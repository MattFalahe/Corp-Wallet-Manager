<?php
namespace Seat\CorpWalletManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
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
    }

    /**
     * Update settings
     */
    public function update(Request $request)
    {
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

        return redirect()
            ->route('corpwalletmanager.settings')
            ->with('success', 'Settings updated successfully!');
    }

    /**
     * Reset settings to defaults
     */
    public function reset()
    {
        Settings::truncate();
        
        return redirect()
            ->route('corpwalletmanager.settings')
            ->with('success', 'Settings reset to defaults!');
    }

    /**
     * Trigger manual wallet backfill
     */
    public function triggerBackfill()
    {
        BackfillWalletData::dispatch();
        
        return redirect()
            ->route('corpwalletmanager.settings')
            ->with('success', 'Wallet backfill job dispatched!');
    }

    /**
     * Trigger prediction computation
     */
    public function triggerPrediction()
    {
        ComputeDailyPrediction::dispatch();
        
        return redirect()
            ->route('corpwalletmanager.settings')
            ->with('success', 'Prediction computation job dispatched!');
    }
    
    /**
     * Trigger division backfill
     */
    public function triggerDivisionBackfill()
    {
        BackfillDivisionWalletData::dispatch();
        
        return redirect()
            ->route('corpwalletmanager.settings')
            ->with('success', 'Division backfill job dispatched!');
    }
    
    /**
     * Trigger division prediction computation
     */
    public function triggerDivisionPrediction()
    {
        ComputeDivisionDailyPrediction::dispatch();
        
        return redirect()
            ->route('corpwalletmanager.settings')
            ->with('success', 'Division prediction job dispatched!');
    }
    
    /**
     * Get job status via AJAX
     */
    public function jobStatus()
    {
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
    }
}
