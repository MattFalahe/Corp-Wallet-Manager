<?php
namespace Seat\CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Seat\CorpWalletManager\Models\MonthlyBalance;
use Seat\CorpWalletManager\Models\DivisionBalance;
use Seat\CorpWalletManager\Models\RecalcLog;
use Seat\CorpWalletManager\Models\Settings;

class UpdateHourlyWalletData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;

    public function handle()
    {
        $logEntry = null;
        
        try {
            $logEntry = RecalcLog::create([
                'job_type' => 'hourly_update',
                'status' => RecalcLog::STATUS_RUNNING,
                'started_at' => now(),
            ]);

            // Get the corporation to update (from settings or all)
            $corporationId = Settings::getSetting('selected_corporation_id');
            
            // Update data for the last 24 hours only (efficient)
            $since = Carbon::now()->subHours(24);
            $currentMonth = Carbon::now()->format('Y-m');
            
            // Update monthly balances for current month
            $query = DB::table('corporation_wallet_journals')
                ->where('date', '>=', $since)
                ->selectRaw('
                    corporation_id,
                    DATE_FORMAT(date, "%Y-%m") as month,
                    SUM(amount) as balance
                ')
                ->groupBy('corporation_id', 'month');
            
            if ($corporationId) {
                $query->where('corporation_id', $corporationId);
            }
            
            $results = $query->get();
            $processed = 0;
            
            foreach ($results as $row) {
                // Get existing balance for the month
                $existing = MonthlyBalance::where('corporation_id', $row->corporation_id)
                    ->where('month', $currentMonth)
                    ->first();
                
                if ($existing) {
                    // Recalculate for the entire month
                    $fullMonthBalance = DB::table('corporation_wallet_journals')
                        ->where('corporation_id', $row->corporation_id)
                        ->whereMonth('date', Carbon::now()->month)
                        ->whereYear('date', Carbon::now()->year)
                        ->sum('amount');
                    
                    $existing->update(['balance' => $fullMonthBalance]);
                } else {
                    MonthlyBalance::create([
                        'corporation_id' => $row->corporation_id,
                        'month' => $row->month,
                        'balance' => $row->balance
                    ]);
                }
                $processed++;
            }
            
            // Also update division balances for current month
            $divisionQuery = DB::table('corporation_wallet_journals')
                ->where('date', '>=', $since)
                ->selectRaw('
                    corporation_id,
                    division,
                    DATE_FORMAT(date, "%Y-%m") as month,
                    SUM(amount) as balance
                ')
                ->groupBy('corporation_id', 'division', 'month');
            
            if ($corporationId) {
                $divisionQuery->where('corporation_id', $corporationId);
            }
            
            $divisionResults = $divisionQuery->get();
            
            foreach ($divisionResults as $row) {
                DivisionBalance::updateOrCreate(
                    [
                        'corporation_id' => $row->corporation_id,
                        'division_id' => $row->division,
                        'month' => $row->month
                    ],
                    ['balance' => $row->balance]
                );
                $processed++;
            }
            
            $logEntry->update([
                'status' => RecalcLog::STATUS_COMPLETED,
                'completed_at' => now(),
                'records_processed' => $processed,
            ]);
            
            Log::info('UpdateHourlyWalletData completed', [
                'records_processed' => $processed
            ]);
            
        } catch (\Exception $e) {
            if ($logEntry) {
                $logEntry->update([
                    'status' => RecalcLog::STATUS_FAILED,
                    'completed_at' => now(),
                    'error_message' => substr($e->getMessage(), 0, 1000),
                ]);
            }
            
            Log::error('UpdateHourlyWalletData failed', [
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
}
