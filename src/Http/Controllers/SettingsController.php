<?php
namespace CorpWalletManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use CorpWalletManager\Http\Controllers\Concerns\AuthorizesCorporationAccess;
use CorpWalletManager\Models\Settings;
use CorpWalletManager\Models\RecalcLog;
use CorpWalletManager\Models\Webhook;
use CorpWalletManager\Services\DiscordRoleResolver;
use CorpWalletManager\Jobs\BackfillWalletData;
use CorpWalletManager\Jobs\ComputeDailyPrediction;
use CorpWalletManager\Jobs\BackfillDivisionWalletData;
use CorpWalletManager\Jobs\BackfillCharacterContributions;
use CorpWalletManager\Jobs\ComputeDivisionDailyPrediction;

class SettingsController extends Controller
{
    use AuthorizesCorporationAccess;
    /**
     * Show the settings page
     */
    public function index()
    {
        try {
            $settings = Settings::pluck('value', 'key')->toArray();
            
            // Provide default values if not set
            $defaultSettings = [
                'refresh_interval' => config('corpwalletmanager.refresh_interval', 60000),
                'refresh_minutes' => '5',
                'color_actual' => config('corpwalletmanager.color_actual', '#4cafef'),
                'color_predicted' => config('corpwalletmanager.color_predicted', '#ef4444'),
                'decimals' => config('corpwalletmanager.decimals', 2),
                'use_precomputed_predictions' => config('corpwalletmanager.use_precomputed_predictions', true),
                'use_precomputed_monthly_balances' => config('corpwalletmanager.use_precomputed_monthly_balances', true),
                'selected_corporation_id' => null,
                // Member view settings defaults
                'member_show_health' => '1',
                'member_show_trends' => '1',
                'member_show_activity' => '1',
                'member_show_goals' => '1',
                'member_show_milestones' => '1',
                'member_show_balance' => '1',
                'member_show_performance' => '1',
                'member_data_delay' => '0',
                // Personal contribution + leaderboard (v3.0.0 member surface).
                // Operator-toggleable so corps that want a fully corp-wide
                // member view can stay on it. Leaderboard mode is enforced
                // server-side; the radio here only writes the persisted value.
                'member_show_personal_contribution' => '1',
                'member_show_leaderboard' => '1',
                'member_show_mm_compliance' => '1',
                // My Personal Wallet tab (v3.0.0 follow-up). Default on so
                // an existing corp gets the tab visible after the migration
                // without operators having to opt in.
                'member_show_personal_wallet' => '1',
                'member_leaderboard_mode' => 'isk_visible',
                'member_leaderboard_size' => '10',
                'goal_savings_target' => '1000000000',
                'goal_activity_target' => '1000',
                'goal_growth_target' => '10',
                // Alert thresholds (0 = disabled)
                'alert_large_transaction_threshold' => '0',
                'alert_low_balance_threshold' => '0',
                // Anomaly detection thresholds (0 = disabled). The
                // contribution-drop floor only flags members whose prior
                // 3-month average was above this; the unusual-recipient
                // threshold filters the corp_account_withdrawal scan so
                // only large outflows to never-seen recipients alert.
                'anomaly_contribution_threshold' => '0',
                'anomaly_unusual_recipient_threshold' => '0',
                // Alliance tax rates (% of contribution surrendered to the
                // alliance — 0 = no alliance, no tax). Applied per-bucket
                // on the Top Contributors leaderboard to show before/after
                // tax. Storage as string lets us preserve fractional rates
                // like 7.5%.
                'alliance_tax_ratting_pct' => '0',
                'alliance_tax_mission_pct' => '0',
                'alliance_tax_tax_payment_pct' => '0',
                'alliance_tax_donation_voluntary_pct' => '0',
                'alliance_tax_industry_pct' => '0',
                // Comma-separated list of party ids the corp pays alliance tax
                // to (alliance master character id, holding corp id, or the
                // alliance entity id itself). Used by the Alliance Tax
                // reconciliation tab to identify outgoing payments as the
                // monthly alliance remit.
                'alliance_tax_recipient_ids' => '',
                // Comma-separated list of description keywords. Any outgoing
                // payment whose description contains one of these is counted
                // as alliance tax in addition to recipient-id matches. Useful
                // when operators tag remits with a memo like "MINC-TAX" so
                // reconciliation works even if the recipient party rotates.
                'alliance_tax_description_keywords' => '',
            ];
            
            // Merge defaults with saved settings
            $settings = array_merge($defaultSettings, $settings);
            
            // Convert string '1'/'0' to boolean for checkboxes
            foreach (['use_precomputed_predictions', 'use_precomputed_monthly_balances',
                      'member_show_health', 'member_show_trends', 'member_show_activity',
                      'member_show_goals', 'member_show_milestones', 'member_show_balance',
                      'member_show_performance',
                      'member_show_personal_contribution', 'member_show_leaderboard',
                      'member_show_mm_compliance', 'member_show_personal_wallet'] as $key) {
                if (isset($settings[$key])) {
                    $settings[$key] = in_array($settings[$key], ['1', 'true', true], true);
                }
            }
            
            // Get available corporations from the database
            $corporations = [];
            try {
                if (DB::getSchemaBuilder()->hasTable('corporation_infos')) {
                    $corporations = DB::table('corporation_infos')
                        ->select('corporation_id', 'name')
                        ->orderBy('name')
                        ->get();
                } else {
                    $corporations = DB::table('corporation_wallet_balances')
                        ->distinct()
                        ->selectRaw('corporation_id, corporation_id as name')
                        ->whereNotNull('corporation_id')
                        ->orderBy('corporation_id')
                        ->get();
                }
            } catch (\Exception $e) {
                Log::warning('Could not fetch corporations: ' . $e->getMessage());
            }
            
            // Get recent job logs for display
            $recentLogs = RecalcLog::with('corporation')
                ->orderBy('started_at', 'desc')
                ->limit(10)
                ->get();

            // Discord webhook management data
            $webhooks = Webhook::orderBy('name')->get();
            $discordRoles = DiscordRoleResolver::listRoles();
            $discordRoleProvider = DiscordRoleResolver::providerLabel();

            // Pre-compute the role lookup map once and pre-resolve each
            // webhook's role mention so the per-row pill in the webhook
            // list renders without running the provider queries N times.
            $roleLookupMap = DiscordRoleResolver::roleLookupMap();
            $roleProviderAvailable = DiscordRoleResolver::isAvailable();
            $webhookRoleDescriptions = [];
            foreach ($webhooks as $wh) {
                $webhookRoleDescriptions[$wh->id] = DiscordRoleResolver::describeRoleMention(
                    $wh->discord_role_id,
                    $roleLookupMap
                );
            }

            // Pre-compute the routing-map snapshot: for every notification
            // category (7 in CWM - weekly / monthly / on_demand / large_tx /
            // low_balance / contribution_drop / unusual_recipient), which
            // enabled webhooks would receive it and what role each would
            // mention. The view renders this read-only.
            //
            // Alliance-tax warning: a corp with alliance tax recipients
            // configured but zero webhooks subscribed to any of the report
            // categories (weekly / monthly / on_demand) means its alliance
            // remit reconciliation would never reach Discord. Surfaced as a
            // single highlighted row so operators notice during setup.
            $routingMap = $this->buildRoutingMap($webhooks, $roleLookupMap, $settings, $corporations);

            return view('corpwalletmanager::settings', compact(
                'settings', 'recentLogs', 'corporations',
                'webhooks', 'discordRoles', 'discordRoleProvider',
                'roleProviderAvailable', 'webhookRoleDescriptions',
                'routingMap'
            ));
            
        } catch (\Exception $e) {
            Log::error('CorpWalletManager settings page error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load settings page. Please check logs.');
        }
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
        try {
            $request->validate([
                'refresh_minutes' => 'required|in:0,5,15,30,60',
                'color_actual' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'color_predicted' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'decimals' => 'required|integer|min:0|max:8',
                'selected_corporation_id' => 'nullable|numeric',
                'alert_large_transaction_threshold' => 'nullable|numeric|min:0',
                'alert_low_balance_threshold' => 'nullable|numeric|min:0',
                'anomaly_contribution_threshold' => 'nullable|numeric|min:0',
                'anomaly_unusual_recipient_threshold' => 'nullable|numeric|min:0',
                'alliance_tax_ratting_pct' => 'nullable|numeric|min:0|max:100',
                'alliance_tax_mission_pct' => 'nullable|numeric|min:0|max:100',
                'alliance_tax_tax_payment_pct' => 'nullable|numeric|min:0|max:100',
                'alliance_tax_donation_voluntary_pct' => 'nullable|numeric|min:0|max:100',
                'alliance_tax_industry_pct' => 'nullable|numeric|min:0|max:100',
                'alliance_tax_recipient_ids' => 'nullable|string|max:1000',
                'alliance_tax_description_keywords' => 'nullable|string|max:1000',
                'member_leaderboard_mode' => 'nullable|in:isk_visible,percentage,rank_only',
                'member_leaderboard_size' => 'nullable|in:5,10,20',
            ]);
    
            // Convert refresh_minutes to milliseconds
            $refreshMinutes = $request->input('refresh_minutes');
            $refreshInterval = $refreshMinutes == '0' ? 0 : ($refreshMinutes * 60 * 1000);
    
            // For checkboxes, check if they exist in the request
            // When a checkbox is unchecked, it won't be in the request at all
            $checkboxFields = [
                'use_precomputed_predictions',
                'use_precomputed_monthly_balances',
                'member_show_health',
                'member_show_trends',
                'member_show_activity',
                'member_show_goals',
                'member_show_milestones',
                'member_show_balance',
                'member_show_performance',
                'member_show_personal_contribution',
                'member_show_leaderboard',
                'member_show_mm_compliance',
                'member_show_personal_wallet',
            ];
            
            // Build settings array
            $settingsToUpdate = [
                'refresh_interval' => $refreshInterval,
                'refresh_minutes' => $refreshMinutes,
                'color_actual' => $request->input('color_actual'),
                'color_predicted' => $request->input('color_predicted'),
                'decimals' => $request->input('decimals'),
                'selected_corporation_id' => $request->input('selected_corporation_id', ''),
                'member_data_delay' => $request->input('member_data_delay', '0'),
                'goal_savings_target' => $request->input('goal_savings_target', '1000000000'),
                'goal_activity_target' => $request->input('goal_activity_target', '1000'),
                'goal_growth_target' => $request->input('goal_growth_target', '10'),
                'alert_large_transaction_threshold' => $request->input('alert_large_transaction_threshold', '0'),
                'alert_low_balance_threshold' => $request->input('alert_low_balance_threshold', '0'),
                'anomaly_contribution_threshold' => $request->input('anomaly_contribution_threshold', '0'),
                'anomaly_unusual_recipient_threshold' => $request->input('anomaly_unusual_recipient_threshold', '0'),
                'alliance_tax_ratting_pct' => $request->input('alliance_tax_ratting_pct', '0'),
                'alliance_tax_mission_pct' => $request->input('alliance_tax_mission_pct', '0'),
                'alliance_tax_tax_payment_pct' => $request->input('alliance_tax_tax_payment_pct', '0'),
                'alliance_tax_donation_voluntary_pct' => $request->input('alliance_tax_donation_voluntary_pct', '0'),
                'alliance_tax_industry_pct' => $request->input('alliance_tax_industry_pct', '0'),
                'alliance_tax_recipient_ids' => $request->input('alliance_tax_recipient_ids', ''),
                'alliance_tax_description_keywords' => $request->input('alliance_tax_description_keywords', ''),
                // Personal contribution + leaderboard. Mode + size go through
                // the validator above so a tampered POST cannot store an
                // unsupported value (which would otherwise leak through to
                // the privacy gate as "anything unrecognised = isk_visible").
                'member_leaderboard_mode' => $request->input('member_leaderboard_mode', 'isk_visible'),
                'member_leaderboard_size' => $request->input('member_leaderboard_size', '10'),
            ];
            
            // Handle checkboxes — absent from the request means unchecked.
            foreach ($checkboxFields as $field) {
                $settingsToUpdate[$field] = $request->has($field) ? '1' : '0';
            }

            // Update each setting
            foreach ($settingsToUpdate as $key => $value) {
                $setting = Settings::where('key', $key)->first();

                if ($setting) {
                    $setting->value = (string)$value;
                    $setting->updated_at = now();
                    $setting->save();
                } else {
                    Settings::create([
                        'key' => $key,
                        'value' => (string)$value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Mirror the saved selection into the admin's session so their
            // next page load uses it immediately — otherwise they'd still
            // see the previously-resolved corp until the session expired.
            $savedCorp = $request->input('selected_corporation_id');
            if (is_numeric($savedCorp) && (int) $savedCorp > 0) {
                $this->setSessionCorporation((int) $savedCorp);
            }

            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('success', 'Settings updated successfully.');
                
        } catch (\Exception $e) {
            Log::error('Settings update error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('error', 'Failed to update settings: ' . $e->getMessage());
        }
    }

    /**
     * Reset settings to defaults
     */
    public function reset()
    {
        try {
            Settings::truncate();
            
            Log::info('CorpWalletManager settings reset to defaults');
            
            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('success', 'Settings reset to defaults!');
                
        } catch (\Exception $e) {
            Log::error('CorpWalletManager settings reset error: ' . $e->getMessage());
            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('error', 'Failed to reset settings. Please check logs.');
        }
    }

    /**
     * Normalize a settings-supplied corporation id to a positive int or
     * null. The Settings model returns '' (empty string) when no corp is
     * selected; passing that straight to a queue job leads to
     * RecalcLog::create() writing '' into a BIGINT column and MySQL
     * rejecting the insert with "Incorrect integer value: '' for column
     * corporation_id". Every trigger method routes through this helper
     * so the job receives a clean null or a real id.
     */
    private function normalizeCorporationId($value): ?int
    {
        return (is_numeric($value) && (int) $value > 0) ? (int) $value : null;
    }

    /**
     * Trigger manual wallet backfill
     */
    public function triggerBackfill(Request $request)
    {
        try {
            // Check if a backfill job is already running
            $runningJobs = RecalcLog::where('job_type', 'wallet_backfill')
                ->where('status', RecalcLog::STATUS_RUNNING)
                ->count();
                
            if ($runningJobs > 0) {
                return redirect()
                    ->route('corpwalletmanager.settings')
                    ->with('warning', 'A backfill job is already running. Please wait for it to complete.');
            }
            
            // Use selected corporation if configured. Normalize so the
            // empty-string default never reaches the queue (see helper docblock).
            $corporationId = $this->normalizeCorporationId(Settings::getSetting('selected_corporation_id'));
            
            BackfillWalletData::dispatch($corporationId);
            
            Log::info('CorpWalletManager wallet backfill job dispatched manually', [
                'corporation_id' => $corporationId
            ]);
            
            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('success', 'Wallet backfill job dispatched!');
                
        } catch (\Exception $e) {
            Log::error('CorpWalletManager backfill dispatch error: ' . $e->getMessage());
            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('error', 'Failed to dispatch backfill job. Please check logs.');
        }
    }

    /**
     * Trigger prediction computation
     */
    public function triggerPrediction(Request $request)
    {
        try {
            // Check if a prediction job is already running
            $runningJobs = RecalcLog::where('job_type', 'daily_prediction')
                ->where('status', RecalcLog::STATUS_RUNNING)
                ->count();
                
            if ($runningJobs > 0) {
                return redirect()
                    ->route('corpwalletmanager.settings')
                    ->with('warning', 'A prediction job is already running. Please wait for it to complete.');
            }
            
            // Use selected corporation if configured. Normalize so the
            // empty-string default never reaches the queue (see helper docblock).
            $corporationId = $this->normalizeCorporationId(Settings::getSetting('selected_corporation_id'));
            
            ComputeDailyPrediction::dispatch($corporationId);
            
            Log::info('CorpWalletManager prediction computation job dispatched manually', [
                'corporation_id' => $corporationId
            ]);
            
            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('success', 'Prediction computation job dispatched!');
                
        } catch (\Exception $e) {
            Log::error('CorpWalletManager prediction dispatch error: ' . $e->getMessage());
            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('error', 'Failed to dispatch prediction job. Please check logs.');
        }
    }
    
    /**
     * Trigger division backfill
     */
    public function triggerDivisionBackfill(Request $request)
    {
        try {
            // Check if a division backfill job is already running
            $runningJobs = RecalcLog::where('job_type', 'division_backfill')
                ->where('status', RecalcLog::STATUS_RUNNING)
                ->count();
                
            if ($runningJobs > 0) {
                return redirect()
                    ->route('corpwalletmanager.settings')
                    ->with('warning', 'A division backfill job is already running. Please wait for it to complete.');
            }
            
            // Use selected corporation if configured. Normalize so the
            // empty-string default never reaches the queue (see helper docblock).
            $corporationId = $this->normalizeCorporationId(Settings::getSetting('selected_corporation_id'));
            
            BackfillDivisionWalletData::dispatch($corporationId);
            
            Log::info('CorpWalletManager division backfill job dispatched manually', [
                'corporation_id' => $corporationId
            ]);
            
            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('success', 'Division backfill job dispatched!');
                
        } catch (\Exception $e) {
            Log::error('CorpWalletManager division backfill dispatch error: ' . $e->getMessage());
            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('error', 'Failed to dispatch division backfill job. Please check logs.');
        }
    }
    
    /**
     * Trigger division prediction computation
     */
    public function triggerDivisionPrediction(Request $request)
    {
        try {
            // Check if a division prediction job is already running
            $runningJobs = RecalcLog::where('job_type', 'division_prediction')
                ->where('status', RecalcLog::STATUS_RUNNING)
                ->count();
                
            if ($runningJobs > 0) {
                return redirect()
                    ->route('corpwalletmanager.settings')
                    ->with('warning', 'A division prediction job is already running. Please wait for it to complete.');
            }
            
            // Use selected corporation if configured. Normalize so the
            // empty-string default never reaches the queue (see helper docblock).
            $corporationId = $this->normalizeCorporationId(Settings::getSetting('selected_corporation_id'));
            
            ComputeDivisionDailyPrediction::dispatch($corporationId);
            
            Log::info('CorpWalletManager division prediction job dispatched manually', [
                'corporation_id' => $corporationId
            ]);
            
            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('success', 'Division prediction job dispatched!');
                
        } catch (\Exception $e) {
            Log::error('CorpWalletManager division prediction dispatch error: ' . $e->getMessage());
            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('error', 'Failed to dispatch division prediction job. Please check logs.');
        }
    }
    
    /**
     * Trigger character contribution backfill.
     *
     * Dispatches BackfillCharacterContributions job, which wraps the
     * artisan command. Used when setting up alliance tax / Top
     * Contributors after upgrading from v2, after classifier changes
     * (industry / ESS), or after Mining Manager is installed and
     * historical tax-coded donations need to be re-split into the
     * tax_payment bucket.
     */
    public function triggerContributionBackfill(Request $request)
    {
        try {
            $months = max(1, min(36, (int) $request->input('months', 6)));

            BackfillCharacterContributions::dispatch($months);

            Log::info('CorpWalletManager contribution backfill dispatched', [
                'months' => $months,
            ]);

            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('success', "Contribution backfill dispatched for the last {$months} month(s). The job runs in the background; refresh the Top Contributors / Alliance Tax tabs in a few minutes.");

        } catch (\Exception $e) {
            Log::error('CorpWalletManager contribution backfill dispatch error: ' . $e->getMessage());
            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('error', 'Failed to dispatch contribution backfill. Please check logs.');
        }
    }

    /**
     * Top recipients of recent corp outgoing payments. Powers the
     * Alliance Tax recipient-id picker on the Settings page so
     * operators don't have to look up alliance master / holding-corp
     * party ids by hand.
     */
    public function recentOutgoingRecipients(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            if (! $corporationId) {
                return response()->json([
                    'success'    => false,
                    'message'    => 'Corporation not selected.',
                    'recipients' => [],
                ], 400);
            }

            $months = max(1, min(12, (int) $request->get('months', 6)));
            $from = Carbon::now()->subMonths($months)->startOfMonth();

            $rows = DB::table('corporation_wallet_journals')
                ->where('corporation_id', $corporationId)
                ->where('date', '>=', $from)
                ->whereIn('ref_type', ['corporation_account_withdrawal', 'player_donation'])
                ->where('amount', '<', 0)
                ->whereNotNull('second_party_id')
                // Don't suggest the corp's own id (internal transfers
                // are already filtered out of income/expense but here
                // we explicitly drop them from the picker so operators
                // never accidentally configure their own corp as the
                // alliance recipient).
                ->where('second_party_id', '!=', $corporationId)
                ->groupBy('second_party_id')
                ->selectRaw('second_party_id, SUM(ABS(amount)) AS total_sent, COUNT(*) AS cnt')
                ->orderByDesc('total_sent')
                ->limit(20)
                ->get();

            if ($rows->isEmpty()) {
                return response()->json([
                    'success'        => true,
                    'corporation_id' => $corporationId,
                    'months'         => $months,
                    'recipients'     => [],
                ]);
            }

            $ids = $rows->pluck('second_party_id')->map(fn ($id) => (int) $id)->all();

            // Layered resolver: character_infos -> corporation_infos ->
            // alliance_infos -> universe_names -> ESI fallback. External
            // recipients that no local table knows still resolve on
            // first load thanks to the ESI step (writes back into
            // universe_names so subsequent calls are free).
            $resolved = app(\CorpWalletManager\Services\EntityNameResolver::class)->resolve($ids, true);

            $recipients = [];
            foreach ($rows as $r) {
                $id = (int) $r->second_party_id;
                $info = $resolved[$id] ?? ['name' => 'Unknown', 'type' => 'unknown', 'source' => ''];
                $recipients[] = [
                    'id'         => $id,
                    'name'       => $info['name'],
                    'type'       => $info['type'],
                    'total_sent' => (float) $r->total_sent,
                    'count'      => (int) $r->cnt,
                ];
            }

            return response()->json([
                'success'        => true,
                'corporation_id' => $corporationId,
                'months'         => $months,
                'recipients'     => $recipients,
            ]);

        } catch (\Exception $e) {
            Log::error('CorpWalletManager recent outgoing recipients error: ' . $e->getMessage());
            return response()->json([
                'success'    => false,
                'message'    => 'Failed to load recipients.',
                'recipients' => [],
            ], 500);
        }
    }

    /**
     * Get job status via AJAX
     */
    public function jobStatus()
    {
        try {
            $runningJobs = RecalcLog::running()
                ->orderBy('started_at', 'desc')
                ->get();
                
            $recentJobs = RecalcLog::orderBy('started_at', 'desc')
                ->limit(5)
                ->get();
            
            return response()->json([
                'running_jobs' => $runningJobs->count(),
                'recent_jobs' => $recentJobs->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'job_type' => $log->job_type_display,
                        'status' => $log->status,
                        'started_at' => $log->started_at->format('Y-m-d H:i:s'),
                        'duration' => $log->formatted_duration,
                        'records_processed' => $log->records_processed,
                    ];
                })
            ]);
            
        } catch (\Exception $e) {
            Log::error('CorpWalletManager job status API error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Unable to fetch job status',
                'running_jobs' => 0,
                'recent_jobs' => []
            ], 500);
        }
    }
    
