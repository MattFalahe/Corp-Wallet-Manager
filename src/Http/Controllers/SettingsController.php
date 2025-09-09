<?php
namespace Seat\CorpWalletManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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
                'refresh_minutes' => '5', // New default
                'color_actual' => config('corpwalletmanager.color_actual', '#4cafef'),
                'color_predicted' => config('corpwalletmanager.color_predicted', '#ef4444'),
                'decimals' => config('corpwalletmanager.decimals', 2),
                'use_precomputed_predictions' => config('corpwalletmanager.use_precomputed_predictions', true),
                'use_precomputed_monthly_balances' => config('corpwalletmanager.use_precomputed_monthly_balances', true),
                'selected_corporation_id' => null, // New setting
            ];
            
            $settings = array_merge($defaultSettings, $settings);
            
            // Get available corporations from the database
            $corporations = [];
            try {
                // Try to get corporations from SeAT's corporation_infos table
                if (DB::getSchemaBuilder()->hasTable('corporation_infos')) {
                    $corporations = DB::table('corporation_infos')
                        ->select('corporation_id', 'name')
                        ->orderBy('name')
                        ->get();
                } else {
                    // Fallback: get from wallet data
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
            $request->validate([
                'refresh_minutes' => 'required|in:0,5,15,30,60',
                'color_actual' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'color_predicted' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'decimals' => 'required|integer|min:0|max:8',
                'use_precomputed_predictions' => 'boolean',
                'use_precomputed_monthly_balances' => 'boolean',
                'selected_corporation_id' => 'nullable|numeric',
            ]);

            // Convert refresh_minutes to milliseconds for refresh_interval
            $refreshMinutes = $request->input('refresh_minutes');
            $refreshInterval = $refreshMinutes == '0' ? 0 : ($refreshMinutes * 60 * 1000);

            $settingsToUpdate = [
                'refresh_interval' => $refreshInterval,
                'refresh_minutes' => $refreshMinutes,
                'color_actual' => $request->input('color_actual'),
                'color_predicted' => $request->input('color_predicted'),
                'decimals' => $request->input('decimals'),
                'use_precomputed_predictions' => $request->input('use_precomputed_predictions', false) ? '1' : '0',
                'use_precomputed_monthly_balances' => $request->input('use_precomputed_monthly_balances', false) ? '1' : '0',
                'selected_corporation_id' => $request->input('selected_corporation_id'),
            ];

            foreach ($settingsToUpdate as $key => $value) {
                if ($value !== null) {
                    Settings::setSetting($key, is_bool($value) ? ($value ? '1' : '0') : $value);
                }
            }

            Log::info('CorpWalletManager settings updated successfully');

            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('success', 'Settings updated successfully!');
                
        } catch (\Exception $e) {
            Log::error('CorpWalletManager settings update error: ' . $e->getMessage());
            return redirect()
                ->route('corpwalletmanager.settings')
                ->with('error', 'Failed to update settings. Please check logs.');
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
}
