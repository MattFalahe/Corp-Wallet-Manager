<?php

namespace CorpWalletManager\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use CorpWalletManager\Models\AlertState;
use CorpWalletManager\Models\CharacterContribution;
use CorpWalletManager\Models\RecalcLog;
use CorpWalletManager\Models\ReportSchedule;
use CorpWalletManager\Models\Settings;
use CorpWalletManager\Models\Webhook;
use CorpWalletManager\Services\ContributionService;
use CorpWalletManager\Services\DiscordRoleResolver;
use CorpWalletManager\Services\WebhookService;

/**
 * Admin-only diagnostic page for Corp Wallet Manager.
 *
 * Reachable at /corp-wallet-manager/diagnostic; intentionally NOT linked
 * from the sidebar (operators navigate manually). Every check is read-only.
 * Mirrors the suite-wide diagnostic standard: Health Checks landing tab,
 * Master Test smoke runner, System Validation / Settings Health / Data
 * Integrity for the rest of Tier 1, plus a CWM-specific Wallet Trace tab
 * that walks a single journal entry through the classify -> cache ->
 * alert -> publish pipeline.
 *
 * Each tab opens with a .diag-tab-intro box per the standard so operators
 * learn the tab's purpose without a Help & Documentation cross-reference.
 */
class DiagnosticController extends Controller
{
    /**
     * Artisan commands the plugin registers into SeAT's scheduler, each
     * paired with the expected cron expression. Source of truth: the
     * ScheduleSeeder. Drift here is itself a finding (a scheduled job
     * stopped registering, or its expression changed without a seeder
     * update).
     */
    private const EXPECTED_SCHEDULES = [
        'corpwalletmanager:update-hourly'                       => '20 * * * *',
        'corpwalletmanager:daily-aggregation'                   => '0 1 * * *',
        'corpwalletmanager:compute-predictions'                 => '0 2 * * *',
        'corpwalletmanager:compute-division-predictions'        => '30 2 * * *',
        'corpwalletmanager:backtest'                            => '45 2 * * *',
        'corpwalletmanager:detect-alerts'                       => '40 * * * *',
        'corpwalletmanager:compute-contributions'               => '50 * * * *',
        'corpwalletmanager:compute-personal-wallet-aggregates'  => '55 * * * *',
        'corpwalletmanager:dispatch-scheduled-reports'          => '*/5 * * * *',
    ];

    /**
     * Plugin-owned tables. Missing tables here mean migrations have not
     * run cleanly; the operator needs to restart the stack.
     */
    private const PLUGIN_TABLES = [
        'corpwalletmanager_monthly_balances',
        'corpwalletmanager_predictions',
        'corpwalletmanager_division_balances',
        'corpwalletmanager_division_predictions',
        'corpwalletmanager_recalc_logs',
        'corpwalletmanager_settings',
        'corpwalletmanager_prediction_metrics',
        'corpwalletmanager_reports',
        'corpwalletmanager_access_logs',
        'corpwalletmanager_webhooks',
        'corpwalletmanager_alert_state',
        'corpwalletmanager_character_contributions',
        // v3.0.0 additions
        'corpwalletmanager_anomaly_state',
        'corpwalletmanager_member_milestone_state',
        'corpwalletmanager_report_schedules',
        'corpwalletmanager_personal_wallet_aggregates',
        'corpwalletmanager_data_exports',
    ];

    /**
     * SeAT-core tables CWM reads from. Missing tables here mean SeAT's own
     * eveapi migrations haven't run or the plugin is on an incompatible
     * SeAT version.
     */
    private const REQUIRED_SEAT_TABLES = [
        'corporation_wallet_journals',
        'corporation_wallet_balances',
        'corporation_infos',
        'corporation_divisions',
        'refresh_tokens',
        'character_affiliations',
        'character_infos',
    ];

    public function index(Request $request)
    {
        $forceRefresh = (bool) $request->get('refresh', false);
        $activeTab = (string) $request->get('diag_tab', 'health-checks');

        // Wallet Trace inputs (only used when activeTab is wallet-trace; cheap
        // enough to always resolve so the form keeps its last value).
        $traceJournalId = (int) $request->get('trace_journal_id', 0);
        $traceInternalId = (int) $request->get('trace_internal_id', 0);

        // Donation Audit inputs (corp + period in YYYY-MM, defaults to
        // current month). Only fires the query when the tab is active so
        // the other tabs stay cheap.
        $auditCorpId = (int) $request->get('audit_corp_id', 0);
        $auditPeriod = (string) $request->get('audit_period', '');
        if (! preg_match('/^\d{4}-\d{2}$/', $auditPeriod)) {
            $auditPeriod = now()->format('Y-m');
        }

        // Notification Testing tab: corp dropdown options + last-fire
        // outcome flashed from the POST handler.
        $corporations = $this->corporationOptions();
        $notificationTest = session('cwm_notification_test', ['state' => 'idle']);

        // Schedule Trace tab inputs (only resolves when corp + cadence are
        // both supplied via the form; cheap enough to always pass through so
        // the form keeps its last values across reloads).
        $scheduleTraceCorpId = (int) $request->get('schedule_trace_corp_id', 0);
        $scheduleTraceType   = (string) $request->get('schedule_trace_type', '');
        if ($scheduleTraceType !== '' && ! in_array($scheduleTraceType, ['daily', 'weekly', 'monthly', 'quarterly', 'annual'], true)) {
            $scheduleTraceType = '';
        }

        return view('corpwalletmanager::diagnostic.index', [
            'activeTab'        => $activeTab,
            'healthChecks'     => $this->cached('health', $forceRefresh, 30, fn () => $this->runHealthChecks()),
            'masterTest'       => $this->cached('master', $forceRefresh, 60, fn () => $this->runMasterTest()),
            'systemValidation' => $this->cached('sysval', $forceRefresh, 60, fn () => $this->runSystemValidation()),
            'crossPluginChecks' => $this->cached('crossplugin', $forceRefresh, 60, fn () => $this->runCrossPluginChecks()),
            'settingsHealth'   => $this->cached('settings', $forceRefresh, 30, fn () => $this->runSettingsHealth()),
            'dataIntegrity'    => $this->cached('data', $forceRefresh, 60, fn () => $this->runDataIntegrity()),
            'scheduleStatus'   => $this->cached('schedstat', $forceRefresh, 60, fn () => $this->runScheduleStatus()),
            'personalWalletStatus' => $this->cached('pwastat', $forceRefresh, 60, fn () => $this->runPersonalWalletAggregatorStatus()),
            'anomalyState'     => $this->cached('anomstate', $forceRefresh, 60, fn () => $this->runAnomalyStateSummary()),
            'walletTrace'      => $this->runWalletTrace($traceJournalId, $traceInternalId),
            'traceJournalId'   => $traceJournalId,
            'traceInternalId'  => $traceInternalId,
            'donationAudit'    => $activeTab === 'donation-audit'
                ? $this->runDonationAudit($auditCorpId, $auditPeriod)
                : ['state' => 'idle'],
            'auditCorpId'      => $auditCorpId,
            'auditPeriod'      => $auditPeriod,
            'scheduleTrace'    => ($scheduleTraceCorpId > 0 && $scheduleTraceType !== '')
                ? $this->runScheduleTrace($scheduleTraceCorpId, $scheduleTraceType)
                : ['state' => 'idle'],
            'scheduleTraceCorpId' => $scheduleTraceCorpId,
            'scheduleTraceType'   => $scheduleTraceType,
            'expectedSchedules' => self::EXPECTED_SCHEDULES,
            'corporations'     => $corporations,
            'notificationTest' => $notificationTest,
            'selectedCorpId'   => (int) Settings::getSetting('selected_corporation_id', 0),
        ]);
    }

