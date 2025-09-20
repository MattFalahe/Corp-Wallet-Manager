<?php
namespace Seat\CorpWalletManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema; 
use Carbon\Carbon; 
use Seat\CorpWalletManager\Models\Settings;
use Seat\CorpWalletManager\Models\RecalcLog;
use Seat\CorpWalletManager\Jobs\BackfillWalletData;
use Seat\CorpWalletManager\Jobs\ComputeDailyPrediction;
use Seat\CorpWalletManager\Jobs\BackfillDivisionWalletData;
use Seat\CorpWalletManager\Jobs\ComputeDivisionDailyPrediction;

class SettingsController extends Controller
{
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
                'goal_savings_target' => '1000000000',
                'goal_activity_target' => '1000',
                'goal_growth_target' => '10',
            ];
            
            // Merge defaults with saved settings
            $settings = array_merge($defaultSettings, $settings);
            
            // Convert string '1'/'0' to boolean for checkboxes
            foreach (['use_precomputed_predictions', 'use_precomputed_monthly_balances', 
                      'member_show_health', 'member_show_trends', 'member_show_activity',
                      'member_show_goals', 'member_show_milestones', 'member_show_balance',
                      'member_show_performance'] as $key) {
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
            
            return view('corpwalletmanager::settings', compact('settings', 'recentLogs', 'corporations'));
            
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
            // Log ALL request data
            Log::info('=== SETTINGS UPDATE DEBUG ===');
            Log::info('All request data:', $request->all());
            Log::info('Request method: ' . $request->method());
            
            // Check specific checkbox values
            Log::info('Checkbox checks:');
            Log::info('member_show_balance input: ' . json_encode($request->input('member_show_balance')));
            Log::info('member_show_balance has: ' . ($request->has('member_show_balance') ? 'YES' : 'NO'));
            Log::info('member_show_balance filled: ' . ($request->filled('member_show_balance') ? 'YES' : 'NO'));
            
            $request->validate([
                'refresh_minutes' => 'required|in:0,5,15,30,60',
                'color_actual' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'color_predicted' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'decimals' => 'required|integer|min:0|max:8',
                'selected_corporation_id' => 'nullable|numeric',
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
            ];
            
            // Handle checkboxes
            foreach ($checkboxFields as $field) {
                // If the field exists in the request with any value, it's checked
                // If it doesn't exist at all, it's unchecked
                $value = $request->has($field) ? '1' : '0';
                $settingsToUpdate[$field] = $value;
                
                Log::info("Checkbox {$field}: has=" . ($request->has($field) ? 'YES' : 'NO') . ", value will be: {$value}");
            }
    
            Log::info('Final settings to update:', $settingsToUpdate);
    
            // Update each setting
            foreach ($settingsToUpdate as $key => $value) {
                $setting = Settings::where('key', $key)->first();
                
                if ($setting) {
                    $oldValue = $setting->value;
                    $setting->value = (string)$value;
                    $setting->updated_at = now();
                    $setting->save();
                    
                    Log::info("Updated {$key}: '{$oldValue}' -> '{$value}'");
                } else {
                    Settings::create([
                        'key' => $key,
                        'value' => (string)$value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    Log::info("Created {$key}: '{$value}'");
                }
            }
    
            Log::info('=== SETTINGS UPDATE COMPLETE ===');
    
            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('success', 'Settings updated successfully! Check logs for debug info.');
                
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
            
            // Use selected corporation if configured
            $corporationId = Settings::getSetting('selected_corporation_id');
            
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
            
            // Use selected corporation if configured
            $corporationId = Settings::getSetting('selected_corporation_id');
            
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
            
            // Use selected corporation if configured
            $corporationId = Settings::getSetting('selected_corporation_id');
            
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
            
            // Use selected corporation if configured
            $corporationId = Settings::getSetting('selected_corporation_id');
            
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

    public function triggerInternalBackfill(Request $request)
    {
        try {
            $corporationId = $request->input('corporation_id') ?? Settings::getSetting('selected_corporation_id');
            
            \Artisan::call('corpwalletmanager:backfill-internal', [
                '--corporation' => $corporationId ?? 'all',
                '--months' => 1
            ]);
            
            return response()->json(['success' => true, 'message' => 'Internal transfer detection started']);
            
        } catch (\Exception $e) {
            Log::error('Internal transfer backfill trigger failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to start detection'], 500);
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
     * Get selected corporation settings via AJAX
     */
    public function getSelectedCorporation()
    {
        try {
            $corporationId = Settings::getSetting('selected_corporation_id');
            
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
}
