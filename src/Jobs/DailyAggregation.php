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
use Seat\CorpWalletManager\Models\RecalcLog;
use Seat\CorpWalletManager\Models\Settings;

class DailyAggregation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 3;

    public function handle()
    {
        $logEntry = null;
        
        try {
            $logEntry = RecalcLog::create([
                'job_type' => 'daily_aggregation',
                'status' => RecalcLog::STATUS_RUNNING,
                'started_at' => now(),
            ]);

            $corporationId = Settings::getSetting('selected_corporation_id');
            
            // Create daily summary table if it doesn't exist
            DB::statement('
                CREATE TABLE IF NOT EXISTS corpwalletmanager_daily_summaries (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    corporation_id BIGINT NOT NULL,
                    date DATE NOT NULL,
                    opening_balance DECIMAL(20,2),
                    closing_balance DECIMAL(20,2),
                    total_income DECIMAL(20,2),
                    total_expenses DECIMAL(20,2),
                    net_flow DECIMAL(20,2),
                    transaction_count INT,
                    top_income_type VARCHAR(255),
                    top_expense_type VARCHAR(255),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_corp_date (corporation_id, date),
                    INDEX idx_date (date),
                    INDEX idx_corp_date (corporation_id, date)
                )
            ');
            
            // Aggregate yesterday's data
            $yesterday = Carbon::yesterday();
            
            $query = DB::table('corporation_wallet_journals')
                ->whereDate('date', $yesterday)
                ->selectRaw('
                    corporation_id,
                    DATE(date) as day,
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses,
                    SUM(amount) as net_flow,
                    COUNT(*) as transaction_count
                ')
                ->groupBy('corporation_id', 'day');
            
            if ($corporationId) {
                $query->where('corporation_id', $corporationId);
            }
            
            $results = $query->get();
            $processed = 0;
            
            foreach ($results as $row) {
                // Get top transaction types for the day
                $topIncome = DB::table('corporation_wallet_journals')
                    ->where('corporation_id', $row->corporation_id)
                    ->whereDate('date', $yesterday)
                    ->where('amount', '>', 0)
                    ->selectRaw('ref_type, SUM(amount) as total')
                    ->groupBy('ref_type')
                    ->orderBy('total', 'desc')
                    ->first();
                
                $topExpense = DB::table('corporation_wallet_journals')
                    ->where('corporation_id', $row->corporation_id)
                    ->whereDate('date', $yesterday)
                    ->where('amount', '<', 0)
                    ->selectRaw('ref_type, SUM(ABS(amount)) as total')
                    ->groupBy('ref_type')
                    ->orderBy('total', 'desc')
                    ->first();
                
                // Get opening and closing balances
                $openingBalance = DB::table('corporation_wallet_balances')
                    ->where('corporation_id', $row->corporation_id)
                    ->sum('balance') - $row->net_flow;
                
                $closingBalance = $openingBalance + $row->net_flow;
                
                // Insert or update daily summary
                DB::table('corpwalletmanager_daily_summaries')
                    ->updateOrInsert(
                        [
                            'corporation_id' => $row->corporation_id,
                            'date' => $row->day
                        ],
                        [
                            'opening_balance' => $openingBalance,
                            'closing_balance' => $closingBalance,
                            'total_income' => $row->income,
                            'total_expenses' => $row->expenses,
                            'net_flow' => $row->net_flow,
                            'transaction_count' => $row->transaction_count,
                            'top_income_type' => $topIncome ? $topIncome->ref_type : null,
                            'top_expense_type' => $topExpense ? $topExpense->ref_type : null,
                            'updated_at' => now()
                        ]
                    );
                
                $processed++;
            }
            
            // Clean up old daily summaries (keep 90 days)
            DB::table('corpwalletmanager_daily_summaries')
                ->where('date', '<', Carbon::now()->subDays(90))
                ->delete();
            
            $logEntry->update([
                'status' => RecalcLog::STATUS_COMPLETED,
                'completed_at' => now(),
                'records_processed' => $processed,
            ]);
            
            Log::info('DailyAggregation completed', [
                'date' => $yesterday->format('Y-m-d'),
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
            
            Log::error('DailyAggregation failed', [
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }
}
