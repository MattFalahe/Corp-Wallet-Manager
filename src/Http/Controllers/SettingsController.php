<?php
namespace Seat\CorpWalletManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
                'color_actual' => config('corpwalletmanager.color_actual', '#4cafef'),
                'color_predicted' => config('corpwalletmanager.color_predicted', '#ef4444'),
                'decimals' => config('corpwalletmanager.decimals', 2),
                'use_precomputed_predictions' => config('corpwalletmanager.use_precomputed_predictions', true),
                'use_precomputed_monthly_balances' => config('corpwalletmanager.use_precomputed_monthly_balances', true),
            ];
            
            $settings = array_merge($defaultSettings, $settings);
            
            // Get recent job logs for display
            $recentLogs = RecalcLog::with('corporation')
                ->orderBy('started_at', 'desc')
                ->limit(10)
                ->get();
            
            return view('corpwalletmanager::settings', compact('settings', 'recentLogs'));
            
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
                'refresh_interval' => 'required|integer|min:5000|max:300000',
                'color_actual' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'color_predicted' => 'required|string|regex:/^#[0-9A-Fa-f]{6}$/',
                'decimals' => 'required|integer|min:0|max:8',
                'use_precomputed_predictions' => 'boolean',
                'use_precomputed_monthly_balances' => 'boolean',
            ]);

            $settingsToUpdate = [
                'refresh_interval',
                'color_actual',
                'color_predicted',
                'decimals',
                'use_precomputed_predictions',
                'use_precomputed_monthly_balances',
            ];

            foreach ($settingsToUpdate as $key) {
                $value = $request->input($key);
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
    public function triggerBackfill()
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
            
            BackfillWalletData::dispatch();
            
            Log::info('CorpWalletManager wallet backfill job dispatched manually');
            
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
    public function triggerPrediction()
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
            
            ComputeDailyPrediction::dispatch();
            
            Log::info('CorpWalletManager prediction computation job dispatched manually');
            
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
    public function triggerDivisionBackfill()
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
            
            BackfillDivisionWalletData::dispatch();
            
            Log::info('CorpWalletManager division backfill job dispatched manually');
            
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
    public function triggerDivisionPrediction()
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
            
            ComputeDivisionDailyPrediction::dispatch();
            
            Log::info('CorpWalletManager division prediction job dispatched manually');
            
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
}
