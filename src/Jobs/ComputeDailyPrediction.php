<?php
namespace Seat\CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Seat\CorpWalletManager\Models\Prediction;
use Seat\CorpWalletManager\Models\MonthlyBalance;
use Seat\CorpWalletManager\Models\RecalcLog;

class ComputeDailyPrediction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $corporationId;

    public function __construct($corporationId = null)
    {
        $this->corporationId = $corporationId;
    }

    public function handle()
    {
        $logEntry = RecalcLog::create([
            'job_type' => 'daily_prediction',
            'corporation_id' => $this->corporationId,
            'status' => RecalcLog::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        try {
            $processed = 0;

            if ($this->corporationId) {
                $processed = $this->computePredictionsForCorporation($this->corporationId);
            } else {
                // Compute for all corporations that have data
                $corporationIds = MonthlyBalance::distinct('corporation_id')
                    ->whereNotNull('corporation_id')
                    ->pluck('corporation_id');

                foreach ($corporationIds as $corpId) {
                    $processed += $this->computePredictionsForCorporation($corpId);
                }
            }

            $logEntry->update([
                'status' => RecalcLog::STATUS_COMPLETED,
                'completed_at' => now(),
                'records_processed' => $processed,
            ]);

        } catch (\Exception $e) {
            $logEntry->update([
                'status' => RecalcLog::STATUS_FAILED,
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function computePredictionsForCorporation($corporationId)
    {
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
        $values = $balances->pluck('balance')->toArray();
        $avg = array_sum($values) / count($values);
        
        // Calculate trend (simple moving average)
        $trend = 0;
        if (count($values) > 1) {
            $recent = array_slice($values, -3); // Last 3 months
            $older = array_slice($values, 0, 3); // First 3 months
            $recentAvg = array_sum($recent) / count($recent);
            $olderAvg = array_sum($older) / count($older);
            $trend = ($recentAvg - $olderAvg) / 3; // Monthly trend
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

        // Insert new predictions
        Prediction::insert($predictions);

        return count($predictions);
    }
}
