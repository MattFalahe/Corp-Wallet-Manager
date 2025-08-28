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
use Seat\CorpWalletManager\Models\Prediction;
use Seat\CorpWalletManager\Models\MonthlyBalance;
use Seat\CorpWalletManager\Models\RecalcLog;

class ComputeDailyPrediction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $corporationId;
    public $timeout = 300; // 5 minutes
    public $tries = 3;

    public function __construct($corporationId = null)
    {
        $this->corporationId = $corporationId;
    }

    public function handle()
    {
        $logEntry = null;
        
        try {
            $logEntry = RecalcLog::create([
                'job_type' => 'daily_prediction',
                'corporation_id' => $this->corporationId,
                'status' => RecalcLog::STATUS_RUNNING,
                'started_at' => now(),
            ]);

            $processed = 0;

            if ($this->corporationId) {
                $processed = $this->computePredictionsForCorporation($this->corporationId);
            } else {
                // Compute for all corporations that have data
                $corporationIds = MonthlyBalance::distinct('corporation_id')
                    ->whereNotNull('corporation_id')
                    ->pluck('corporation_id');

                foreach ($corporationIds as $corpId) {
                    try {
                        $processed += $this->computePredictionsForCorporation($corpId);
                    } catch (\Exception $e) {
                        Log::warning('ComputeDailyPrediction: Failed for corporation', [
                            'corporation_id' => $corpId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            $logEntry->update([
                'status' => RecalcLog::STATUS_COMPLETED,
                'completed_at' => now(),
                'records_processed' => $processed,
            ]);

            Log::info('ComputeDailyPrediction completed successfully', [
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
            
            Log::error('ComputeDailyPrediction failed', [
                'corporation_id' => $this->corporationId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    private function computePredictionsForCorporation($corporationId)
    {
        if (!is_numeric($corporationId)) {
            throw new \InvalidArgumentException('Invalid corporation ID');
        }

        // Get the last 6 months of data for trend analysis
        $sixMonthsAgo = Carbon::now()->subMonths(6)->startOfMonth();
        
        $balances = MonthlyBalance::where('corporation_id', $corporationId)
            ->where('month', '>=', $sixMonthsAgo->format('Y-m'))
            ->orderBy('month')
            ->get();

        if ($balances->count() < 2) {
            // Not enough data for prediction
            return 0;
        }

        // Simple linear regression for trend prediction
        $values = $balances->pluck('balance')->map(function ($val) {
            return (float)$val;
        })->toArray();
        
        $avg = array_sum($values) / count($values);
        
        // Calculate trend (simple moving average)
        $trend = 0;
        if (count($values) > 1) {
            $recent = array_slice($values, -3); // Last 3 months
            $older = array_slice($values, 0, 3); // First 3 months
            $recentAvg = count($recent) > 0 ? array_sum($recent) / count($recent) : 0;
            $olderAvg = count($older) > 0 ? array_sum($older) / count($older) : 0;
            $trend = $recentAvg != $olderAvg ? ($recentAvg - $olderAvg) / 3 : 0; // Monthly trend
        }

        // Predict for the next 30 days
        $predictions = [];
        $today = Carbon::today();
        
        for ($i = 1; $i <= 30; $i++) {
            $futureDate = $today->copy()->addDays($i);
            $monthsAhead = $today->diffInMonths($futureDate, false);
            
            $predictedBalance = $avg + ($trend * $monthsAhead);
            
            $predictions[] = [
                'corporation_id' => $corporationId,
                'date' => $futureDate->format('Y-m-d'),
                'predicted_balance' => max(0, $predictedBalance), // Don't predict negative
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Clear old predictions for this corporation
        Prediction::where('corporation_id', $corporationId)
            ->where('date', '>=', $today->format('Y-m-d'))
            ->delete();

        // Insert new predictions in chunks to avoid memory issues
        $chunks = array_chunk($predictions, 100);
        foreach ($chunks as $chunk) {
            Prediction::insert($chunk);
        }

        return count($predictions);
    }

    public function failed(\Exception $exception)
    {
        Log::error('ComputeDailyPrediction job permanently failed', [
            'corporation_id' => $this->corporationId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
