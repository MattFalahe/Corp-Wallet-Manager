<?php
namespace Seat\CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Seat\CorpWalletManager\Models\MonthlyBalance;
use Seat\CorpWalletManager\Models\Prediction;
use Seat\CorpWalletManager\Models\RecalcLog;

class BackfillWalletData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $corporationId;
    protected $monthsLimit;
    protected $specificYear;
    protected $specificMonth;
    
    public $timeout = 300;
    public $tries = 3;

    public function __construct($corporationId = null, $monthsLimit = null, $specificYear = null, $specificMonth = null)
    {
        $this->corporationId = $corporationId;
        $this->monthsLimit = $monthsLimit;
        $this->specificYear = $specificYear;
        $this->specificMonth = $specificMonth;
    }

    public function handle()
    {
        $logEntry = null;
        
        try {
            $logEntry = RecalcLog::create([
                'job_type' => 'wallet_backfill',
                'corporation_id' => $this->corporationId,
                'status' => RecalcLog::STATUS_RUNNING,
                'started_at' => now(),
            ]);

            if (!Schema::hasTable('corporation_wallet_journals')) {
                throw new \Exception('Required SeAT table "corporation_wallet_journals" not found.');
            }

            $processed = 0;

            $query = DB::table('corporation_wallet_journals')
                ->selectRaw('
                    corporation_id,
                    DATE_FORMAT(date, "%Y-%m") as month, 
                    SUM(amount) as balance
                ')
                ->whereNotNull('corporation_id');

            // Apply time filters based on constructor parameters
            if ($this->specificYear && $this->specificMonth) {
                // Specific month only
                $startDate = Carbon::create($this->specificYear, $this->specificMonth, 1)->startOfMonth();
                $endDate = $startDate->copy()->endOfMonth();
                $query->whereBetween('date', [$startDate, $endDate]);
            } elseif ($this->monthsLimit) {
                // Last X months
                $startDate = Carbon::now()->subMonths($this->monthsLimit)->startOfMonth();
                $query->where('date', '>=', $startDate);
            } else {
                // Default: last 12 months
                $twelveMonthsAgo = Carbon::now()->subMonths(12)->startOfMonth();
                $query->where('date', '>=', $twelveMonthsAgo);
            }

            if ($this->corporationId) {
                $query->where('corporation_id', $this->corporationId);
            }

            $query->groupBy('corporation_id', 'month')
                ->orderBy('corporation_id')
                ->orderBy('month');

            // Process in chunks
            $query->chunk(100, function($chunk) use (&$processed) {
                foreach ($chunk as $row) {
                    try {
                        MonthlyBalance::updateOrCreate(
                            [
                                'corporation_id' => $row->corporation_id,
                                'month' => $row->month
                            ],
                            ['balance' => $row->balance ?? 0]
                        );
                        $processed++;
                    } catch (\Exception $e) {
                        Log::warning('BackfillWalletData: Failed to process monthly balance', [
                            'corporation_id' => $row->corporation_id,
                            'month' => $row->month,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            });

            // Create predictions only for corporations with recent data
            $threeMonthsAgo = Carbon::now()->subMonths(3)->startOfMonth();
            
            $recentCorporations = MonthlyBalance::where('month', '>=', $threeMonthsAgo->format('Y-m'))
                ->distinct('corporation_id')
                ->pluck('corporation_id');

            foreach ($recentCorporations as $corpId) {
                try {
                    $corpBalances = MonthlyBalance::where('corporation_id', $corpId)
                        ->where('month', '>=', $threeMonthsAgo->format('Y-m'))
                        ->orderBy('month')
                        ->get();
                    
                    if ($corpBalances->count() < 2) continue;
                    
                    $avg = $corpBalances->avg('balance');
                    
                    if ($avg !== null && is_numeric($avg)) {
                        $nextMonth = now()->addMonth()->startOfMonth();
                        
                        Prediction::updateOrCreate(
                            [
                                'corporation_id' => $corpId,
                                'date' => $nextMonth->format('Y-m-d')
                            ],
                            ['predicted_balance' => $avg]
                        );
                    }
                } catch (\Exception $e) {
                    Log::warning('BackfillWalletData: Failed to create prediction', [
                        'corporation_id' => $corpId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $logEntry->update([
                'status' => RecalcLog::STATUS_COMPLETED,
                'completed_at' => now(),
                'records_processed' => $processed,
            ]);
            
            Log::info('BackfillWalletData completed successfully', [
                'corporation_id' => $this->corporationId,
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
            
            Log::error('BackfillWalletData failed', [
                'corporation_id' => $this->corporationId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    public function failed(\Exception $exception)
    {
        Log::error('BackfillWalletData job permanently failed', [
            'corporation_id' => $this->corporationId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