    /**
     * Get selected corporation settings via AJAX.
     *
     * Resolution honors per-user session selection first (set via
     * /api/set-corporation or by saving the settings form as an admin),
     * then falls back to the global corpwalletmanager_settings row, then
     * the caller's first authorized corp. See AuthorizesCorporationAccess.
     */
    public function getSelectedCorporation(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);

            return response()->json([
                'corporation_id' => $corporationId,
                'refresh_minutes' => Settings::getSetting('refresh_minutes', '5'),
                'refresh_interval' => Settings::getIntegerSetting('refresh_interval', 300000)
            ]);

        } catch (\Exception $e) {
            Log::error('CorpWalletManager get selected corporation error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Unable to fetch corporation settings',
                'corporation_id' => null
            ], 500);
        }
    }

    /**
     * Get access logs via AJAX
     */
    public function getAccessLogs(Request $request)
    {
        try {
            // Check if the table exists first
            if (!Schema::hasTable('corpwalletmanager_access_logs')) {
                return response()->json([
                    'logs' => [],
                    'message' => 'Access logs table not found. Please run migrations.'
                ]);
            }
            
            $logsQuery = DB::table('corpwalletmanager_access_logs as al');
            
            // Check if users table exists for join
            if (Schema::hasTable('users')) {
                $logsQuery->leftJoin('users as u', 'al.user_id', '=', 'u.id');
            }
            
            // Check if corporation_infos table exists
            if (Schema::hasTable('corporation_infos')) {
                $logsQuery->leftJoin('corporation_infos as c', 'al.corporation_id', '=', 'c.corporation_id');
            }
            
            $logs = $logsQuery
                ->select(
                    Schema::hasTable('users') ? 'u.name as user_name' : DB::raw('al.user_id as user_name'),
                    'al.view_type',
                    Schema::hasTable('corporation_infos') ? 'c.name as corporation_name' : DB::raw('al.corporation_id as corporation_name'),
                    'al.accessed_at',
                    'al.ip_address'
                )
                ->orderBy('al.accessed_at', 'desc')
                ->limit(50)
                ->get();
                
            return response()->json([
                'logs' => $logs->map(function ($log) {
                    return [
                        'user' => is_numeric($log->user_name) ? 'User #' . $log->user_name : ($log->user_name ?? 'Unknown'),
                        'view' => ucfirst($log->view_type),
                        'corporation' => is_numeric($log->corporation_name) ? 'Corp #' . $log->corporation_name : ($log->corporation_name ?? 'All'),
                        'accessed_at' => Carbon::parse($log->accessed_at)->diffForHumans(),
                        'ip_address' => $log->ip_address ?? 'N/A',
                    ];
                })
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to load access logs', ['error' => $e->getMessage()]);
            return response()->json([
                'logs' => [],
                'error' => 'Unable to load access logs'
            ], 500);
        }
    }

    /**
     * Show the help & documentation page.
     *
     * Surfaces the installed version (resolved via Composer's installed.json
     * with a constant fallback for the rare case where Composer metadata
     * isn't available, e.g. zip-deployed installs). The Help page Version
     * Status block uses this to show the installed version + a link to the
     * v3.0.0 release notes; no Packagist round-trip, no caching layer
     * needed for a pre-release help page.
     */
    public function help()
    {
        $installedVersion = $this->resolveInstalledVersion();

        // Version Status: prefer Manager Core's EcosystemVersionChecker
        // (it does the Packagist round-trip with a 6h cache, the dev-branch
        // detection, and the status enum mapping in one shot) and fall back
        // to a minimal local shape when MC isn't installed. The blade renders
        // both shapes identically.
        $versionStatus = $this->resolveVersionStatus($installedVersion);

        return view('corpwalletmanager::help.index', [
            'installedVersion' => $installedVersion['version'],
            'installedVersionSource' => $installedVersion['source'],
            'releaseTag' => 'v3.0.0',
            'releaseCodename' => 'The Ecosystem Era',
            'releaseUrl' => 'https://github.com/MattFalahe/Corp-Wallet-Manager/releases/tag/v3.0.0',
            'versionStatus' => $versionStatus,
        ]);
    }

    /**
     * Build the version status shape the Version Status panel renders.
     * Same field names as `ManagerCore\Services\EcosystemVersionChecker`
     * returns so a single blade renders both code paths:
     *
     *   current, current_source, is_dev_branch, latest, status, message, release_url
     *
     * Status enum (matches MC): current / outdated / ahead / dev_branch /
     * unreleased / unknown / offline.
     *
     * @param array{version: string, source: string} $installed
     * @return array<string, mixed>
     */
    protected function resolveVersionStatus(array $installed): array
    {
        // MC installed? delegate to its checker so the cache and Packagist
        // logic live in one place across the suite.
        if (class_exists(\ManagerCore\Services\EcosystemVersionChecker::class)) {
            try {
                return app(\ManagerCore\Services\EcosystemVersionChecker::class)
                    ->getStatusForPlugin('corp-wallet-manager');
            } catch (\Throwable $e) {
                // fall through to local
            }
        }

        // MC not installed: build a minimal local shape so the panel still
        // renders something useful. We skip the Packagist round-trip here
        // (CWM has no reason to maintain a duplicate cache layer) and just
        // report the installed version + a "Manager Core not installed,
        // cannot check for updates" message.
        $current = $installed['version'] ?? null;
        $source  = $installed['source'] ?? 'fallback';
        $isDev   = $current !== null
            && (str_starts_with($current, 'dev-') || str_ends_with($current, '-dev'));

        return [
            'plugin_key'     => 'corp-wallet-manager',
            'package'        => 'mattfalahe/corp-wallet-manager',
            'current'        => $current,
            'current_source' => $source === 'composer' ? 'composer' : 'config',
            'is_dev_branch'  => $isDev,
            'latest'         => null,
            'status'         => $isDev ? 'dev_branch' : 'unknown',
            'message'        => $isDev
                ? 'Development branch (' . $current . '). Manager Core not installed, so latest stable version on Packagist was not checked.'
                : 'Installed: ' . ($current ?? '?') . '. Manager Core not installed, so latest stable version on Packagist was not checked.',
            'release_url'    => null,
        ];
    }

    /**
     * Best-effort installed-version lookup.
     *
     * Composer's runtime API is the source of truth when Composer metadata
     * is available (the normal path for a `composer require ...` install).
     * Falls back to the CWM_VERSION constant defined in the service
     * provider so the Help page never renders an empty version badge.
     *
     * @return array{version: string, source: string}
     */
    protected function resolveInstalledVersion(): array
    {
        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                $version = \Composer\InstalledVersions::getPrettyVersion('mattfalahe/corp-wallet-manager');
                if (! empty($version)) {
                    return ['version' => $version, 'source' => 'composer'];
                }
            } catch (\Throwable $e) {
                // fall through to constant
            }
        }

        if (defined('CorpWalletManager\\CWM_VERSION')) {
            return ['version' => constant('CorpWalletManager\\CWM_VERSION'), 'source' => 'constant'];
        }

        return ['version' => '3.0.0', 'source' => 'fallback'];
    }

    /**
     * Build the Notification Routing Map snapshot.
     *
     * For every CWM notification category, walks each enabled webhook with
     * the matching `notify_$category` flag set and pre-resolves the role
     * each would mention. The Settings tab renders this as a read-only
     * table, mirroring SM's Routing Map UX.
     *
     * Categories:
     *   - weekly_report       scheduled Monday digest
     *   - monthly_report      scheduled 1st-of-month digest
     *   - on_demand_report    Director view "Generate Report" button
     *   - large_transfer      alert when |amount| >= configured threshold
     *   - low_balance         alert when corp balance falls under threshold
     *   - contribution_drop   alert when a member's 3mo avg collapses
     *   - unusual_recipient   alert on first-time large outflow recipient
     *
     * Returns:
     *   [
     *     'categories' => [
     *        ['key' => 'weekly_report',
     *         'label' => 'Weekly Report',
     *         'kind' => 'report' | 'alert',
     *         'icon' => 'fa-calendar-week',
     *         'webhooks' => [
     *            ['webhook' => Webhook, 'role' => describeRoleMention()],
     *            ...
     *         ],
     *        ],
     *        ...
     *     ],
     *     'summary' => [
     *        'total' => 7,
     *        'covered' => int,   // categories with >=1 enabled subscriber
     *        'silent'  => int,   // categories with zero enabled subscribers
     *     ],
     *     'alliance_tax_warning' => null | string,
     *   ]
     */
    private function buildRoutingMap($webhooks, array $roleLookupMap, array $settings, $corporations): array
    {
        $categoryDefs = [
            ['key' => 'weekly_report',      'label' => 'Weekly Report',            'kind' => 'report', 'icon' => 'fa-calendar-week',   'flag' => 'notify_weekly_report',      'desc' => 'Scheduled weekly digest (Mondays @ 03:30)'],
            ['key' => 'monthly_report',     'label' => 'Monthly Report',           'kind' => 'report', 'icon' => 'fa-calendar-alt',    'flag' => 'notify_monthly_report',     'desc' => 'Scheduled monthly digest (1st @ 03:00)'],
            ['key' => 'on_demand_report',   'label' => 'On-Demand Report',         'kind' => 'report', 'icon' => 'fa-file-export',     'flag' => 'notify_on_demand_report',   'desc' => 'Manually generated from the Director view'],
            ['key' => 'large_transfer',     'label' => 'Large Transfer Alert',     'kind' => 'alert',  'icon' => 'fa-coins',           'flag' => 'notify_large_transfer',     'desc' => 'Fired when |amount| >= threshold (detect-alerts every 40min)'],
            ['key' => 'low_balance',        'label' => 'Low Balance Alert',        'kind' => 'alert',  'icon' => 'fa-battery-quarter', 'flag' => 'notify_low_balance',        'desc' => 'Fired when corp balance falls below threshold'],
            ['key' => 'contribution_drop',  'label' => 'Contribution Drop Alert',  'kind' => 'alert',  'icon' => 'fa-chart-line',      'flag' => 'notify_contribution_drop',  'desc' => "Fired when a member's recent 3mo avg collapses to <20% of prior"],
            ['key' => 'unusual_recipient',  'label' => 'Unusual Recipient Alert',  'kind' => 'alert',  'icon' => 'fa-user-secret',     'flag' => 'notify_unusual_recipient',  'desc' => 'Fired when a corp withdrawal lands at a never-seen recipient above threshold'],
        ];

        $enabledWebhooks = $webhooks->where('is_enabled', true);
        $corpLabelMap = [];
        foreach ($corporations as $corp) {
            $corpLabelMap[(int) $corp->corporation_id] = $corp->name;
        }

        $categories = [];
        $covered = 0;
        $silent  = 0;

        foreach ($categoryDefs as $def) {
            $subs = [];
            foreach ($enabledWebhooks as $wh) {
                if (! (bool) $wh->{$def['flag']}) {
                    continue;
                }
                $subs[] = [
                    'webhook'    => $wh,
                    'role'       => DiscordRoleResolver::describeRoleMention($wh->discord_role_id, $roleLookupMap),
                    'corp_label' => $wh->corporation_id
                        ? ($corpLabelMap[(int) $wh->corporation_id] ?? ('Corp ' . $wh->corporation_id))
                        : null,
                ];
            }
            if (! empty($subs)) {
                $covered++;
            } else {
                $silent++;
            }
            $categories[] = [
                'key'      => $def['key'],
                'label'    => $def['label'],
                'kind'     => $def['kind'],
                'icon'     => $def['icon'],
                'desc'     => $def['desc'],
                'webhooks' => $subs,
            ];
        }

        // Alliance tax warning: alliance tax is configured for the selected
        // corp if any of the *_pct settings is > 0 OR the recipient_ids /
        // description_keywords list is non-empty. If so, the corp needs at
        // least one report-category subscriber so the monthly reconciliation
        // reaches Discord. We check the SELECTED corp only - global webhooks
        // are inspected too since they catch every corp's reports.
        $allianceTaxWarning = null;
        $selectedCorpId = is_numeric($settings['selected_corporation_id'] ?? null) && (int) $settings['selected_corporation_id'] > 0
            ? (int) $settings['selected_corporation_id']
            : null;

        $hasAllianceTaxConfig = false;
        foreach (['alliance_tax_ratting_pct', 'alliance_tax_mission_pct', 'alliance_tax_tax_payment_pct',
                  'alliance_tax_donation_voluntary_pct', 'alliance_tax_industry_pct'] as $k) {
            if ((float) ($settings[$k] ?? 0) > 0) {
                $hasAllianceTaxConfig = true;
                break;
            }
        }
        if (! $hasAllianceTaxConfig) {
            if (trim((string) ($settings['alliance_tax_recipient_ids'] ?? '')) !== ''
                || trim((string) ($settings['alliance_tax_description_keywords'] ?? '')) !== '') {
                $hasAllianceTaxConfig = true;
            }
        }

        if ($hasAllianceTaxConfig && $selectedCorpId !== null) {
            // Does ANY enabled webhook (global or scoped to this corp) cover
            // any of the three report categories?
            $hasReportCoverage = false;
            foreach ($enabledWebhooks as $wh) {
                $scopeOk = ($wh->corporation_id === null) || ((int) $wh->corporation_id === $selectedCorpId);
                if (! $scopeOk) continue;
                if ($wh->notify_weekly_report || $wh->notify_monthly_report || $wh->notify_on_demand_report) {
                    $hasReportCoverage = true;
                    break;
                }
            }
            if (! $hasReportCoverage) {
                $corpLabel = $corpLabelMap[$selectedCorpId] ?? ('Corp ' . $selectedCorpId);
                $allianceTaxWarning = "Alliance tax is configured for {$corpLabel} but no enabled webhook is subscribed to any of the report categories (Weekly / Monthly / On-Demand). The monthly remit reconciliation will compute but never reach Discord.";
            }
        }

        return [
            'categories' => $categories,
            'summary' => [
                'total'   => count($categoryDefs),
                'covered' => $covered,
                'silent'  => $silent,
            ],
            'alliance_tax_warning' => $allianceTaxWarning,
        ];
    }
}