    /**
     * Build the corporation dropdown options for the Notification Testing
     * tab. Reuses the same source SettingsController uses (corporation_infos
     * preferred, wallet-balances distinct-list as a fallback).
     */
    private function corporationOptions(): array
    {
        try {
            if (Schema::hasTable('corporation_infos')) {
                return DB::table('corporation_infos')
                    ->select('corporation_id', 'name')
                    ->orderBy('name')
                    ->get()
                    ->all();
            }
            if (Schema::hasTable('corporation_wallet_balances')) {
                return DB::table('corporation_wallet_balances')
                    ->distinct()
                    ->selectRaw('corporation_id, corporation_id as name')
                    ->whereNotNull('corporation_id')
                    ->orderBy('corporation_id')
                    ->get()
                    ->all();
            }
        } catch (\Throwable $e) {
            // Best-effort; if the lookup fails the dropdown just renders empty.
        }
        return [];
    }

    // ------------------------------------------------------------------
    // Cache helper
    // ------------------------------------------------------------------

    private function cached(string $key, bool $forceRefresh, int $ttl, callable $compute)
    {
        $cacheKey = 'cwm.diag.' . $key;
        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }
        try {
            return Cache::remember($cacheKey, $ttl, $compute);
        } catch (\Throwable $e) {
            // Cache backend down or compute failed - fall back to direct compute
            // so the diagnostic page never goes blank on a Redis outage.
            return $compute();
        }
    }

    // ------------------------------------------------------------------
    // Tab 1: Health Checks
    // ------------------------------------------------------------------

    private function runHealthChecks(): array
    {
        return [
            $this->checkPluginTables(),
            $this->checkSeatTables(),
            $this->checkScheduleRegistered(),
            $this->checkWebhooks(),
            $this->checkAlertConfig(),
            $this->checkContributionCache(),
            $this->checkWalletJournalData(),
            $this->checkManagerCore(),
            $this->checkMiningManager(),
            $this->checkHrManager(),
            $this->checkRecentJobActivity(),
        ];
    }

    private function checkPluginTables(): array
    {
        $missing = [];
        foreach (self::PLUGIN_TABLES as $t) {
            if (! Schema::hasTable($t)) {
                $missing[] = $t;
            }
        }

        return $this->result(
            empty($missing) ? 'pass' : 'fail',
            'Plugin tables',
            empty($missing)
                ? count(self::PLUGIN_TABLES) . ' tables present'
                : 'Missing: ' . implode(', ', $missing) . ' - migrations may not have run'
        );
    }

    private function checkSeatTables(): array
    {
        $missing = [];
        foreach (self::REQUIRED_SEAT_TABLES as $t) {
            if (! Schema::hasTable($t)) {
                $missing[] = $t;
            }
        }

        return $this->result(
            empty($missing) ? 'pass' : 'fail',
            'SeAT core tables',
            empty($missing)
                ? count(self::REQUIRED_SEAT_TABLES) . ' required tables present'
                : 'Missing: ' . implode(', ', $missing)
        );
    }

    private function checkScheduleRegistered(): array
    {
        if (! Schema::hasTable('schedules')) {
            return $this->result('fail', 'Scheduled commands', 'SeAT schedules table missing');
        }

        $registered = DB::table('schedules')
            ->whereIn('command', array_keys(self::EXPECTED_SCHEDULES))
            ->pluck('expression', 'command')
            ->all();

        $missing = [];
        $drift = [];
        foreach (self::EXPECTED_SCHEDULES as $cmd => $expected) {
            if (! array_key_exists($cmd, $registered)) {
                $missing[] = $cmd;
            } elseif ($registered[$cmd] !== $expected) {
                $drift[] = "{$cmd} (have '{$registered[$cmd]}', expected '{$expected}')";
            }
        }

        if (! empty($missing)) {
            return $this->result('fail', 'Scheduled commands', 'Not registered: ' . implode(', ', $missing));
        }
        if (! empty($drift)) {
            return $this->result('warn', 'Scheduled commands', 'Expression drift: ' . implode('; ', $drift));
        }

        return $this->result('pass', 'Scheduled commands', count(self::EXPECTED_SCHEDULES) . ' commands registered with expected expressions');
    }

    private function checkWebhooks(): array
    {
        if (! Schema::hasTable('corpwalletmanager_webhooks')) {
            return $this->result('warn', 'Discord webhooks', 'Table missing - run migrations');
        }

        $total = Webhook::count();
        $enabled = Webhook::where('is_enabled', true)->count();

        if ($total === 0) {
            return $this->result('warn', 'Discord webhooks', 'No webhooks configured - reports and alerts will not deliver to Discord');
        }
        if ($enabled === 0) {
            return $this->result('warn', 'Discord webhooks', "{$total} configured but all disabled");
        }

        return $this->result('pass', 'Discord webhooks', "{$enabled} enabled / {$total} total");
    }

    private function checkAlertConfig(): array
    {
        $large = (float) Settings::getIntegerSetting('alert_large_transaction_threshold', 0);
        $low = (float) Settings::getIntegerSetting('alert_low_balance_threshold', 0);

        if ($large <= 0 && $low <= 0) {
            return $this->result('warn', 'Alert thresholds', 'Both thresholds set to 0 (disabled) - the detect-alerts job runs but does nothing');
        }

        $parts = [];
        $parts[] = $large > 0 ? 'large-tx: ' . number_format($large, 0) . ' ISK' : 'large-tx: off';
        $parts[] = $low > 0 ? 'low-balance: ' . number_format($low, 0) . ' ISK' : 'low-balance: off';

        return $this->result('pass', 'Alert thresholds', implode(' / ', $parts));
    }

    private function checkContributionCache(): array
    {
        if (! Schema::hasTable('corpwalletmanager_character_contributions')) {
            return $this->result('warn', 'Contribution cache', 'Table missing - run migrations');
        }

        $count = CharacterContribution::count();
        $watermark = Settings::getSetting('contributions_last_internal_id');

        if ($watermark === null || $watermark === '') {
            return $this->result('warn', 'Contribution cache', 'Watermark not initialised - the hourly job has not run yet; cache empty');
        }
        if ($count === 0) {
            return $this->result('warn', 'Contribution cache', "Watermark set ({$watermark}) but cache empty - run `corpwalletmanager:backfill-contributions --months=6` to populate history");
        }

        $distinctCorps = CharacterContribution::distinct('corporation_id')->count('corporation_id');
        $distinctChars = CharacterContribution::distinct('character_id')->count('character_id');

        return $this->result('pass', 'Contribution cache', "{$count} rows / {$distinctCorps} corps / {$distinctChars} characters (watermark: {$watermark})");
    }

    private function checkWalletJournalData(): array
    {
        if (! Schema::hasTable('corporation_wallet_journals')) {
            return $this->result('fail', 'Wallet journal data', 'SeAT corporation_wallet_journals missing');
        }

        $count = DB::table('corporation_wallet_journals')->count();
        if ($count === 0) {
            return $this->result('warn', 'Wallet journal data', 'No journal entries yet - SeAT may not have synced wallet data');
        }

        $latest = DB::table('corporation_wallet_journals')->max('date');
        $latestAge = $latest ? Carbon::parse($latest)->diffForHumans() : 'unknown';
        $corps = DB::table('corporation_wallet_journals')->distinct('corporation_id')->count('corporation_id');

        return $this->result('pass', 'Wallet journal data', number_format($count) . " entries across {$corps} corp(s), latest {$latestAge}");
    }

    private function checkManagerCore(): array
    {
        if (! class_exists(\ManagerCore\Topics::class)) {
            return $this->result('info', 'Manager Core', 'Not installed - event-bus publishing and HR bridge capabilities skip cleanly');
        }
        return $this->result('pass', 'Manager Core', 'Installed - wallet events publish to the cross-plugin EventBus');
    }

    private function checkMiningManager(): array
    {
        if (! class_exists(\MiningManager\Models\TaxCode::class)) {
            return $this->result('info', 'Mining Manager', 'Not installed - tax/voluntary donation split is not available; both collapse into "donation" on read');
        }
        return $this->result('pass', 'Mining Manager', 'Installed - donation entries are split into tax vs voluntary via MM tax-code marker');
    }

    private function checkHrManager(): array
    {
        if (! class_exists('HrManager\HrManagerServiceProvider')) {
            return $this->result('info', 'HR Manager', 'Not installed - the four contribution.* and wallet.* PluginBridge capabilities are still registered, just unused');
        }
        return $this->result('pass', 'HR Manager', 'Installed - contribution capabilities are consumed for member assessment');
    }

    private function checkRecentJobActivity(): array
    {
        if (! Schema::hasTable('corpwalletmanager_recalc_logs')) {
            return $this->result('warn', 'Recent job activity', 'RecalcLog table missing');
        }

        $latest = RecalcLog::orderBy('started_at', 'desc')->first();
        if (! $latest) {
            return $this->result('warn', 'Recent job activity', 'No job runs recorded yet');
        }

        $age = Carbon::parse($latest->started_at)->diffInMinutes(now());
        $when = Carbon::parse($latest->started_at)->diffForHumans();

        if ($age > 90) {
            return $this->result('warn', 'Recent job activity', "Last run was {$when} ({$latest->job_type}) - queue worker may be stopped");
        }
        return $this->result('pass', 'Recent job activity', "Last run {$when} ({$latest->job_type}, status: {$latest->status})");
    }

    // ------------------------------------------------------------------
    // Tab 2: Master Test
    // ------------------------------------------------------------------

    private function runMasterTest(): array
    {
        $health = $this->runHealthChecks();
        $passed = count(array_filter($health, fn ($c) => $c['status'] === 'pass'));
        $warned = count(array_filter($health, fn ($c) => $c['status'] === 'warn'));
        $failed = count(array_filter($health, fn ($c) => $c['status'] === 'fail'));
        $info = count(array_filter($health, fn ($c) => $c['status'] === 'info'));

        return [
            'summary' => [
                'total'  => count($health),
                'passed' => $passed,
                'warned' => $warned,
                'failed' => $failed,
                'info'   => $info,
            ],
            'checks' => $health,
            'extras' => [
                $this->masterTestClassifySample(),
                $this->masterTestRecentReport(),
                $this->masterTestRecentAlertJournal(),
            ],
        ];
    }

    private function masterTestClassifySample(): array
    {
        try {
            $sample = DB::table('corporation_wallet_journals')
                ->orderBy('internal_id', 'desc')
                ->limit(1)
                ->first();
            if (! $sample) {
                return $this->result('info', 'Classifier dry-run', 'No journal entries to classify');
            }
            $result = app(ContributionService::class)->classify($sample);
            if ($result === null) {
                return $this->result('pass', 'Classifier dry-run', "Latest entry (id {$sample->id}, ref_type {$sample->ref_type}) classified as unattributable - this is normal for ref_types outside the bucket scheme");
            }
            return $this->result('pass', 'Classifier dry-run', "Latest entry classified as bucket '{$result['bucket']}' for character {$result['character_id']}");
        } catch (\Throwable $e) {
            return $this->result('fail', 'Classifier dry-run', 'Threw: ' . $e->getMessage());
        }
    }

    private function masterTestRecentReport(): array
    {
        try {
            $latest = DB::table('corpwalletmanager_reports')
                ->orderBy('created_at', 'desc')
                ->limit(1)
                ->first();
            if (! $latest) {
                return $this->result('info', 'Last report generated', 'No reports stored yet');
            }
            $when = Carbon::parse($latest->created_at)->diffForHumans();
            return $this->result('pass', 'Last report generated', "{$latest->report_type} report for corp {$latest->corporation_id} created {$when}");
        } catch (\Throwable $e) {
            return $this->result('fail', 'Last report generated', 'Query failed: ' . $e->getMessage());
        }
    }

    private function masterTestRecentAlertJournal(): array
    {
        try {
            $threshold = (float) Settings::getIntegerSetting('alert_large_transaction_threshold', 0);
            if ($threshold <= 0) {
                return $this->result('info', 'Large-transaction threshold preview', 'Threshold is 0 - feature disabled');
            }
            $count = DB::table('corporation_wallet_journals')
                ->where('date', '>=', now()->subDays(7))
                ->whereRaw('ABS(amount) >= ?', [$threshold])
                ->count();
            return $this->result('pass', 'Large-transaction threshold preview', "{$count} journal entries in the last 7 days would have crossed the current threshold (" . number_format($threshold, 0) . " ISK)");
        } catch (\Throwable $e) {
            return $this->result('fail', 'Large-transaction threshold preview', 'Query failed: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------
    // Tab 3: System Validation
    // ------------------------------------------------------------------

    private function runSystemValidation(): array
    {
        return [
            $this->validateScheduleExpressions(),
            $this->validatePermissionsAssigned(),
            $this->validateWebhookUrls(),
            $this->validateWatermarks(),
        ];
    }

    private function validateScheduleExpressions(): array
    {
        // Already covered by health check but the System Validation view
        // expects a separate row; reuse the same logic for consistency.
        return $this->checkScheduleRegistered();
    }

    private function validatePermissionsAssigned(): array
    {
        if (! Schema::hasTable('permissions')) {
            return $this->result('warn', 'Permission assignment', 'SeAT permissions table missing');
        }

        $assigned = DB::table('permissions')
            ->where('title', 'LIKE', 'corpwalletmanager.%')
            ->count();

        if ($assigned === 0) {
            return $this->result('warn', 'Permission assignment', 'No CWM permissions assigned to any role - operators will see 403 everywhere');
        }
        return $this->result('pass', 'Permission assignment', "{$assigned} CWM permission rows assigned across roles");
    }

    private function validateWebhookUrls(): array
    {
        if (! Schema::hasTable('corpwalletmanager_webhooks')) {
            return $this->result('warn', 'Webhook URL format', 'Table missing');
        }

        $bad = Webhook::query()
            ->where('webhook_url', 'NOT REGEXP', '^https://(discord|discordapp)\\.com/api/webhooks/')
            ->count();

        if ($bad > 0) {
            return $this->result('warn', 'Webhook URL format', "{$bad} webhook(s) have a URL that does not look like a Discord webhook endpoint");
        }
        return $this->result('pass', 'Webhook URL format', 'All webhook URLs match the Discord endpoint pattern');
    }

    private function validateWatermarks(): array
    {
        $alertWatermark = Settings::getSetting('alert_large_tx_watermark');
        $contribWatermark = Settings::getSetting('contributions_last_internal_id');

        $parts = [];
        $parts[] = $alertWatermark !== null && $alertWatermark !== ''
            ? "alert: {$alertWatermark}"
            : 'alert: unset (job has not run yet)';
        $parts[] = $contribWatermark !== null && $contribWatermark !== ''
            ? "contributions: {$contribWatermark}"
            : 'contributions: unset (job has not run yet)';

        $status = ($alertWatermark === null || $alertWatermark === '') || ($contribWatermark === null || $contribWatermark === '')
            ? 'warn'
            : 'pass';

        return $this->result($status, 'Job watermarks', implode(' / ', $parts));
    }

    // ------------------------------------------------------------------
    // Tab 4: Settings Health
    // ------------------------------------------------------------------

    private function runSettingsHealth(): array
    {
        if (! Schema::hasTable('corpwalletmanager_settings')) {
            return [
                'rows'   => [],
                'note'   => 'corpwalletmanager_settings table missing - run migrations',
            ];
        }

        $rows = DB::table('corpwalletmanager_settings')
            ->orderBy('key')
            ->get(['key', 'value', 'updated_at'])
            ->map(function ($r) {
                return [
                    'key'        => $r->key,
                    'value'      => $this->formatSettingValue($r->key, $r->value),
                    'raw'        => $r->value,
                    'updated_at' => $r->updated_at,
                    'category'   => $this->categoriseSetting($r->key),
                ];
            })
            ->all();

        return [
            'rows' => $rows,
            'note' => count($rows) . ' setting row(s) in corpwalletmanager_settings. Values are shown raw; sensitive values (webhook URLs) live in the webhooks table now and are hidden from this list.',
        ];
    }

    private function categoriseSetting(string $key): string
    {
        if (str_starts_with($key, 'discord_'))               return 'Legacy (pre-3.0 Discord)';
        if (str_starts_with($key, 'alert_'))                 return 'Alerts (v3.0.0)';
        if (str_starts_with($key, 'contributions_'))         return 'Contributions (v3.0.0)';
        if (str_starts_with($key, 'member_'))                return 'Member view';
        if (str_starts_with($key, 'goal_'))                  return 'Goals';
        if (in_array($key, ['refresh_interval', 'refresh_minutes', 'color_actual', 'color_predicted', 'decimals', 'selected_corporation_id'], true)) return 'Display';
        if (str_starts_with($key, 'use_precomputed_'))       return 'Performance';
        return 'Other';
    }

    private function formatSettingValue(string $key, ?string $value): string
    {
        if ($value === null) return 'null';
        if (str_contains($key, 'threshold') && is_numeric($value)) {
            return number_format((float) $value, 0) . ' ISK';
        }
        if (in_array($value, ['0', '1'], true)) {
            return $value === '1' ? 'true' : 'false';
        }
        return (string) $value;
    }

    // ------------------------------------------------------------------
    // Tab 5: Data Integrity
    // ------------------------------------------------------------------

    private function runDataIntegrity(): array
    {
        $blocks = [];

        foreach ([
            'corpwalletmanager_reports'                  => 'Reports',
            'corpwalletmanager_webhooks'                 => 'Webhooks',
            'corpwalletmanager_alert_state'              => 'Alert state',
            'corpwalletmanager_character_contributions'  => 'Character contributions',
            'corpwalletmanager_monthly_balances'         => 'Monthly balances',
            'corpwalletmanager_predictions'              => 'Predictions',
            'corpwalletmanager_division_balances'        => 'Division balances',
            'corpwalletmanager_division_predictions'     => 'Division predictions',
            'corpwalletmanager_recalc_logs'              => 'Job log',
            'corpwalletmanager_access_logs'              => 'Access logs',
        ] as $table => $label) {
            $blocks[] = $this->tableSummary($table, $label);
        }

        // Webhook delivery aggregate
        if (Schema::hasTable('corpwalletmanager_webhooks')) {
            $agg = DB::table('corpwalletmanager_webhooks')
                ->selectRaw('SUM(success_count) as ok, SUM(failure_count) as fail')
                ->first();
            $blocks[] = [
                'label'   => 'Webhook delivery totals',
                'detail'  => 'Successful deliveries: ' . number_format((int) ($agg->ok ?? 0))
                    . ' / Failed deliveries: ' . number_format((int) ($agg->fail ?? 0)),
            ];
        }

        // Currently-low corps
        if (Schema::hasTable('corpwalletmanager_alert_state')) {
            $low = AlertState::where('balance_is_low', true)->count();
            $blocks[] = [
                'label'  => 'Corporations currently in low-balance state',
                'detail' => $low . ' corp(s) currently below threshold',
            ];
        }

        return $blocks;
    }

    private function tableSummary(string $table, string $label): array
    {
        if (! Schema::hasTable($table)) {
            return ['label' => $label, 'detail' => 'Table missing'];
        }
        $count = DB::table($table)->count();
        $extra = '';
        if (Schema::hasColumn($table, 'created_at') && $count > 0) {
            $oldest = DB::table($table)->min('created_at');
            $newest = DB::table($table)->max('created_at');
            $extra = ' (oldest: ' . substr((string) $oldest, 0, 16) . ', newest: ' . substr((string) $newest, 0, 16) . ')';
        }
        return [
            'label'  => $label,
            'detail' => number_format($count) . ' row(s)' . $extra,
        ];
    }

    // ------------------------------------------------------------------
    // v3.0.0 Data Integrity extensions
    // ------------------------------------------------------------------

    /**
     * Schedule Status block (Data Integrity): one row per
     * `corpwalletmanager_report_schedules` entry, ordered by next_run_at.
     * Shows the corp name (resolved against corporation_infos), cadence,
     * next/last run timestamps, status icon, and enabled flag so operators
     * can see at a glance whether their configured Monday 03:30 weekly is
     * actually queued.
     *
     * Cached 60s via the controller `cached()` helper.
     */
    private function runScheduleStatus(): array
    {
        if (! Schema::hasTable('corpwalletmanager_report_schedules')) {
            return ['state' => 'missing', 'rows' => []];
        }

        $rows = DB::table('corpwalletmanager_report_schedules')
            ->orderByRaw('CASE WHEN next_run_at IS NULL THEN 0 ELSE 1 END, next_run_at ASC')
            ->get();

        if ($rows->isEmpty()) {
            return ['state' => 'empty', 'rows' => []];
        }

        // Resolve corp names once in a single lookup to avoid N+1.
        $corpIds = $rows->pluck('corporation_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $corpNames = [];
        if (! empty($corpIds) && Schema::hasTable('corporation_infos')) {
            $corpNames = DB::table('corporation_infos')
                ->whereIn('corporation_id', $corpIds)
                ->pluck('name', 'corporation_id')
                ->all();
        }

        $out = [];
        foreach ($rows as $r) {
            $enabled = (bool) $r->enabled;
            $lastStatus = $r->last_status ? strtolower((string) $r->last_status) : null;

            // Status icon convention:
            //   ok      = enabled AND (no last run yet OR last run succeeded)
            //   warn    = enabled AND last run failed
            //   off     = disabled
            if (! $enabled) {
                $status = 'off';
            } elseif ($lastStatus === 'failed') {
                $status = 'warn';
            } else {
                $status = 'ok';
            }

            $out[] = [
                'id'             => (int) $r->id,
                'corporation_id' => (int) $r->corporation_id,
                'corp_name'      => $corpNames[(int) $r->corporation_id] ?? null,
                'report_type'    => (string) $r->report_type,
                'enabled'        => $enabled,
                'next_run_at'    => $r->next_run_at,
                'last_run_at'    => $r->last_run_at,
                'last_status'    => $lastStatus,
                'last_error'     => $r->last_error,
                'status'         => $status,
            ];
        }

        return ['state' => 'found', 'rows' => $out];
    }

    /**
     * Personal Wallet Aggregator Status block (Data Integrity).
     * Three figures: total rows, max(updated_at), and the gap count of
     * distinct character_ids in `refresh_tokens` (deleted_at IS NULL and
     * character_id >= 90M) that have NO aggregate row for the current
     * month period. A non-zero gap means the hourly job is behind or has
     * not backfilled new characters yet.
     *
     * Cached 60s via the controller `cached()` helper.
     */
    private function runPersonalWalletAggregatorStatus(): array
    {
        if (! Schema::hasTable('corpwalletmanager_personal_wallet_aggregates')) {
            return ['state' => 'missing'];
        }

        $period = now('UTC')->format('Y-m');

        $totalRows = (int) DB::table('corpwalletmanager_personal_wallet_aggregates')->count();
        $maxUpdatedAt = DB::table('corpwalletmanager_personal_wallet_aggregates')->max('updated_at');

        // Gap detection: tokens that exist but have no aggregate row for the
        // current period. Character_id >= 90M is the standard player-character
        // floor (NPCs and corporations live below this). Soft-deleted tokens
        // (deleted_at NOT NULL) are excluded.
        $gapCount = 0;
        if (Schema::hasTable('refresh_tokens')) {
            try {
                $hasDeletedAt = Schema::hasColumn('refresh_tokens', 'deleted_at');

                $tokenQuery = DB::table('refresh_tokens')
                    ->where('character_id', '>=', 90000000);
                if ($hasDeletedAt) {
                    $tokenQuery->whereNull('deleted_at');
                }
                $tokenCharIds = $tokenQuery->distinct()->pluck('character_id')->map(fn ($id) => (int) $id)->all();

                if (! empty($tokenCharIds)) {
                    $aggregateCharIds = DB::table('corpwalletmanager_personal_wallet_aggregates')
                        ->where('period', $period)
                        ->whereIn('character_id', $tokenCharIds)
                        ->distinct()
                        ->pluck('character_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                    $gapCount = count(array_diff($tokenCharIds, $aggregateCharIds));
                }
            } catch (\Throwable $e) {
                // Gap detection is best-effort; surface the failure to the
                // operator so they know the figure is unreliable rather
                // than silently rendering 0.
                return [
                    'state'        => 'partial',
                    'total_rows'   => $totalRows,
                    'max_updated'  => $maxUpdatedAt,
                    'period'       => $period,
                    'gap_count'    => null,
                    'gap_error'    => $e->getMessage(),
                ];
            }
        }

        return [
            'state'       => 'found',
            'total_rows'  => $totalRows,
            'max_updated' => $maxUpdatedAt,
            'period'      => $period,
            'gap_count'   => $gapCount,
        ];
    }

    /**
     * Anomaly State block (Data Integrity). Lists any
     * `corpwalletmanager_anomaly_state` row that currently has a non-NULL
     * `contribution_drop_notified_at` timestamp (i.e. an open
     * contribution-drop alert latched against that member). Resolves corp
     * and character names so the operator sees "Mercurialis Inc. - John
     * Doe" rather than bare snowflakes.
     *
     * The anomaly_state schema only carries the contribution_drop latch
     * today (unusual_recipient uses a settings watermark, not a per-row
     * latch), so this block shows the contribution_drop fleet only.
     *
     * Cached 60s via the controller `cached()` helper.
     */
    private function runAnomalyStateSummary(): array
    {
        if (! Schema::hasTable('corpwalletmanager_anomaly_state')) {
            return ['state' => 'missing', 'rows' => []];
        }

        $rows = DB::table('corpwalletmanager_anomaly_state')
            ->whereNotNull('contribution_drop_notified_at')
            ->orderByDesc('contribution_drop_notified_at')
            ->limit(200)
            ->get();

        if ($rows->isEmpty()) {
            return ['state' => 'empty', 'rows' => []];
        }

        $corpIds = $rows->pluck('corporation_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $charIds = $rows->pluck('character_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $corpNames = [];
        if (! empty($corpIds) && Schema::hasTable('corporation_infos')) {
            $corpNames = DB::table('corporation_infos')
                ->whereIn('corporation_id', $corpIds)
                ->pluck('name', 'corporation_id')
                ->all();
        }

        $charNames = [];
        if (! empty($charIds) && Schema::hasTable('character_infos')) {
            $charNames = DB::table('character_infos')
                ->whereIn('character_id', $charIds)
                ->pluck('name', 'character_id')
                ->all();
        }

        $out = [];
        foreach ($rows as $r) {
            $corpId = (int) $r->corporation_id;
            $charId = (int) $r->character_id;
            $out[] = [
                'corporation_id'                => $corpId,
                'corp_name'                     => $corpNames[$corpId] ?? null,
                'character_id'                  => $charId,
                'character_name'                => $charNames[$charId] ?? null,
                'alert_kind'                    => 'contribution_drop',
                'latched'                       => (bool) $r->contribution_drop_latched,
                'contribution_drop_prior_avg'   => (float) $r->contribution_drop_prior_avg,
                'contribution_drop_recent_avg'  => (float) $r->contribution_drop_recent_avg,
                'contribution_drop_notified_at' => $r->contribution_drop_notified_at,
            ];
        }

        return ['state' => 'found', 'rows' => $out];
    }

    // ------------------------------------------------------------------
    // System Validation: Cross-plugin integration block
    // ------------------------------------------------------------------

    /**
     * Detection rows for every sibling plugin in the suite. All entries
     * return an `info` status: missing siblings are not a CWM failure,
     * they're optional integrations the operator may or may not have
     * installed. The Cross-plugin block on the System Validation tab
     * renders each row as a check.
     *
     * Cached 60s via the controller `cached()` helper.
     */
    private function runCrossPluginChecks(): array
    {
        $checks = [];

        // Manager Core - the hub for EventBus + PluginBridge + pricing.
        // We probe Topics first (the most stable public class), then try a
        // PluginBridge handshake to confirm the bridge is alive without
        // breaking when an older MC release does not expose getPlugins().
        if (class_exists(\ManagerCore\Topics::class)) {
            $detail = 'Detected - wallet.* events publish to the cross-plugin EventBus';
            if (class_exists(\ManagerCore\Services\PluginBridge::class)) {
                try {
                    $bridge = app(\ManagerCore\Services\PluginBridge::class);
                    if (method_exists($bridge, 'getPlugins')) {
                        $count = is_countable($bridge->getPlugins()) ? count($bridge->getPlugins()) : 0;
                        $detail .= ' / PluginBridge handshake OK (' . $count . ' plugin(s) registered)';
                    } else {
                        $detail .= ' / PluginBridge present (no getPlugins method on this MC release)';
                    }
                } catch (\Throwable $e) {
                    $detail .= ' / PluginBridge handshake failed: ' . $e->getMessage();
                }
            } else {
                $detail .= ' / PluginBridge class not found on this MC release';
            }
            $checks[] = $this->result('info', 'Manager Core', $detail);
        } else {
            $checks[] = $this->result('info', 'Manager Core', 'Not installed - EventBus publish and PluginBridge capabilities are no-ops');
        }

        // Mining Manager - source of mining_taxes.transaction_id for the
        // tax-vs-voluntary split on player_donation classification.
        if (class_exists(\MiningManager\Models\MiningTax::class)) {
            $checks[] = $this->result('info', 'Mining Manager', 'Detected - donation entries are split tax_payment vs donation_voluntary via MM transaction linkage');
        } else {
            $checks[] = $this->result('info', 'Mining Manager', 'Not installed - all donations land in donation_voluntary');
        }

        // HR Manager - consumer of CWM's PluginBridge capabilities (the
        // contribution.* and wallet.* surface). ClassifierService is the
        // stable public service that pulls those signals.
        if (class_exists(\HrManager\Services\ClassifierService::class)) {
            $checks[] = $this->result('info', 'HR Manager', 'Detected - contribution.* capabilities are consumed for member assessment');
        } else {
            $checks[] = $this->result('info', 'HR Manager', 'Not installed - contribution capabilities are still registered, just unused');
        }

        // Structure Manager - CWM does not consume SM today, but this
        // row signals that the rest of the suite is present.
        if (class_exists(\StructureManager\StructureManagerServiceProvider::class)) {
            $checks[] = $this->result('info', 'Structure Manager', 'Detected - present in suite (no direct CWM consumption today)');
        } else {
            $checks[] = $this->result('info', 'Structure Manager', 'Not installed');
        }

        // SeAT Broadcast (Discord Pings) - curated role provider via
        // discord_roles table. Same detection logic DiscordRoleResolver
        // uses so the row reflects what the role picker actually sees.
        if (Schema::hasTable('discord_roles')) {
            $checks[] = $this->result('info', 'SeAT Broadcast (Discord Pings)', 'Detected - curated role provider available for the role picker (discord_roles)');
        } else {
            $checks[] = $this->result('info', 'SeAT Broadcast (Discord Pings)', 'Not installed - discord_roles table absent');
        }

        // SeAT Connector - secondary synced role provider for the picker.
        if (Schema::hasTable('seat_connector_sets')) {
            $checks[] = $this->result('info', 'SeAT Connector', 'Detected - synced role provider available for the role picker (seat_connector_sets)');
        } else {
            $checks[] = $this->result('info', 'SeAT Connector', 'Not installed - seat_connector_sets table absent');
        }

        return $checks;
    }

    // ------------------------------------------------------------------
    // Schedule Trace (Tier 3)
    // ------------------------------------------------------------------

    /**
     * Walks a (corp, cadence) schedule entry through the dispatcher
     * pipeline. Operator-driven query - bypasses cache so a fresh look
     * after a config change shows the change.
     *
     * Output sections:
     *   - schedule row (or "no schedule configured")
     *   - dispatcher status (last failure + recent successful dispatches)
     *   - webhook delivery preview
     *   - computed next-run reporting window via dateWindowFor()
     */
    private function runScheduleTrace(int $corporationId, string $reportType): array
    {
        $cadence = strtolower($reportType);

        $result = [
            'state'          => 'found',
            'corporation_id' => $corporationId,
            'report_type'    => $cadence,
            'schedule'       => null,
            'dispatcher'     => null,
            'webhooks'       => [],
            'webhook_status' => null,
            'window'         => null,
        ];

        // Resolve corp name for the result header.
        $result['corp_name'] = null;
        if (Schema::hasTable('corporation_infos')) {
            $row = DB::table('corporation_infos')->where('corporation_id', $corporationId)->first();
            $result['corp_name'] = $row->name ?? null;
        }

        // Schedule row.
        if (Schema::hasTable('corpwalletmanager_report_schedules')) {
            $schedule = DB::table('corpwalletmanager_report_schedules')
                ->where('corporation_id', $corporationId)
                ->where('report_type', $cadence)
                ->first();
            $result['schedule'] = $schedule ? (array) $schedule : null;
        }

        // Dispatcher status: pull most recent failure for this combo from
        // Laravel's failed_jobs (if the table exists) and count successful
        // dispatches over the last 24h from corpwalletmanager_recalc_logs
        // (the GenerateReport job writes a log row on success).
        $dispatcher = [
            'last_failure'      => null,
            'success_count_24h' => 0,
            'status'            => 'unknown',
        ];

        if (Schema::hasTable('failed_jobs')) {
            try {
                $fail = DB::table('failed_jobs')
                    ->where('payload', 'LIKE', '%GenerateReport%')
                    ->where('payload', 'LIKE', '%"' . $corporationId . '"%')
                    ->orderByDesc('failed_at')
                    ->first();
                if ($fail) {
                    $dispatcher['last_failure'] = [
                        'failed_at' => $fail->failed_at,
                        'exception' => mb_substr((string) ($fail->exception ?? ''), 0, 400),
                    ];
                }
            } catch (\Throwable $e) {
                // Best-effort - if the LIKE query is rejected by the DB
                // (e.g. payload column type), leave last_failure null.
            }
        }

        if (Schema::hasTable('corpwalletmanager_recalc_logs')) {
            try {
                $dispatcher['success_count_24h'] = (int) DB::table('corpwalletmanager_recalc_logs')
                    ->where('corporation_id', $corporationId)
                    ->where('job_type', 'LIKE', '%generate_report%' . $cadence . '%')
                    ->where('status', 'completed')
                    ->where('started_at', '>=', now()->subDay())
                    ->count();
                if ($dispatcher['success_count_24h'] === 0) {
                    // Fallback: count any generate_report success in 24h for
                    // this corp regardless of the cadence string in job_type
                    // (older runs may use a different label).
                    $dispatcher['success_count_24h'] = (int) DB::table('corpwalletmanager_recalc_logs')
                        ->where('corporation_id', $corporationId)
                        ->where('job_type', 'LIKE', '%generate_report%')
                        ->where('status', 'completed')
                        ->where('started_at', '>=', now()->subDay())
                        ->count();
                }
            } catch (\Throwable $e) {
                // Best-effort - leave at 0.
            }
        }

        if ($dispatcher['last_failure'] !== null) {
            $dispatcher['status'] = 'fail';
        } elseif ($dispatcher['success_count_24h'] > 0) {
            $dispatcher['status'] = 'ok';
        } else {
            $dispatcher['status'] = 'warn';
        }

        $result['dispatcher'] = $dispatcher;

        // Webhook delivery preview - same selection logic WebhookService
        // would apply at real delivery time.
        if (Schema::hasTable('corpwalletmanager_webhooks')) {
            $category = Webhook::reportCategoryFor($cadence);
            $webhookFlag = match ($category) {
                'weekly'  => 'notify_weekly_report',
                'monthly' => 'notify_monthly_report',
                default   => 'notify_on_demand_report',
            };

            $webhooks = Webhook::query()
                ->enabled()
                ->forCorporation($corporationId)
                ->where($webhookFlag, true)
                ->get(['id', 'name', 'corporation_id', 'discord_role_id'])
                ->all();

            $result['webhooks']       = $webhooks;
            $result['webhook_flag']   = $webhookFlag;
            $result['webhook_status'] = empty($webhooks) ? 'none' : 'ok';
        } else {
            $result['webhook_status'] = 'missing_table';
        }

        // Computed next-run window. Mirrors DispatchScheduledReportsCommand's
        // dateWindowFor verbatim, against the NEXT firing time of the
        // schedule (or now+5min as a fallback when no schedule is configured
        // so the operator can still see what window would be requested).
        $next = null;
        if ($result['schedule'] !== null) {
            try {
                $model = ReportSchedule::find($result['schedule']['id']);
                if ($model) {
                    $next = $model->computeNextRunAt();
                }
            } catch (\Throwable $e) {
                $next = null;
            }
        }
        if ($next === null) {
            $next = now('UTC')->copy()->addMinutes(5);
        }
        [$from, $to] = $this->traceWindowFor($cadence, $next);
        $result['window'] = [
            'next_firing' => $next->toIso8601String(),
            'from'        => $from->toIso8601String(),
            'to'          => $to->toIso8601String(),
            'human'       => $this->humaniseCadenceWindow($cadence, $from, $to),
        ];

        return $result;
    }

    /**
     * Replicates DispatchScheduledReportsCommand::dateWindowFor() so the
     * Schedule Trace tab shows the exact same [from, to] window the
     * dispatcher would request. Kept in lockstep with the command - if
     * the command's logic changes, mirror it here.
     */
    private function traceWindowFor(string $cadence, Carbon $reference): array
    {
        switch (strtolower($cadence)) {
            case 'daily':
                $from = $reference->copy()->subDay()->startOfDay();
                $to   = $reference->copy()->subDay()->endOfDay();
                return [$from, $to];

            case 'weekly':
                $startOfThisWeek = $reference->copy()->startOfWeek(Carbon::MONDAY);
                $from = $startOfThisWeek->copy()->subWeek();
                $to   = $startOfThisWeek->copy()->subSecond();
                return [$from, $to];

            case 'monthly':
                $from = $reference->copy()->subMonthNoOverflow()->startOfMonth();
                $to   = $reference->copy()->subMonthNoOverflow()->endOfMonth();
                return [$from, $to];

            case 'quarterly':
                $startOfThisQuarter = $reference->copy()->firstOfQuarter()->startOfDay();
                $to   = $startOfThisQuarter->copy()->subSecond();
                $from = $to->copy()->firstOfQuarter()->startOfDay();
                return [$from, $to];

            case 'annual':
                $year = $reference->year - 1;
                $from = Carbon::create($year, 1, 1, 0, 0, 0, 'UTC');
                $to   = Carbon::create($year, 12, 31, 23, 59, 59, 'UTC');
                return [$from, $to];
        }

        return [$reference->copy()->subDay(), $reference->copy()];
    }

    /**
     * Short human description of the window for the trace result section.
     */
    private function humaniseCadenceWindow(string $cadence, Carbon $from, Carbon $to): string
    {
        switch (strtolower($cadence)) {
            case 'daily':
                return $cadence . ' report next firing covers ' . $from->toDateString();
            case 'weekly':
                return $cadence . ' report next firing covers ' . $from->toDateString() . ' to ' . $to->toDateString();
            case 'monthly':
                return $cadence . ' report next firing covers ' . $from->toDateString() . ' to ' . $to->toDateString();
            case 'quarterly':
                return $cadence . ' report next firing covers ' . $from->toDateString() . ' to ' . $to->toDateString();
            case 'annual':
                return $cadence . ' report next firing covers ' . $from->year;
        }
        return $from->toDateString() . ' to ' . $to->toDateString();
    }

    // ------------------------------------------------------------------
    // Tab 6: Wallet Trace
    // ------------------------------------------------------------------

    private function runWalletTrace(int $journalId, int $internalId): array
    {
        if ($journalId <= 0 && $internalId <= 0) {
            return ['state' => 'idle'];
        }

        $row = $journalId > 0
            ? DB::table('corporation_wallet_journals')->where('id', $journalId)->first()
            : DB::table('corporation_wallet_journals')->where('internal_id', $internalId)->first();

        if (! $row) {
            return ['state' => 'not_found'];
        }

        $service = app(ContributionService::class);
        $isInternalTransfer = \CorpWalletManager\Support\JournalFilters::isInternalTransfer($row);
        $classification = $service->classify($row);
        $taxCode = $service->extractTaxCode($row->description ?? null);

        $largeThreshold = (float) Settings::getIntegerSetting('alert_large_transaction_threshold', 0);
        // Internal transfers are skipped by the large-tx scan, so reflect
        // that here instead of falsely claiming "this would alert".
        $wouldLargeAlert = $largeThreshold > 0
            && abs((float) $row->amount) >= $largeThreshold
            && ! $isInternalTransfer;

        $period = substr((string) $row->date, 0, 7);

        $matchingWebhooks = [];
        if (Schema::hasTable('corpwalletmanager_webhooks')) {
            // Webhooks that would receive a large-transfer alert for this corp
            $matchingWebhooks = Webhook::query()
                ->enabled()
                ->forCorporation((int) $row->corporation_id)
                ->where('notify_large_transfer', true)
                ->get(['id', 'name', 'corporation_id', 'discord_role_id'])
                ->all();
        }

        $corpBalanceLow = null;
        $lowThreshold = (float) Settings::getIntegerSetting('alert_low_balance_threshold', 0);
        if ($lowThreshold > 0 && Schema::hasTable('corporation_wallet_balances')) {
            $balance = (float) DB::table('corporation_wallet_balances')
                ->where('corporation_id', $row->corporation_id)
                ->sum('balance');
            $corpBalanceLow = [
                'threshold' => $lowThreshold,
                'balance'   => $balance,
                'is_low'    => $balance < $lowThreshold,
            ];
        }

        $publishedEvents = [];
        if ($wouldLargeAlert) {
            $publishedEvents[] = [
                'topic'        => 'wallet.transaction_detected',
                'guarded_by'   => 'class_exists(\\ManagerCore\\Topics::class)',
                'mc_installed' => class_exists(\ManagerCore\Topics::class),
            ];
        }
        if ($corpBalanceLow && $corpBalanceLow['is_low']) {
            $publishedEvents[] = [
                'topic'        => 'wallet.balance_low',
                'guarded_by'   => 'class_exists(\\ManagerCore\\Topics::class)',
                'mc_installed' => class_exists(\ManagerCore\Topics::class),
            ];
        }

        // Resolve party / corp ids on this row to names via the
        // layered EntityNameResolver (character_infos -> corp_infos ->
        // alliance_infos -> universe_names -> ESI). Lets operators
        // tracing a journal entry see "Mercurialis Inc. [98692850]"
        // instead of a bare snowflake when investigating attribution.
        $partyIds = array_values(array_filter([
            (int) ($row->corporation_id ?? 0),
            (int) ($row->first_party_id ?? 0),
            (int) ($row->second_party_id ?? 0),
            $classification ? (int) $classification['character_id'] : 0,
        ]));
        $partyNames = empty($partyIds)
            ? []
            : app(\CorpWalletManager\Services\EntityNameResolver::class)->resolve($partyIds, true);

        return [
            'state'                 => 'found',
            'row'                   => (array) $row,
            'is_internal_transfer'  => $isInternalTransfer,
            'classification'        => $classification,
            'tax_code_match'        => $taxCode,
            'mm_installed'          => $service->isMmInstalled(),
            'period'                => $period,
            'large_threshold'       => $largeThreshold,
            'would_large_alert'     => $wouldLargeAlert,
            'corp_balance_low'      => $corpBalanceLow,
            'matching_webhooks'     => $matchingWebhooks,
            'published_events'      => $publishedEvents,
            'party_names'           => $partyNames,
        ];
    }

    // ------------------------------------------------------------------
    // Tab 7: Donation Audit
    // ------------------------------------------------------------------

    /**
     * Batch view of every player_donation journal entry in a period,
     * with the classifier's bucket assignment + MM tax-code match
     * shown side-by-side per row. Complements Wallet Trace (which
     * walks a single entry) — this lets operators verify a whole
     * month at once.
     *
     * When MM is installed, donations split into tax_payment vs
     * donation_voluntary based on whether the description contains a
     * recognised tax-code marker. A row that lands in
     * donation_voluntary while the description LOOKS like it should
     * be a tax payment (mentions "tax", "mining", etc.) is the
     * typical false-classify case — the audit highlights these so
     * the operator can spot a member who's tagging incorrectly or a
     * MM tax-code config drift.
     */
    private function runDonationAudit(int $corporationId, string $period): array
    {
        if ($corporationId <= 0) {
            return ['state' => 'no_corp'];
        }
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            return ['state' => 'bad_period'];
        }

        $monthStart = $period . '-01';
        $monthEnd   = date('Y-m-t 23:59:59', strtotime($monthStart));

        $rows = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->where('ref_type', 'player_donation')
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->orderByDesc('amount')
            ->limit(500)
            ->get([
                'id', 'internal_id', 'corporation_id', 'date', 'ref_type',
                'amount', 'first_party_id', 'second_party_id',
                'context_id', 'context_id_type', 'description', 'reason',
            ]);

        if ($rows->isEmpty()) {
            return ['state' => 'no_rows', 'period' => $period, 'corporation_id' => $corporationId];
        }

        $service = app(ContributionService::class);
        $mmInstalled = $service->isMmInstalled();

        // Batch-resolve donor names once for the whole table.
        $donorIds = $rows->pluck('first_party_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $donorNames = empty($donorIds)
            ? []
            : app(\CorpWalletManager\Services\EntityNameResolver::class)->resolve($donorIds, true);

        // Bulk-warm the MM-link cache for every row's journal id so the
        // classify() call inside the per-row loop is free (no per-row
        // DB hits for the link check).
        if ($mmInstalled) {
            $service->prewarmMmTaxCache(
                $rows->pluck('id')->map(fn ($id) => (int) $id)->all()
            );
        }

        // Heuristics for the "looks like tax but classified as voluntary"
        // flag. Case-insensitive substring match on description. Only
        // applied to rows that are NOT already MM-linked — once MM has
        // confirmed the donation, the description text is moot.
        $suspiciousKeywords = ['tax', 'mining tax', 'mining', 'tax payment'];

        $entries = [];
        $totals = [
            'tax_payment'        => 0.0,
            'donation_voluntary' => 0.0,
            'unattributed'       => 0.0,
            'count_tax'          => 0,
            'count_voluntary'    => 0,
            'count_unattributed' => 0,
            'count_suspect'      => 0,
            'count_mm_linked'    => 0,
        ];

        foreach ($rows as $row) {
            $classification = $service->classify($row);
            $taxCode = $service->extractTaxCode($row->description ?? null);
            $mmLinked = $mmInstalled && $service->isMmTaxPayment((int) $row->id);
            $bucket = $classification['bucket'] ?? null;
            $amount = (float) $row->amount;
            $donorId = $row->first_party_id !== null ? (int) $row->first_party_id : null;
            $donor = $donorId !== null ? ($donorNames[$donorId] ?? ['name' => 'Unknown', 'type' => 'unknown']) : null;

            // Suspect when MM is installed, the row is NOT MM-linked,
            // classifier sent it to voluntary, no description tax-code
            // matched, and EITHER the description OR the user-typed
            // reason hints at tax — operator review candidate. The
            // reason field is the more likely place for an informal
            // "Mining tax for May" tag since that's where CCP exposes
            // the donor's own message. Once MM links a row both are
            // moot.
            $suspect = false;
            if ($mmInstalled && ! $mmLinked && $bucket === 'donation_voluntary' && $taxCode === null) {
                $haystack = strtolower(
                    ((string) ($row->description ?? '')) . ' ' . ((string) ($row->reason ?? ''))
                );
                foreach ($suspiciousKeywords as $kw) {
                    if (strpos($haystack, $kw) !== false) {
                        $suspect = true;
                        break;
                    }
                }
            }

            if ($bucket === 'tax_payment') {
                $totals['tax_payment'] += abs($amount);
                $totals['count_tax']++;
            } elseif ($bucket === 'donation_voluntary') {
                $totals['donation_voluntary'] += abs($amount);
                $totals['count_voluntary']++;
            } else {
                $totals['unattributed'] += abs($amount);
                $totals['count_unattributed']++;
            }
            if ($suspect) {
                $totals['count_suspect']++;
            }
            if ($mmLinked) {
                $totals['count_mm_linked']++;
            }

            $entries[] = [
                'journal_id'   => (int) $row->id,
                'internal_id'  => (int) $row->internal_id,
                'date'         => (string) $row->date,
                'amount'       => $amount,
                'donor_id'     => $donorId,
                'donor_name'   => $donor['name'] ?? null,
                'donor_type'   => $donor['type'] ?? null,
                'description'  => (string) ($row->description ?? ''),
                'reason'       => (string) ($row->reason ?? ''),
                'tax_code'     => $taxCode,
                'mm_linked'    => $mmLinked,
                'bucket'       => $bucket,
                'character_id' => $classification['character_id'] ?? null,
                'suspect'      => $suspect,
            ];
        }

        return [
            'state'          => 'found',
            'period'         => $period,
            'corporation_id' => $corporationId,
            'mm_installed'   => $mmInstalled,
            'totals'         => $totals,
            'entries'        => $entries,
        ];
    }

    // ------------------------------------------------------------------
    // Tab 8: Notification Testing
    // ------------------------------------------------------------------

    /**
     * Categories the Notification Testing tab can fire. Mirrors the five
     * webhook subscription flags on the Webhook model. Each entry carries
     * a human label, the dispatch verb (report vs alert), the type string
     * WebhookService expects, and the column WebhookService inspects to
     * decide which webhooks subscribe.
     */
    private const TEST_CATEGORIES = [
        'weekly_report' => [
            'label'      => 'Weekly Report',
            'kind'       => 'report',
            'type'       => 'weekly',
            'flag'       => 'notify_weekly_report',
            'embed_title' => 'TEST NOTIFICATION - Weekly Report',
            'embed_body'  => 'This is a test of the Corp Wallet Manager weekly report delivery channel. No real report has been generated; this message was triggered manually from the Diagnostic page.',
            'color'      => 3447003,
        ],
        'monthly_report' => [
            'label'      => 'Monthly Report',
            'kind'       => 'report',
            'type'       => 'monthly',
            'flag'       => 'notify_monthly_report',
            'embed_title' => 'TEST NOTIFICATION - Monthly Report',
            'embed_body'  => 'This is a test of the Corp Wallet Manager monthly report delivery channel. No real report has been generated; this message was triggered manually from the Diagnostic page.',
            'color'      => 3447003,
        ],
        'on_demand_report' => [
            'label'      => 'On-Demand Report',
            'kind'       => 'report',
            'type'       => 'on_demand',
            'flag'       => 'notify_on_demand_report',
            'embed_title' => 'TEST NOTIFICATION - On-Demand Report',
            'embed_body'  => 'This is a test of the Corp Wallet Manager on-demand report delivery channel. No real report has been generated; this message was triggered manually from the Diagnostic page.',
            'color'      => 3447003,
        ],
        'large_transfer' => [
            'label'      => 'Large Transfer Alert',
            'kind'       => 'alert',
            'type'       => 'large_transfer',
            'flag'       => 'notify_large_transfer',
            'embed_title' => 'TEST NOTIFICATION - Large Transfer Alert',
            'embed_body'  => 'This is a test of the Corp Wallet Manager large-transfer alert channel. No real transaction has crossed the threshold; this message was triggered manually from the Diagnostic page.',
            'color'      => 15158332,
        ],
        'low_balance' => [
            'label'      => 'Low Balance Alert',
            'kind'       => 'alert',
            'type'       => 'low_balance',
            'flag'       => 'notify_low_balance',
            'embed_title' => 'TEST NOTIFICATION - Low Balance Alert',
            'embed_body'  => 'This is a test of the Corp Wallet Manager low-balance alert channel. No real balance has dropped below threshold; this message was triggered manually from the Diagnostic page.',
            'color'      => 15158332,
        ],
        'contribution_drop' => [
            'label'      => 'Contribution Drop Alert',
            'kind'       => 'alert',
            'type'       => 'contribution_drop',
            'flag'       => 'notify_contribution_drop',
            'embed_title' => 'TEST NOTIFICATION - Contribution Drop Alert',
            'embed_body'  => 'This is a test of the Corp Wallet Manager contribution-drop alert channel. No real member contribution has actually collapsed; this message was triggered manually from the Diagnostic page.',
            'color'      => 16753920,
        ],
        'unusual_recipient' => [
            'label'      => 'Unusual Recipient Alert',
            'kind'       => 'alert',
            'type'       => 'unusual_recipient',
            'flag'       => 'notify_unusual_recipient',
            'embed_title' => 'TEST NOTIFICATION - Unusual Recipient Alert',
            'embed_body'  => 'This is a test of the Corp Wallet Manager unusual-recipient alert channel. No real first-time payout has been detected; this message was triggered manually from the Diagnostic page.',
            'color'      => 15158332,
        ],
    ];

    /**
     * Fire a test notification to every webhook subscribed to the chosen
     * category (and corp, if scoped). Records per-webhook outcomes in the
     * session so the Notification Testing tab can render them on the next
     * page load.
     *
     * Auth: corpwalletmanager.settings via the route middleware.
     */
    public function fireTestNotification(Request $request)
    {
        $validated = $request->validate([
            'category'       => 'required|string|in:' . implode(',', array_keys(self::TEST_CATEGORIES)),
            'corporation_id' => 'nullable|integer|min:0',
        ]);

        $categoryKey = $validated['category'];
        $corpId      = ! empty($validated['corporation_id']) ? (int) $validated['corporation_id'] : null;
        $def         = self::TEST_CATEGORIES[$categoryKey];

        // Resolve the candidate webhook set the same way WebhookService
        // would at real delivery time, then fan-out one-at-a-time so we
        // can record per-webhook outcomes (the normal dispatchReport /
        // dispatchAlert path only returns aggregate sent/failed counts).
        $service = app(WebhookService::class);
        $webhooks = $def['kind'] === 'report'
            ? $service->webhooksForReport($corpId, $def['type'])
            : $service->webhooksForAlert($corpId, $def['type']);

        $embed = [
            'title'       => $def['embed_title'],
            'description' => $def['embed_body']
                . ($corpId
                    ? "\n\nTarget: Corp #{$corpId}"
                    : "\n\nTarget: all corps subscribed to this category"),
            'color'       => $def['color'],
            'timestamp'   => now()->toIso8601String(),
            'footer'      => ['text' => 'Corp Wallet Manager - Diagnostic test'],
        ];

        $roleLookupMap = DiscordRoleResolver::roleLookupMap();
        $outcomes = [];

        if ($webhooks->isEmpty()) {
            return redirect()
                ->route('corpwalletmanager.diagnostic', ['diag_tab' => 'notification-testing'])
                ->with('cwm_notification_test', [
                    'state'       => 'no_subscribers',
                    'category'    => $categoryKey,
                    'category_label' => $def['label'],
                    'corporation_id' => $corpId,
                    'outcomes'    => [],
                    'fired_at'    => now()->toDateTimeString(),
                ]);
        }

        foreach ($webhooks as $wh) {
            $outcome = [
                'webhook_id'     => (int) $wh->id,
                'webhook_name'   => (string) $wh->name,
                'corporation_id' => $wh->corporation_id ? (int) $wh->corporation_id : null,
                'role_desc'      => DiscordRoleResolver::describeRoleMention($wh->discord_role_id, $roleLookupMap),
                'status'         => 'pending',
                'message'        => '',
            ];

            try {
                // sendOneEmbed: single-attempt path that uses our
                // category-specific embed (with the TEST NOTIFICATION
                // marker in the title so recipients on Discord know it
                // is not real). Throws on failure with the HTTP status
                // / exception message so the per-webhook outcome row
                // can show the precise reason.
                $service->sendOneEmbed($wh, $embed);
                $outcome['status'] = 'success';
                $outcome['message'] = 'Discord acknowledged delivery';
            } catch (\Throwable $e) {
                $outcome['status'] = 'failure';
                $outcome['message'] = $e->getMessage();
            }
            $outcomes[] = $outcome;
        }

        return redirect()
            ->route('corpwalletmanager.diagnostic', ['diag_tab' => 'notification-testing'])
            ->with('cwm_notification_test', [
                'state'          => 'fired',
                'category'       => $categoryKey,
                'category_label' => $def['label'],
                'corporation_id' => $corpId,
                'outcomes'       => $outcomes,
                'fired_at'       => now()->toDateTimeString(),
            ]);
    }

    // ------------------------------------------------------------------
    // Output helper
    // ------------------------------------------------------------------

    private function result(string $status, string $label, string $detail): array
    {
        return ['status' => $status, 'label' => $label, 'detail' => $detail];
    }
}
