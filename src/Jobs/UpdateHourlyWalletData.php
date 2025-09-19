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
use Seat\CorpWalletManager\Services\InternalTransferService; 

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
            
            // === ADD INTERNAL TRANSFER DETECTION HERE ===
            // Process internal transfers for recent transactions
            if ($corporationId) {
                $this->detectInternalTransfers($corporationId, $since);
            } else {
                // Process for all corporations
                $corporations = DB::table('corporation_wallet_journals')
                    ->where('date', '>=', $since)
                    ->distinct('corporation_id')
                    ->pluck('corporation_id');
                    
                foreach ($corporations as $corpId) {
                    $this->detectInternalTransfers($corpId, $since);
                }
            }
            // === END INTERNAL TRANSFER DETECTION ===
            
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
    
    /**
     * Detect and mark internal transfers for recent transactions
     */
    private function detectInternalTransfers($corporationId, $since)
    {
        try {
            $service = new InternalTransferService($corporationId);
            
            // Get recent transactions that haven't been checked yet
            $transactions = DB::table('corporation_wallet_journals as j')
                ->leftJoin('corpwalletmanager_journal_metadata as m', 'j.id', '=', 'm.journal_id')
                ->where('j.corporation_id', $corporationId)
                ->where('j.date', '>=', $since)
                ->whereNull('m.journal_id') // Only unprocessed transactions
                ->select('j.*')
                ->get();
            
            $detectedCount = 0;
            
            foreach ($transactions as $transaction) {
                if ($service->isInternalTransfer($transaction)) {
                    $detectedCount++;
                    // The service automatically marks it as internal
                }
            }
            
            if ($detectedCount > 0) {
                Log::info('Internal transfers detected', [
                    'corporation_id' => $corporationId,
                    'detected_count' => $detectedCount,
                    'total_checked' => $transactions->count()
                ]);
                
                // Clear cache for this corporation
                \Cache::tags(['corp_wallet_' . $corporationId])->flush();
            }
            
        } catch (\Exception $e) {
            Log::warning('Failed to detect internal transfers in hourly update', [
                'corporation_id' => $corporationId,
                'error' => $e->getMessage()
            ]);
            // Don't throw - let the rest of the job continue
        }
    }
}
