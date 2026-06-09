<?php
namespace CorpWalletManager\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use CorpWalletManager\Models\MonthlyBalance;
use CorpWalletManager\Models\Prediction;
use CorpWalletManager\Support\JournalFilters;

class PredictionService
{
    // Weights for different time periods (must sum to 1.0)
    private const WEIGHT_DISTRIBUTION = [
        'current_month' => 0.35,    // Last 30 days
        'previous_month' => 0.25,    // 31-60 days ago
        'quarter_recent' => 0.20,    // 61-90 days ago
        'quarter_old' => 0.12,       // 91-180 days ago
        'half_year' => 0.08,         // 181-365 days ago
    ];

    private $corporationId;
    private $historicalData = [];
    private $activityPatterns = [];
    private $confidence = 0;
    private $seasonalFactors;
    private ?float $observedMape30d = null;

    public function __construct($corporationId, ?SeasonalFactorLearner $learner = null)
    {
        $this->corporationId = $corporationId;
        $learner = $learner ?? app(SeasonalFactorLearner::class);
        $this->seasonalFactors = $learner->getFactors((int) $corporationId);
        $this->observedMape30d = $this->loadObservedMape((int) $corporationId);
    }

    /**
     * Look up the 30-day MAPE stored by BacktestService, if it exists.
     * Null means we have no backtest data yet (newly installed, or no
     * predictions old enough to compare against actuals).
     */
    private function loadObservedMape(int $corporationId): ?float
    {
        try {
            $value = DB::table('corpwalletmanager_prediction_metrics')
                ->where('corporation_id', $corporationId)
                ->value('mape_30d');
            return $value !== null ? (float) $value : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
    
    /**
     * Generate predictions for the next 30-90 days
     */
    public function generatePredictions($days = 30)
    {
        // Load and analyze historical data
        $this->loadHistoricalData();
        $this->analyzeActivityPatterns();
        
        // Calculate base predictions
        $predictions = $this->calculateWeightedPredictions($days);
        
        // Apply seasonal adjustments
        $predictions = $this->applySeasonalAdjustments($predictions);
        
        // Apply activity pattern adjustments
        $predictions = $this->applyActivityPatterns($predictions);
        
        // Calculate confidence intervals
        $predictions = $this->addConfidenceIntervals($predictions);
        
        // Apply trend momentum
        $predictions = $this->applyTrendMomentum($predictions);
        
        // Smooth out predictions to avoid unrealistic spikes
        $predictions = $this->smoothPredictions($predictions);
        
        return $predictions;
    }
    
    /**
     * Load 12 months of historical data with transaction patterns
     */
    private function loadHistoricalData()
    {
        $twelveMonthsAgo = Carbon::now()->subMonths(12)->startOfDay();
        
        // Simpler approach - get raw data and process in PHP.
        // Internal transfers (+X / -X pairs the corp moves between its own
        // divisions) double-count daily_income AND daily_expenses while
        // netting to zero on daily_change, so strip them before any
        // per-day aggregation downstream.
        $rawDataQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->where('date', '>=', $twelveMonthsAgo);
        $rawDataQuery = JournalFilters::excludeInternalTransfers($rawDataQuery, (int) $this->corporationId);
        $rawData = $rawDataQuery
            ->select('date', 'amount', 'ref_type')
            ->orderBy('date')
            ->get();
        
        if ($rawData->isEmpty()) {
            Log::warning('PredictionService: No historical data found', [
                'corporation_id' => $this->corporationId
            ]);
            throw new \Exception('Insufficient historical data for predictions');
        }
        
        // Process data by day in PHP to avoid GROUP BY issues
        $this->historicalData = collect();
        $groupedByDay = $rawData->groupBy(function($item) {
            return Carbon::parse($item->date)->format('Y-m-d');
        });
        
        foreach ($groupedByDay as $day => $transactions) {
            $dailyIncome = $transactions->where('amount', '>', 0)->sum('amount');
            $dailyExpenses = $transactions->where('amount', '<', 0)->sum(function($t) {
                return abs($t->amount);
            });
            $dailyChange = $transactions->sum('amount');
            
            $date = Carbon::parse($day);
            
            $dayData = (object)[
                'day' => $day,
                'daily_change' => $dailyChange,
                'daily_income' => $dailyIncome,
                'daily_expenses' => $dailyExpenses,
                'transaction_count' => $transactions->count(),
                'transaction_types' => $transactions->pluck('ref_type')->unique()->count(),
                'avg_transaction_size' => $transactions->avg(function($t) {
                    return abs($t->amount);
                }),
                'max_transaction' => $transactions->max(function($t) {
                    return abs($t->amount);
                }),
                'day_of_week' => $date->dayOfWeek == 0 ? 7 : $date->dayOfWeek,
                'week_number' => $date->weekOfYear,
                'day_of_month' => $date->day
            ];
            
            $this->historicalData->put($day, $dayData);
        }
        
        // Calculate rolling averages and volatility
        $this->calculateRollingMetrics();
        
        Log::info('PredictionService: Loaded ' . count($this->historicalData) . ' days of historical data', [
            'corporation_id' => $this->corporationId
        ]);
    }
    
    /**
     * Analyze activity patterns for pattern recognition
     */
    private function analyzeActivityPatterns()
    {
        // Analyze day-of-week patterns
        $this->activityPatterns['day_of_week'] = $this->analyzeDayOfWeekPattern();
        
        // Analyze week-of-month patterns
        $this->activityPatterns['week_of_month'] = $this->analyzeWeekOfMonthPattern();
        
        // Analyze monthly patterns
        $this->activityPatterns['monthly'] = $this->analyzeMonthlyPattern();
        
        // Detect recurring transactions (like office rentals, fuel costs)
        $this->activityPatterns['recurring'] = $this->detectRecurringTransactions();
        
        // Analyze volatility patterns
        $this->activityPatterns['volatility'] = $this->analyzeVolatilityPatterns();
    }
    
    /**
     * Calculate weighted predictions based on historical periods
     */
    private function calculateWeightedPredictions($days)
    {
        $predictions = [];
        $today = Carbon::today();
        
        // Get weighted average daily changes for each period
        $weightedChanges = $this->calculateWeightedAverages();
        
        // Get the current balance as starting point
        $currentBalance = $this->getCurrentBalance();
        
        // Generate base predictions
        for ($i = 1; $i <= $days; $i++) {
            $futureDate = $today->copy()->addDays($i);
            
            // Calculate base daily change
            $baseChange = $this->calculateBaseDailyChange($futureDate, $weightedChanges);
            
            // Calculate trend adjustment
            $trendAdjustment = $this->calculateTrendAdjustment($i);
            
            // Combine base and trend
            $predictedChange = $baseChange * (1 + $trendAdjustment);
            
            // Update running balance
            $currentBalance += $predictedChange;
            
            $predictions[] = [
                'date' => $futureDate->format('Y-m-d'),
                'predicted_balance' => $currentBalance,
                'predicted_change' => $predictedChange,
                'base_change' => $baseChange,
                'trend_factor' => 1 + $trendAdjustment,
                'confidence' => $this->calculateDayConfidence($i),
            ];
        }
        
        return $predictions;
    }
    
    /**
     * Calculate weighted averages from historical data
     */
    private function calculateWeightedAverages()
    {
        $now = Carbon::now();
        $periods = [
            'current_month' => [$now->copy()->subDays(30), $now->copy()],
            'previous_month' => [$now->copy()->subDays(60), $now->copy()->subDays(31)],
            'quarter_recent' => [$now->copy()->subDays(90), $now->copy()->subDays(61)],
            'quarter_old' => [$now->copy()->subDays(180), $now->copy()->subDays(91)],
            'half_year' => [$now->copy()->subDays(365), $now->copy()->subDays(181)],
        ];
        
        $weightedAverages = [];
        
        foreach ($periods as $period => $dates) {
            $periodData = collect($this->historicalData)
                ->filter(function ($day) use ($dates) {
                    $dayDate = Carbon::parse($day->day);
                    return $dayDate->between($dates[0], $dates[1]);
                });
            
            if ($periodData->count() > 0) {
                $avgChange = $periodData->avg('daily_change');
                $avgIncome = $periodData->avg('daily_income');
                $avgExpenses = $periodData->avg('daily_expenses');
                $volatility = $this->calculateStandardDeviation($periodData->pluck('daily_change')->toArray());
                
                $weightedAverages[$period] = [
                    'avg_change' => $avgChange,
                    'avg_income' => $avgIncome,
                    'avg_expenses' => $avgExpenses,
                    'volatility' => $volatility,
                    'weight' => self::WEIGHT_DISTRIBUTION[$period],
                    'data_points' => $periodData->count(),
                ];
            } else {
                $weightedAverages[$period] = [
                    'avg_change' => 0,
                    'avg_income' => 0,
                    'avg_expenses' => 0,
                    'volatility' => 0,
                    'weight' => 0,
                    'data_points' => 0,
                ];
            }
        }
        
        // Normalize weights if we have missing data
        $totalWeight = collect($weightedAverages)->sum('weight');
        if ($totalWeight < 1.0 && $totalWeight > 0) {
            foreach ($weightedAverages as &$period) {
                $period['weight'] = $period['weight'] / $totalWeight;
            }
        }
        
        return $weightedAverages;
    }
    
    /**
     * Calculate base daily change for a specific date
     */
    private function calculateBaseDailyChange($date, $weightedChanges)
    {
        $totalChange = 0;
        $totalWeight = 0;
        
        foreach ($weightedChanges as $period => $data) {
            if ($data['data_points'] > 0) {
                $totalChange += $data['avg_change'] * $data['weight'];
                $totalWeight += $data['weight'];
            }
        }
        
        // If we have data, use weighted average, otherwise return 0
        return $totalWeight > 0 ? $totalChange : 0;
    }
    
    /**
     * Apply seasonal adjustments to predictions
     */
    private function applySeasonalAdjustments($predictions)
    {
        foreach ($predictions as &$prediction) {
            $date = Carbon::parse($prediction['date']);
            
            // Day of week adjustment (with fallback)
            $dayOfWeek = $date->dayOfWeek == 0 ? 7 : $date->dayOfWeek;
            $dayFactor = $this->seasonalFactors['day_of_week'][$dayOfWeek] ?? 1.0;

            // Week of month adjustment (with bounds check)
            $weekOfMonth = min(4, max(1, ceil($date->day / 7))); // Ensure 1-4 range
            $weekFactor = $this->seasonalFactors['week_of_month'][$weekOfMonth] ?? 1.0;

            // Month of year adjustment
            $monthFactor = $this->seasonalFactors['month_of_year'][$date->month] ?? 1.0;
            
            // Combine factors (multiplicative)
            $seasonalFactor = $dayFactor * $weekFactor * $monthFactor;
            
            // Apply to predicted change, not balance
            $prediction['predicted_change'] *= $seasonalFactor;
            $prediction['seasonal_factor'] = $seasonalFactor;
            
            // Recalculate balance
            if (isset($predictions[array_search($prediction, $predictions) - 1])) {
                $prevBalance = $predictions[array_search($prediction, $predictions) - 1]['predicted_balance'];
                $prediction['predicted_balance'] = $prevBalance + $prediction['predicted_change'];
            }
        }
        
        return $predictions;
    }
    
    /**
     * Apply learned activity patterns
     */
    private function applyActivityPatterns($predictions)
    {
        foreach ($predictions as &$prediction) {
            $date = Carbon::parse($prediction['date']);
            $dayOfWeek = $date->dayOfWeek == 0 ? 7 : $date->dayOfWeek;
            
            // Apply day-of-week pattern if we have it
            if (isset($this->activityPatterns['day_of_week'][$dayOfWeek])) {
                $pattern = $this->activityPatterns['day_of_week'][$dayOfWeek];
                $prediction['predicted_change'] *= $pattern['factor'];
                $prediction['activity_factor'] = $pattern['factor'];
            }
            
            // Check for recurring transactions on this day
            if (isset($this->activityPatterns['recurring'])) {
                $dayOfMonth = $date->day;
                foreach ($this->activityPatterns['recurring'] as $recurring) {
                    if ($recurring['day_of_month'] == $dayOfMonth) {
                        $prediction['predicted_change'] += $recurring['average_amount'];
                        $prediction['recurring_adjustment'] = $recurring['average_amount'];
                    }
                }
            }
        }
        
        // Recalculate cumulative balances
        $this->recalculateBalances($predictions);
        
        return $predictions;
    }
    
    /**
     * Calculate trend adjustment based on recent momentum
     */
    private function calculateTrendAdjustment($daysAhead)
    {
        // Get recent trend (last 30 days vs previous 30 days)
        $recent30 = collect($this->historicalData)
            ->filter(function ($day) {
                $date = Carbon::parse($day->day);
                return $date->isAfter(Carbon::now()->subDays(30));
            });
        
        $previous30 = collect($this->historicalData)
            ->filter(function ($day) {
                $date = Carbon::parse($day->day);
                return $date->isBetween(Carbon::now()->subDays(60), Carbon::now()->subDays(31));
            });
        
        if ($recent30->isEmpty() || $previous30->isEmpty()) {
            return 0;
        }
        
        $recentAvg = $recent30->avg('daily_change');
        $previousAvg = $previous30->avg('daily_change');
        
        // Calculate momentum
        if ($previousAvg != 0) {
            $momentum = ($recentAvg - $previousAvg) / abs($previousAvg);
        } else {
            $momentum = $recentAvg > 0 ? 0.1 : -0.1;
        }
        
        // Decay momentum over time (it becomes less reliable further out)
        $decayFactor = exp(-$daysAhead / 30); // Exponential decay
        
        return $momentum * $decayFactor * 0.1; // Scale down to avoid extreme predictions
    }
    
    /**
     * Add confidence intervals to predictions
     */
    private function addConfidenceIntervals($predictions)
    {
        // Calculate historical volatility
        $historicalChanges = collect($this->historicalData)->pluck('daily_change')->toArray();
        $stdDev = $this->calculateStandardDeviation($historicalChanges);
        
        foreach ($predictions as $index => &$prediction) {
            $daysAhead = $index + 1;
            
            // Confidence decreases with time
            $confidence = $prediction['confidence'];
            
            // Standard error increases with square root of time (random walk assumption)
            $standardError = $stdDev * sqrt($daysAhead);
            
            // Calculate confidence intervals (68% and 95%)
            $prediction['confidence_68_lower'] = $prediction['predicted_balance'] - $standardError;
            $prediction['confidence_68_upper'] = $prediction['predicted_balance'] + $standardError;
            $prediction['confidence_95_lower'] = $prediction['predicted_balance'] - (2 * $standardError);
            $prediction['confidence_95_upper'] = $prediction['predicted_balance'] + (2 * $standardError);
            
            // Ensure we don't predict negative balances
            $prediction['confidence_68_lower'] = max(0, $prediction['confidence_68_lower']);
            $prediction['confidence_95_lower'] = max(0, $prediction['confidence_95_lower']);
        }
        
        return $predictions;
    }
    
    /**
     * Apply trend momentum to predictions
     */
    private function applyTrendMomentum($predictions)
    {
        // Calculate recent momentum indicators
        $momentum = $this->calculateMomentumIndicators();
        
        foreach ($predictions as $index => &$prediction) {
            $daysAhead = $index + 1;
            
            // Apply momentum with decay
            $momentumFactor = 1 + ($momentum['strength'] * exp(-$daysAhead / $momentum['period']));
            
            $prediction['predicted_change'] *= $momentumFactor;
            $prediction['momentum_factor'] = $momentumFactor;
        }
        
        // Recalculate balances
        $this->recalculateBalances($predictions);
        
        return $predictions;
    }
    
    /**
     * Smooth predictions to avoid unrealistic spikes
     */
    private function smoothPredictions($predictions)
    {
        // Apply moving average smoothing
        $windowSize = 3;
        
        for ($i = $windowSize; $i < count($predictions) - $windowSize; $i++) {
            $sum = 0;
            $count = 0;
            
            for ($j = -$windowSize; $j <= $windowSize; $j++) {
                if (isset($predictions[$i + $j])) {
                    $weight = 1 - (abs($j) / ($windowSize + 1)); // Linear decay
                    $sum += $predictions[$i + $j]['predicted_change'] * $weight;
                    $count += $weight;
                }
            }
            
            if ($count > 0) {
                $predictions[$i]['predicted_change_smoothed'] = $sum / $count;
            }
        }
        
        // Apply smoothed values
        foreach ($predictions as &$prediction) {
            if (isset($prediction['predicted_change_smoothed'])) {
                $prediction['predicted_change'] = $prediction['predicted_change_smoothed'];
            }
        }
        
        // Final balance recalculation
        $this->recalculateBalances($predictions);
        
        return $predictions;
    }
    
    // =============== HELPER METHODS ===============
    
    /**
     * Analyze day of week patterns from historical data
     */
    private function analyzeDayOfWeekPattern()
    {
        $patterns = [];
        
        for ($dow = 1; $dow <= 7; $dow++) {
            $dayData = collect($this->historicalData)
                ->filter(function ($day) use ($dow) {
                    return $day->day_of_week == $dow;
                });
            
            if ($dayData->count() > 4) { // Need at least 4 samples
                $avgChange = $dayData->avg('daily_change');
                $avgTransactions = $dayData->avg('transaction_count');
                
                // Calculate overall average
                $overallAvg = collect($this->historicalData)->avg('daily_change');
                
                $patterns[$dow] = [
                    'factor' => $overallAvg != 0 ? $avgChange / $overallAvg : 1.0,
                    'avg_transactions' => $avgTransactions,
                    'sample_size' => $dayData->count(),
                ];
            }
        }
        
        return $patterns;
    }
    
    /**
     * Analyze week of month patterns
     */
    private function analyzeWeekOfMonthPattern()
    {
        $patterns = [];
        
        for ($week = 1; $week <= 4; $week++) {
            $weekData = collect($this->historicalData)
                ->filter(function ($day) use ($week) {
                    $dayOfMonth = Carbon::parse($day->day)->day;
                    return ceil($dayOfMonth / 7) == $week;
                });
            
            if ($weekData->count() > 7) {
                $avgChange = $weekData->avg('daily_change');
                $overallAvg = collect($this->historicalData)->avg('daily_change');
                
                $patterns[$week] = [
                    'factor' => $overallAvg != 0 ? $avgChange / $overallAvg : 1.0,
                    'sample_size' => $weekData->count(),
                ];
            }
        }
        
        return $patterns;
    }
    
    /**
     * Analyze monthly patterns
     */
    private function analyzeMonthlyPattern()
    {
        $twelveMonthsAgo = Carbon::now()->subMonths(12);
        
        // Get raw data and process in PHP.
        // Internal transfers skew monthly avg() because the +X and -X halves
        // both appear in the per-month list and inflate the variance with
        // signal that doesn't represent real corp wallet movement.
        $rawDataQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->where('date', '>=', $twelveMonthsAgo);
        $rawDataQuery = JournalFilters::excludeInternalTransfers($rawDataQuery, (int) $this->corporationId);
        $rawData = $rawDataQuery
            ->select('date', 'amount')
            ->get();
        
        // Group by month in PHP
        $monthlyData = collect();
        
        foreach ($rawData as $transaction) {
            $month = Carbon::parse($transaction->date)->month;
            
            if (!$monthlyData->has($month)) {
                $monthlyData[$month] = collect();
            }
            $monthlyData[$month]->push($transaction->amount);
        }
        
        // Calculate averages
        $result = collect();
        foreach ($monthlyData as $month => $amounts) {
            $result[$month] = (object)[
                'month' => $month,
                'avg_change' => $amounts->avg(),
                'transaction_count' => $amounts->count()
            ];
        }
        
        return $result;
    }
    
    /**
     * Detect recurring transactions (like monthly bills)
     */
    private function detectRecurringTransactions()
    {
        $recurring = [];
        
        // Get raw data and process in PHP to avoid GROUP BY issues
        $sixMonthsAgo = Carbon::now()->subMonths(6);
        
        // Internal transfers share ref_type=corporation_account_withdrawal
        // and recur on the same days the corp moves ISK between divisions;
        // without this filter they'd be flagged as "reliable recurring
        // bills" and added to every prediction.
        $rawTransactionsQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->where('date', '>=', $sixMonthsAgo);
        $rawTransactionsQuery = JournalFilters::excludeInternalTransfers($rawTransactionsQuery, (int) $this->corporationId);
        $rawTransactions = $rawTransactionsQuery
            ->select('date', 'amount', 'ref_type')
            ->get();
        
        // Group by day of month and ref_type in PHP
        $patterns = collect();
        
        foreach ($rawTransactions as $transaction) {
            $dayOfMonth = Carbon::parse($transaction->date)->day;
            $key = $dayOfMonth . '_' . $transaction->ref_type;
            
            if (!$patterns->has($key)) {
                $patterns[$key] = collect();
            }
            $patterns[$key]->push($transaction->amount);
        }
        
        // Analyze patterns
        foreach ($patterns as $key => $amounts) {
            if ($amounts->count() >= 3) { // At least 3 occurrences
                $avg = $amounts->avg();
                $stdDev = $this->calculateStandardDeviation($amounts->toArray());
                
                // Low variance check
                if ($stdDev < abs($avg) * 0.2) {
                    list($dayOfMonth, $refType) = explode('_', $key, 2);
                    
                    $recurring[] = [
                        'day_of_month' => (int)$dayOfMonth,
                        'ref_type' => $refType,
                        'average_amount' => $avg,
                        'reliability' => $avg != 0 ? 1 - ($stdDev / abs($avg)) : 0,
                    ];
                }
            }
        }
        
        return $recurring;
    }
    
    /**
     * Analyze volatility patterns
     */
    private function analyzeVolatilityPatterns()
    {
        $windows = [7, 14, 30, 60];
        $volatilities = [];
        
        foreach ($windows as $window) {
            $windowData = collect($this->historicalData)->take(-$window);
            if ($windowData->count() >= $window * 0.8) { // Need at least 80% of data
                $changes = $windowData->pluck('daily_change')->toArray();
                $volatilities[$window] = $this->calculateStandardDeviation($changes);
            }
        }
        
        return $volatilities;
    }
    
    /**
     * Calculate momentum indicators
     */
    private function calculateMomentumIndicators()
    {
        // Simple moving average crossover
        $sma7 = collect($this->historicalData)->take(-7)->avg('daily_change');
        $sma30 = collect($this->historicalData)->take(-30)->avg('daily_change');
        
        $momentum = [
            'strength' => 0,
            'period' => 30,
        ];
        
        if ($sma30 != 0) {
            $momentum['strength'] = ($sma7 - $sma30) / abs($sma30);
            $momentum['strength'] = max(-0.5, min(0.5, $momentum['strength'])); // Cap at ±50%
        }
        
        return $momentum;
    }
    
    /**
     * Calculate rolling metrics for historical data
     */
    private function calculateRollingMetrics()
    {
        $data = collect($this->historicalData);
        
        foreach ($this->historicalData as $date => &$dayData) {
            $currentDate = Carbon::parse($date);
            
            // 7-day rolling average
            $rolling7 = $data->filter(function ($d) use ($currentDate) {
                $dDate = Carbon::parse($d->day);
                return $dDate->between($currentDate->copy()->subDays(6), $currentDate);
            });
            
            if ($rolling7->count() > 0) {
                $dayData->rolling_7_avg = $rolling7->avg('daily_change');
                $dayData->rolling_7_volatility = $this->calculateStandardDeviation($rolling7->pluck('daily_change')->toArray());
            }
            
            // 30-day rolling average
            $rolling30 = $data->filter(function ($d) use ($currentDate) {
                $dDate = Carbon::parse($d->day);
                return $dDate->between($currentDate->copy()->subDays(29), $currentDate);
            });
            
            if ($rolling30->count() > 0) {
                $dayData->rolling_30_avg = $rolling30->avg('daily_change');
                $dayData->rolling_30_volatility = $this->calculateStandardDeviation($rolling30->pluck('daily_change')->toArray());
            }
        }
    }
    
    /**
     * Calculate confidence for a specific day ahead.
     *
     * When BacktestService has written an observed 30-day MAPE for this
     * corporation, we derive base confidence from it: a model that has
     * been averaging 8% error recently starts at 92% confidence, not at
     * an ungrounded 95%. Otherwise we fall back to the original linear
     * 2%/day decay (for new installs with no backtest history yet).
     */
    private function calculateDayConfidence($daysAhead)
    {
        if ($this->observedMape30d !== null) {
            // Clamp MAPE to [2, 60] so pathological values don't collapse
            // confidence to 0 or inflate it past the 98% ceiling.
            $clampedMape = max(2.0, min(60.0, $this->observedMape30d));
            $baseConfidence = 100.0 - $clampedMape;
            // Horizon decay is gentler when the base is already data-backed.
            $decayRate = 1.0;
        } else {
            $baseConfidence = 95;
            $decayRate = 2;
        }

        $confidence = $baseConfidence - ($daysAhead * $decayRate);

        return max(20, $confidence);
    }
    
    /**
     * Get current balance
     */
    private function getCurrentBalance()
    {
        $balance = DB::table('corporation_wallet_balances')
            ->where('corporation_id', $this->corporationId)
            ->sum('balance');
        
        return (float)$balance;
    }
    
    /**
     * Recalculate cumulative balances
     */
    private function recalculateBalances(&$predictions)
    {
        $currentBalance = $this->getCurrentBalance();
        
        foreach ($predictions as &$prediction) {
            $currentBalance += $prediction['predicted_change'];
            $prediction['predicted_balance'] = $currentBalance;
        }
    }
    
    /**
     * Calculate standard deviation
     */
    private function calculateStandardDeviation($values)
    {
        $count = count($values);
        
        if ($count < 2) {
            return 0;
        }
        
        $mean = array_sum($values) / $count;
        $variance = 0;
        
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        $variance /= ($count - 1);
        
        return sqrt($variance);
    }
}
