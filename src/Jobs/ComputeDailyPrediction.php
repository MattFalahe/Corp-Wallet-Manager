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
use Seat\CorpWalletManager\Services\PredictionService;

class ComputeDailyPrediction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $corporationId;
    public $timeout = 600; // 10 minutes for complex calculations
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
                $processed = $this->computeAdvancedPredictions($this->corporationId);
            } else {
                // Compute for all corporations that have sufficient data
                $corporationIds = $this->getCorporationsWithSufficientData();

                foreach ($corporationIds as $corpId) {
                    try {
                        $processed += $this->computeAdvancedPredictions($corpId);
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
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get corporations with at least 3 months of data
     */
    private function getCorporationsWithSufficientData()
    {
        $threeMonthsAgo = Carbon::now()->subMonths(3)->format('Y-m');
        
        return MonthlyBalance::where('month', '>=', $threeMonthsAgo)
            ->select('corporation_id')
            ->distinct()
            ->groupBy('corporation_id')
            ->havingRaw('COUNT(DISTINCT month) >= 3')
            ->pluck('corporation_id');
    }

    /**
     * Compute advanced predictions using the new PredictionService
     */
    private function computeAdvancedPredictions($corporationId)
    {
        if (!is_numeric($corporationId)) {
            throw new \InvalidArgumentException('Invalid corporation ID');
        }

        // Check if we have enough historical data
        if (!$this->hasEnoughHistoricalData($corporationId)) {
            Log::warning('Not enough historical data for advanced predictions', [
                'corporation_id' => $corporationId
            ]);
            return $this->computeSimplePredictions($corporationId);
        }

        // Use the new prediction service
        $predictionService = new PredictionService($corporationId);
        
        // Generate predictions for different time horizons
        $predictions30Days = $predictionService->generatePredictions(30);
        $predictions60Days = $predictionService->generatePredictions(60);
        $predictions90Days = $predictionService->generatePredictions(90);
        
        // Clear old predictions
        Prediction::where('corporation_id', $corporationId)
            ->where('date', '>=', Carbon::today()->format('Y-m-d'))
            ->delete();
        
        // Prepare batch insert data
        $insertData = [];
        
        // Process 30-day predictions (highest confidence)
        foreach ($predictions30Days as $prediction) {
            $insertData[] = [
                'corporation_id' => $corporationId,
                'date' => $prediction['date'],
                'predicted_balance' => $prediction['predicted_balance'],
                'confidence' => $prediction['confidence'],
                'lower_bound' => $prediction['confidence_68_lower'] ?? null,
                'upper_bound' => $prediction['confidence_68_upper'] ?? null,
                'prediction_method' => 'advanced_weighted',
                'metadata' => json_encode([
                    'predicted_change' => $prediction['predicted_change'],
                    'seasonal_factor' => $prediction['seasonal_factor'] ?? null,
                    'momentum_factor' => $prediction['momentum_factor'] ?? null,
                    'activity_factor' => $prediction['activity_factor'] ?? null,
                    'confidence_95_lower' => $prediction['confidence_95_lower'] ?? null,
                    'confidence_95_upper' => $prediction['confidence_95_upper'] ?? null,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        // Add 60-day predictions (days 31-60, lower confidence)
        foreach (array_slice($predictions60Days, 30, 30) as $prediction) {
            $insertData[] = [
                'corporation_id' => $corporationId,
                'date' => $prediction['date'],
                'predicted_balance' => $prediction['predicted_balance'],
                'confidence' => $prediction['confidence'],
                'lower_bound' => $prediction['confidence_68_lower'] ?? null,
                'upper_bound' => $prediction['confidence_68_upper'] ?? null,
                'prediction_method' => 'advanced_weighted',
                'metadata' => json_encode([
                    'predicted_change' => $prediction['predicted_change'],
                    'seasonal_factor' => $prediction['seasonal_factor'] ?? null,
                    'momentum_factor' => $prediction['momentum_factor'] ?? null,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        // Add 90-day predictions (days 61-90, lowest confidence)
        foreach (array_slice($predictions90Days, 60, 30) as $prediction) {
            $insertData[] = [
                'corporation_id' => $corporationId,
                'date' => $prediction['date'],
                'predicted_balance' => $prediction['predicted_balance'],
                'confidence' => $prediction['confidence'] * 0.8, // Further reduce confidence
                'lower_bound' => $prediction['confidence_95_lower'] ?? null,
                'upper_bound' => $prediction['confidence_95_upper'] ?? null,
                'prediction_method' => 'advanced_weighted_extended',
                'metadata' => json_encode([
                    'predicted_change' => $prediction['predicted_change'],
                    'extended_range' => true,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        // Insert in chunks
        $chunks = array_chunk($insertData, 100);
        foreach ($chunks as $chunk) {
            Prediction::insert($chunk);
        }
        
        // Store prediction quality metrics
        $this->storePredictionMetrics($corporationId, $predictions30Days);
        
        return count($insertData);
    }

    /**
     * Check if corporation has enough historical data
     */
    private function hasEnoughHistoricalData($corporationId)
    {
        $threeMonthsAgo = Carbon::now()->subMonths(3);
        
        $dataPoints = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->where('date', '>=', $threeMonthsAgo)
            ->count();
        
        // Need at least 60 days of data for reliable predictions
        return $dataPoints >= 60;
    }

    /**
     * Fallback to simple predictions if not enough data
     */
    private function computeSimplePredictions($corporationId)
    {
        // Your existing simple prediction logic as fallback
        $sixMonthsAgo = Carbon::now()->subMonths(6)->startOfMonth();
        
        $balances = MonthlyBalance::where('corporation_id', $corporationId)
            ->where('month', '>=', $sixMonthsAgo->format('Y-m'))
            ->orderBy('month')
            ->get();

        if ($balances->count() < 2) {
            return 0;
        }

        $values = $balances->pluck('balance')->map(function ($val) {
            return (float)$val;
        })->toArray();
        
        $avg = array_sum($values) / count($values);
        
        $trend = 0;
        if (count($values) > 1) {
            $recent = array_slice($values, -3);
            $older = array_slice($values, 0, 3);
            $recentAvg = count($recent) > 0 ? array_sum($recent) / count($recent) : 0;
            $olderAvg = count($older) > 0 ? array_sum($older) / count($older) : 0;
            $trend = $recentAvg != $olderAvg ? ($recentAvg - $olderAvg) / 3 : 0;
        }

        $predictions = [];
        $today = Carbon::today();
        
        for ($i = 1; $i <= 30; $i++) {
            $futureDate = $today->copy()->addDays($i);
            $monthsAhead = $today->diffInMonths($futureDate, false);
            
            $predictedBalance = $avg + ($trend * $monthsAhead);
            
            $predictions[] = [
                'corporation_id' => $corporationId,
                'date' => $futureDate->format('Y-m-d'),
                'predicted_balance' => max(0, $predictedBalance),
                'confidence' => max(20, 90 - ($i * 2)), // Simple confidence decay
                'prediction_method' => 'simple_linear',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Prediction::where('corporation_id', $corporationId)
            ->where('date', '>=', $today->format('Y-m-d'))
            ->delete();

        $chunks = array_chunk($predictions, 100);
        foreach ($chunks as $chunk) {
            Prediction::insert($chunk);
        }

        return count($predictions);
    }

    /**
     * Store prediction quality metrics for later analysis
     */
    private function storePredictionMetrics($corporationId, $predictions)
    {
        try {
            // Calculate and store prediction quality metrics
            $metrics = [
                'corporation_id' => $corporationId,
                'prediction_date' => now(),
                'data_points_used' => DB::table('corporation_wallet_journals')
                    ->where('corporation_id', $corporationId)
                    ->where('date', '>=', Carbon::now()->subMonths(12))
                    ->count(),
                'average_confidence' => collect($predictions)->avg('confidence'),
                'volatility_factor' => $this->calculateVolatilityFactor($corporationId),
                'trend_strength' => $this->calculateTrendStrength($corporationId),
            ];
            
            // Store in a metrics table or cache
            DB::table('corpwalletmanager_prediction_metrics')->updateOrInsert(
                ['corporation_id' => $corporationId],
                array_merge($metrics, ['updated_at' => now()])
            );
            
        } catch (\Exception $e) {
            Log::warning('Failed to store prediction metrics', [
                'corporation_id' => $corporationId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate volatility factor for metrics
     */
    private function calculateVolatilityFactor($corporationId)
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        $dailyChanges = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->where('date', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(date) as day, SUM(amount) as daily_change')
            ->groupBy('day')
            ->pluck('daily_change')
            ->toArray();
        
        if (count($dailyChanges) < 2) {
            return 0;
        }
        
        $mean = array_sum($dailyChanges) / count($dailyChanges);
        $variance = 0;
        
        foreach ($dailyChanges as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        $variance /= (count($dailyChanges) - 1);
        $stdDev = sqrt($variance);
        
        return $mean != 0 ? abs($stdDev / $mean) : 0;
    }

    /**
     * Calculate trend strength for metrics
     */
    private function calculateTrendStrength($corporationId)
    {
        $sixMonthsAgo = Carbon::now()->subMonths(6);
        
        $monthlyBalances = MonthlyBalance::where('corporation_id', $corporationId)
            ->where('month', '>=', $sixMonthsAgo->format('Y-m'))
            ->orderBy('month')
            ->pluck('balance')
            ->toArray();
        
        if (count($monthlyBalances) < 3) {
            return 0;
        }
        
        // Calculate linear regression slope
        $n = count($monthlyBalances);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $monthlyBalances[$i];
            $sumXY += $i * $monthlyBalances[$i];
            $sumX2 += $i * $i;
        }
        
        if (($n * $sumX2 - $sumX * $sumX) == 0) {
            return 0;
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $avgBalance = $sumY / $n;
        
        // Return normalized slope (percentage of average balance)
        return $avgBalance != 0 ? ($slope / $avgBalance) * 100 : 0;
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
