<?php
namespace CorpWalletManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use CorpWalletManager\Http\Controllers\Concerns\AuthorizesCorporationAccess;
use CorpWalletManager\Models\Settings;
use CorpWalletManager\Models\Prediction;
use CorpWalletManager\Models\MonthlyBalance;
use CorpWalletManager\Models\DivisionBalance;
use CorpWalletManager\Support\JournalFilters;

class AnalyticsController extends Controller
{
    use AuthorizesCorporationAccess;

    /**
    * Get Daily Cash Flow Data
    */
    public function dailyCashFlow(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            $days = min(max((int)$request->get('days', 30), 7), 90); // Between 7 and 90 days
        
            $startDate = Carbon::now()->subDays($days);
        
            $query = DB::table('corporation_wallet_journals')
                ->whereDate('date', '>=', $startDate)
                ->selectRaw('
                    DATE(date) as day,
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as daily_income,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as daily_expenses,
                    SUM(amount) as net_flow,
                    COUNT(*) as transaction_count
                ')
                ->groupBy('day')
                ->orderBy('day');

            if ($corporationId && is_numeric($corporationId)) {
                $query->where('corporation_id', $corporationId);
                $query = JournalFilters::excludeInternalTransfers($query, (int) $corporationId);
            } else {
                $query = JournalFilters::excludeInternalTransfers($query);
            }

            $dailyData = $query->get();
        
            // Format for chart
            $labels = [];
            $income = [];
            $expenses = [];
            $netFlow = [];
            $cumulative = 0;
            $cumulativeFlow = [];
        
            foreach ($dailyData as $day) {
                // Format date for display
                $labels[] = Carbon::parse($day->day)->format('M d');
                $income[] = (float)$day->daily_income;
                $expenses[] = -(float)$day->daily_expenses; // Negative for display
                $netFlow[] = (float)$day->net_flow;
            
                // Calculate cumulative flow
                $cumulative += (float)$day->net_flow;
                $cumulativeFlow[] = $cumulative;
            }
        
            // Calculate statistics
            $totalIncome = array_sum($income);
            $totalExpenses = abs(array_sum($expenses));
            $avgDailyFlow = count($netFlow) > 0 ? array_sum($netFlow) / count($netFlow) : 0;
            $bestDay = count($netFlow) > 0 ? max($netFlow) : 0;
            $worstDay = count($netFlow) > 0 ? min($netFlow) : 0;
        
            // Find best and worst day dates
            $bestDayDate = null;
            $worstDayDate = null;
            foreach ($dailyData as $index => $day) {
                if ((float)$day->net_flow == $bestDay) {
                    $bestDayDate = Carbon::parse($day->day)->format('M d, Y');
                }
                if ((float)$day->net_flow == $worstDay) {
                    $worstDayDate = Carbon::parse($day->day)->format('M d, Y');
                }
            }
        
            return response()->json([
                'labels' => $labels,
                'datasets' => [
                    'income' => $income,
                    'expenses' => $expenses,
                    'net_flow' => $netFlow,
                    'cumulative' => $cumulativeFlow
                ],
                'statistics' => [
                    'total_income' => $totalIncome,
                    'total_expenses' => $totalExpenses,
                    'net_total' => $totalIncome - $totalExpenses,
                    'average_daily_flow' => round($avgDailyFlow, 2),
                    'best_day' => [
                        'amount' => $bestDay,
                        'date' => $bestDayDate
                    ],
                    'worst_day' => [
                        'amount' => $worstDay,
                        'date' => $worstDayDate
                    ],
                    'days_positive' => count(array_filter($netFlow, function($v) { return $v > 0; })),
                    'days_negative' => count(array_filter($netFlow, function($v) { return $v < 0; })),
                    'days_total' => count($netFlow)
                ],
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => Carbon::now()->format('Y-m-d'),
                    'days' => $days
                ],
                'corporation_id' => $corporationId
            ]);
        
        } catch (\Exception $e) {
            Log::error('AnalyticsController dailyCashFlow error', [
                'error' => $e->getMessage(),
                'corporation_id' => $request->get('corporation_id')
            ]);
        
            return response()->json([
                'error' => 'Unable to fetch daily cash flow data',
                'labels' => [],
                'datasets' => [
                    'income' => [],
                    'expenses' => [],
                    'net_flow' => [],
                    'cumulative' => []
                ]
            ], 500);
        }
    }

    /**
     * Calculate Financial Health Score
     */
    public function healthScore(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            // Get current balance
            $currentBalanceQuery = DB::table('corporation_wallet_balances')
                ->selectRaw('SUM(balance) as total');
            
            if ($corporationId) {
                $currentBalanceQuery->where('corporation_id', $corporationId);
            }
            
            $currentBalance = (float)($currentBalanceQuery->first()->total ?? 0);
            
            // Get 3-month average balance
            $threeMonthsAgo = Carbon::now()->subMonths(3)->startOfMonth();
            $avgBalanceQuery = MonthlyBalance::where('month', '>=', $threeMonthsAgo->format('Y-m'));
            
            if ($corporationId) {
                $avgBalanceQuery->where('corporation_id', $corporationId);
            }
            
            $avgBalance = $avgBalanceQuery->avg('balance') ?? 0;
            
            // Get income/expense ratio for last month
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();
            $financialsQuery = DB::table('corporation_wallet_journals')
                ->whereMonth('date', $lastMonth->month)
                ->whereYear('date', $lastMonth->year)
                ->selectRaw('
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses
                ');

            if ($corporationId) {
                $financialsQuery->where('corporation_id', $corporationId);
                $financialsQuery = JournalFilters::excludeInternalTransfers($financialsQuery, (int) $corporationId);
            } else {
                $financialsQuery = JournalFilters::excludeInternalTransfers($financialsQuery);
            }

            $financials = $financialsQuery->first();
            $incomeExpenseRatio = ($financials->expenses > 0) 
                ? $financials->income / $financials->expenses 
                : 2.0;
            
            // Calculate volatility (standard deviation of daily changes)
            $thirtyDaysAgo = Carbon::now()->subDays(30);
            $dailyChangesQuery = DB::table('corporation_wallet_journals')
                ->where('date', '>=', $thirtyDaysAgo)
                ->selectRaw('DATE(date) as day, SUM(amount) as daily_change')
                ->groupBy('day');

            if ($corporationId) {
                $dailyChangesQuery->where('corporation_id', $corporationId);
                $dailyChangesQuery = JournalFilters::excludeInternalTransfers($dailyChangesQuery, (int) $corporationId);
            } else {
                $dailyChangesQuery = JournalFilters::excludeInternalTransfers($dailyChangesQuery);
            }

            $dailyChanges = $dailyChangesQuery->pluck('daily_change')->toArray();
            $volatility = $this->calculateStandardDeviation($dailyChanges);
            $avgDailyChange = count($dailyChanges) > 0 ? array_sum($dailyChanges) / count($dailyChanges) : 0;
            $volatilityScore = $avgDailyChange != 0 ? abs($volatility / $avgDailyChange) : 0;
            
            // Calculate component scores
            $balanceScore = min(100, ($currentBalance > 0 && $avgBalance > 0) 
                ? ($currentBalance / $avgBalance) * 50 
                : 0);
            
            $ratioScore = min(40, $incomeExpenseRatio * 20);
            
            $stabilityScore = max(0, 10 - ($volatilityScore * 10));
            
            // Calculate overall health score
            $healthScore = round($balanceScore + $ratioScore + $stabilityScore);
            $healthScore = max(0, min(100, $healthScore)); // Ensure 0-100 range
            
            // Determine status
            $status = 'Poor';
            if ($healthScore >= 80) $status = 'Excellent';
            elseif ($healthScore >= 60) $status = 'Good';
            elseif ($healthScore >= 40) $status = 'Moderate';
            
            return response()->json([
                'score' => $healthScore,
                'status' => $status,
                'components' => [
                    'balance_stability' => round($balanceScore),
                    'income_consistency' => round($ratioScore),
                    'expense_control' => round($stabilityScore),
                ],
                'details' => [
                    'current_balance' => $currentBalance,
                    'average_balance' => $avgBalance,
                    'income_expense_ratio' => round($incomeExpenseRatio, 2),
                    'volatility_index' => round($volatilityScore, 2),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('AnalyticsController healthScore error', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Unable to calculate health score',
                'score' => 0,
                'status' => 'Unknown'
            ], 500);
        }
    }

    /**
     * Calculate Burn Rate
     */
    public function burnRate(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            // Get current balance
            $balanceQuery = DB::table('corporation_wallet_balances')
                ->selectRaw('SUM(balance) as total');
            
            if ($corporationId) {
                $balanceQuery->where('corporation_id', $corporationId);
            }
            
            $currentBalance = (float)($balanceQuery->first()->total ?? 0);
            
            // Calculate daily, weekly, monthly burn rates
            $periods = [
                'daily' => 1,
                'weekly' => 7,
                'monthly' => 30,
            ];
            
            $burnRates = [];
            
            foreach ($periods as $period => $days) {
                $startDate = Carbon::now()->subDays($days);

                $query = DB::table('corporation_wallet_journals')
                    ->where('date', '>=', $startDate)
                    ->selectRaw('
                        SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses,
                        SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income
                    ');

                if ($corporationId) {
                    $query->where('corporation_id', $corporationId);
                    $query = JournalFilters::excludeInternalTransfers($query, (int) $corporationId);
                } else {
                    $query = JournalFilters::excludeInternalTransfers($query);
                }

                $result = $query->first();

                $netBurn = ($result->expenses - $result->income) / $days;
                $burnRates[$period] = $netBurn;
            }
            
            // Calculate days of cash remaining
            $dailyBurn = $burnRates['daily'];
            $daysRemaining = $dailyBurn > 0 ? floor($currentBalance / $dailyBurn) : 999;
            $daysRemaining = min($daysRemaining, 999); // Cap at 999 for display
            
            // Calculate runway date
            $runwayDate = $daysRemaining < 999 
                ? Carbon::now()->addDays($daysRemaining)->format('Y-m-d')
                : null;
            
            return response()->json([
                'current_balance' => $currentBalance,
                'burn_rates' => [
                    'daily' => $burnRates['daily'],
                    'weekly' => $burnRates['weekly'] / 7,
                    'monthly' => $burnRates['monthly'] / 30,
                ],
                'days_of_cash' => $daysRemaining,
                'runway_date' => $runwayDate,
                'status' => $daysRemaining > 90 ? 'healthy' : ($daysRemaining > 30 ? 'warning' : 'critical'),
            ]);
            
        } catch (\Exception $e) {
            Log::error('AnalyticsController burnRate error', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Unable to calculate burn rate',
                'burn_rates' => ['daily' => 0, 'weekly' => 0, 'monthly' => 0],
                'days_of_cash' => 0
            ], 500);
        }
    }

    /**
     * Calculate Financial Ratios
     */
    public function financialRatios(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            // Current month data
            $currentMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();
            
            // Get monthly financials
            $monthlyQuery = DB::table('corporation_wallet_journals')
                ->whereMonth('date', $currentMonth->month)
                ->whereYear('date', $currentMonth->year)
                ->selectRaw('
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses
                ');

            if ($corporationId) {
                $monthlyQuery->where('corporation_id', $corporationId);
                $monthlyQuery = JournalFilters::excludeInternalTransfers($monthlyQuery, (int) $corporationId);
            } else {
                $monthlyQuery = JournalFilters::excludeInternalTransfers($monthlyQuery);
            }

            $currentMonthData = $monthlyQuery->first();

            // Last month data for growth calculation
            $lastMonthQuery = DB::table('corporation_wallet_journals')
                ->whereMonth('date', $lastMonth->month)
                ->whereYear('date', $lastMonth->year)
                ->selectRaw('
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses
                ');

            if ($corporationId) {
                $lastMonthQuery->where('corporation_id', $corporationId);
                $lastMonthQuery = JournalFilters::excludeInternalTransfers($lastMonthQuery, (int) $corporationId);
            } else {
                $lastMonthQuery = JournalFilters::excludeInternalTransfers($lastMonthQuery);
            }

            $lastMonthData = $lastMonthQuery->first();
            
            // Current balance
            $balanceQuery = DB::table('corporation_wallet_balances')
                ->selectRaw('SUM(balance) as total');
            
            if ($corporationId) {
                $balanceQuery->where('corporation_id', $corporationId);
            }
            
            $currentBalance = (float)($balanceQuery->first()->total ?? 0);
            
            // Calculate ratios
            $liquidityRatio = $currentMonthData->expenses > 0 
                ? round($currentBalance / $currentMonthData->expenses, 2)
                : 0;
            
            $incomeExpenseRatio = $currentMonthData->expenses > 0 
                ? round($currentMonthData->income / $currentMonthData->expenses, 2)
                : 0;
            
            $growthRate = $lastMonthData->income > 0 
                ? round((($currentMonthData->income - $lastMonthData->income) / $lastMonthData->income) * 100, 2)
                : 0;
            
            // Calculate volatility
            $thirtyDaysAgo = Carbon::now()->subDays(30);
            $dailyBalances = DB::table('corporation_wallet_journals')
                ->where('date', '>=', $thirtyDaysAgo)
                ->selectRaw('DATE(date) as day, SUM(amount) as daily_change')
                ->groupBy('day');

            if ($corporationId) {
                $dailyBalances->where('corporation_id', $corporationId);
                $dailyBalances = JournalFilters::excludeInternalTransfers($dailyBalances, (int) $corporationId);
            } else {
                $dailyBalances = JournalFilters::excludeInternalTransfers($dailyBalances);
            }

            $changes = $dailyBalances->pluck('daily_change')->toArray();
            $volatility = count($changes) > 0 ? $this->calculateStandardDeviation($changes) : 0;
            $avgChange = count($changes) > 0 ? abs(array_sum($changes) / count($changes)) : 1;
            $volatilityPercent = $avgChange > 0 ? round(($volatility / $avgChange) * 100, 2) : 0;
            
            return response()->json([
                'liquidity_ratio' => $liquidityRatio,
                'income_expense_ratio' => $incomeExpenseRatio,
                'growth_rate' => $growthRate,
                'volatility' => $volatilityPercent,
                'interpretations' => [
                    'liquidity' => $liquidityRatio > 3 ? 'Excellent' : ($liquidityRatio > 1 ? 'Good' : 'Poor'),
                    'profitability' => $incomeExpenseRatio > 1.2 ? 'Profitable' : ($incomeExpenseRatio > 0.9 ? 'Break-even' : 'Loss'),
                    'growth' => $growthRate > 10 ? 'High Growth' : ($growthRate > 0 ? 'Moderate Growth' : 'Declining'),
                    'stability' => $volatilityPercent < 20 ? 'Stable' : ($volatilityPercent < 50 ? 'Moderate' : 'Volatile'),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('AnalyticsController financialRatios error', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Unable to calculate financial ratios',
                'liquidity_ratio' => 0,
                'income_expense_ratio' => 0,
                'growth_rate' => 0,
                'volatility' => 0
            ], 500);
        }
    }

    /**
     * Get Activity Heatmap Data
     */
    public function activityHeatmap(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            $days = min(max((int)$request->get('days', 90), 30), 365);
            
            $startDate = Carbon::now()->subDays($days);
            
            $query = DB::table('corporation_wallet_journals')
                ->where('date', '>=', $startDate)
                ->selectRaw('
                    DATE(date) as day,
                    SUM(amount) as net_flow,
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses,
                    COUNT(*) as transaction_count
                ')
                ->groupBy('day')
                ->orderBy('day');

            if ($corporationId) {
                $query->where('corporation_id', $corporationId);
                $query = JournalFilters::excludeInternalTransfers($query, (int) $corporationId);
            } else {
                $query = JournalFilters::excludeInternalTransfers($query);
            }

            $data = $query->get();

            // Format for heatmap
            $heatmapData = [];
            foreach ($data as $day) {
                $heatmapData[] = [
                    'date' => $day->day,
                    'value' => (float)$day->net_flow,
                    'income' => (float)$day->income,
                    'expenses' => (float)$day->expenses,
                    'transactions' => $day->transaction_count,
                    'intensity' => $this->calculateIntensity($day->net_flow, $data->pluck('net_flow')->toArray()),
                ];
            }
            
            return response()->json([
                'heatmap' => $heatmapData,
                'summary' => [
                    'total_days' => count($heatmapData),
                    'positive_days' => collect($heatmapData)->where('value', '>', 0)->count(),
                    'negative_days' => collect($heatmapData)->where('value', '<', 0)->count(),
                    'max_gain' => collect($heatmapData)->max('value'),
                    'max_loss' => collect($heatmapData)->min('value'),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('AnalyticsController activityHeatmap error', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Unable to generate activity heatmap',
                'heatmap' => []
            ], 500);
        }
    }

    /**
     * Get Best and Worst Days
     */
    public function bestWorstDays(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            $days = 30; // Last 30 days
            
            $startDate = Carbon::now()->subDays($days);
            
            $query = DB::table('corporation_wallet_journals')
                ->where('date', '>=', $startDate)
                ->selectRaw('
                    DATE(date) as day,
                    SUM(amount) as net_flow,
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses
                ')
                ->groupBy('day');

            if ($corporationId) {
                $query->where('corporation_id', $corporationId);
                $query = JournalFilters::excludeInternalTransfers($query, (int) $corporationId);
            } else {
                $query = JournalFilters::excludeInternalTransfers($query);
            }

            $data = $query->get();

            // Sort and get top/bottom 5
            $bestDays = $data->sortByDesc('income')->take(5)->map(function ($day) {
                return [
                    'date' => Carbon::parse($day->day)->format('M d, Y'),
                    'income' => (float)$day->income,
                    'net_flow' => (float)$day->net_flow,
                ];
            });
            
            $worstDays = $data->sortByDesc('expenses')->take(5)->map(function ($day) {
                return [
                    'date' => Carbon::parse($day->day)->format('M d, Y'),
                    'expenses' => (float)$day->expenses,
                    'net_flow' => (float)$day->net_flow,
                ];
            });
            
            return response()->json([
                'best_days' => $bestDays->values(),
                'worst_days' => $worstDays->values(),
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => Carbon::now()->format('Y-m-d'),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('AnalyticsController bestWorstDays error', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Unable to calculate best/worst days',
                'best_days' => [],
                'worst_days' => []
            ], 500);
        }
    }

    /**
     * Get Weekly Patterns
     */
    public function weeklyPatterns(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            $weeks = 12; // Last 12 weeks
            
            $startDate = Carbon::now()->subWeeks($weeks)->startOfWeek();
            
            $query = DB::table('corporation_wallet_journals')
                ->where('date', '>=', $startDate)
                ->selectRaw('
                    DAYOFWEEK(date) as day_of_week,
                    AVG(amount) as avg_flow,
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) / ' . $weeks . ' as avg_income,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) / ' . $weeks . ' as avg_expenses
                ')
                ->groupBy('day_of_week')
                ->orderBy('day_of_week');

            if ($corporationId) {
                $query->where('corporation_id', $corporationId);
                $query = JournalFilters::excludeInternalTransfers($query, (int) $corporationId);
            } else {
                $query = JournalFilters::excludeInternalTransfers($query);
            }

            $data = $query->get();
            
            // Map day numbers to names
            $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            
            $patterns = [];
            foreach ($data as $day) {
                $patterns[] = [
                    'day' => $dayNames[$day->day_of_week - 1] ?? 'Unknown',
                    'avg_income' => (float)$day->avg_income,
                    'avg_expenses' => (float)$day->avg_expenses,
                    'net_flow' => (float)$day->avg_flow,
                ];
            }
            
            return response()->json([
                'patterns' => $patterns,
                'best_day' => collect($patterns)->sortByDesc('net_flow')->first(),
                'worst_day' => collect($patterns)->sortBy('net_flow')->first(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('AnalyticsController weeklyPatterns error', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Unable to calculate weekly patterns',
                'patterns' => []
            ], 500);
        }
    }

    /**
     * Get Division Performance Metrics
     */
    public function divisionPerformance(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            if (!$corporationId) {
                // Try to get first available
                $corporationId = DB::table('corporation_wallet_balances')
                    ->whereNotNull('corporation_id')
                    ->value('corporation_id');
            }
            
            if (!$corporationId) {
                return response()->json(['divisions' => []]);
            }
            
            // Get current balances
            $currentBalances = DB::table('corporation_wallet_balances')
                ->where('corporation_id', $corporationId)
                ->get()
                ->keyBy('division');
            
            // Get monthly income/expense per division
            // Per-division aggregation is especially sensitive to inter-division
            // transfers: both source and destination divisions show inflated activity.
            $currentMonth = Carbon::now()->startOfMonth();
            $monthlyQuery = DB::table('corporation_wallet_journals')
                ->where('corporation_id', $corporationId)
                ->whereMonth('date', $currentMonth->month)
                ->whereYear('date', $currentMonth->year);
            $monthlyQuery = JournalFilters::excludeInternalTransfers($monthlyQuery, (int) $corporationId);

            $monthlyData = $monthlyQuery
                ->selectRaw('
                    division,
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses
                ')
                ->groupBy('division')
                ->get()
                ->keyBy('division');
            
            // Get division names
            $divisionNames = $this->getDivisionNames($corporationId);
            
            $performance = [];
            
            foreach ($currentBalances as $divisionId => $balance) {
                $monthData = $monthlyData->get($divisionId);
                
                $income = $monthData ? (float)$monthData->income : 0;
                $expenses = $monthData ? (float)$monthData->expenses : 0;
                $currentBalance = (float)$balance->balance;
                
                // Calculate ROI (Return on Investment)
                $roi = $currentBalance > 0 ? (($income - $expenses) / $currentBalance) * 100 : 0;
                
                // Calculate efficiency (income per ISK held)
                $efficiency = $currentBalance > 0 ? ($income / $currentBalance) : 0;
                
                // Determine trend (simplified)
                $trend = ($income - $expenses) > 0 ? 'up' : 'down';
                
                $performance[] = [
                    'division_id' => $divisionId,
                    'name' => $divisionNames[$divisionId] ?? "Division $divisionId",
                    'balance' => $currentBalance,
                    'monthly_income' => $income,
                    'monthly_expense' => $expenses,
                    'roi' => round($roi, 2),
                    'efficiency' => round($efficiency, 3),
                    'trend' => $trend,
                    'net_flow' => $income - $expenses,
                ];
            }
            
            // Sort by ROI
            usort($performance, function($a, $b) {
                return $b['roi'] <=> $a['roi'];
            });
            
            return response()->json([
                'divisions' => $performance,
                'summary' => [
                    'best_performer' => $performance[0] ?? null,
                    'worst_performer' => end($performance) ?: null,
                    'total_income' => array_sum(array_column($performance, 'monthly_income')),
                    'total_expenses' => array_sum(array_column($performance, 'monthly_expense')),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('AnalyticsController divisionPerformance error', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Unable to calculate division performance',
                'divisions' => []
            ], 500);
        }
    }

    /**
     * Get Executive Summary
     */
    public function executiveSummary(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            // Gather all key metrics
            $healthData = json_decode($this->healthScore($request)->getContent(), true);
            $burnData = json_decode($this->burnRate($request)->getContent(), true);
            $ratiosData = json_decode($this->financialRatios($request)->getContent(), true);
            
            // Generate insights
            $insights = [];
            
            // Health insight
            if ($healthData['score'] < 50) {
                $insights[] = "Financial health score is below 50, indicating potential cash flow issues";
            } else {
                $insights[] = "Financial health score is strong at {$healthData['score']}/100";
            }
            
            // Burn rate insight
            if ($burnData['days_of_cash'] < 30) {
                $insights[] = "Critical: Only {$burnData['days_of_cash']} days of cash remaining at current burn rate";
            } elseif ($burnData['days_of_cash'] < 90) {
                $insights[] = "Warning: {$burnData['days_of_cash']} days of cash remaining";
            } else {
                $insights[] = "Healthy cash runway with {$burnData['days_of_cash']} days of operating capital";
            }
            
            // Growth insight
            if ($ratiosData['growth_rate'] > 0) {
                $insights[] = "Income growing at {$ratiosData['growth_rate']}% month-over-month";
            } else {
                $insights[] = "Income declined by " . abs($ratiosData['growth_rate']) . "% compared to last month";
            }
            
            // Profitability insight
            if ($ratiosData['income_expense_ratio'] > 1) {
                $insights[] = "Operations are profitable with income/expense ratio of {$ratiosData['income_expense_ratio']}";
            } else {
                $insights[] = "Operating at a loss with income/expense ratio of {$ratiosData['income_expense_ratio']}";
            }
            
            // Generate recommendations
            $recommendations = [];
            
            if ($burnData['days_of_cash'] < 60) {
                $recommendations[] = "Urgent: Reduce expenses or increase income sources to extend cash runway";
            }
            
            if ($ratiosData['volatility'] > 50) {
                $recommendations[] = "High volatility detected - consider diversifying income sources";
            }
            
            if ($ratiosData['liquidity_ratio'] < 1) {
                $recommendations[] = "Low liquidity - maintain higher cash reserves for operational stability";
            }
            
            if ($ratiosData['growth_rate'] < 0) {
                $recommendations[] = "Investigate declining revenue and identify new income opportunities";
            }
            
            // Risk assessment
            $riskLevel = 'Low';
            $riskFactors = [];
            
            if ($burnData['days_of_cash'] < 30) {
                $riskLevel = 'Critical';
                $riskFactors[] = "Imminent cash depletion";
            } elseif ($burnData['days_of_cash'] < 90 || $healthData['score'] < 40) {
                $riskLevel = 'High';
                $riskFactors[] = "Limited cash runway";
            } elseif ($ratiosData['volatility'] > 50 || $ratiosData['income_expense_ratio'] < 1) {
                $riskLevel = 'Medium';
                $riskFactors[] = "Income volatility";
            }
            
            if ($ratiosData['income_expense_ratio'] < 0.8) {
                $riskFactors[] = "Significant operating losses";
            }
            
            return response()->json([
                'generated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'insights' => $insights,
                'recommendations' => $recommendations,
                'risk_assessment' => [
                    'level' => $riskLevel,
                    'factors' => $riskFactors,
                ],
                'key_metrics' => [
                    'health_score' => $healthData['score'],
                    'days_of_cash' => $burnData['days_of_cash'],
                    'growth_rate' => $ratiosData['growth_rate'],
                    'profitability_ratio' => $ratiosData['income_expense_ratio'],
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('AnalyticsController executiveSummary error', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Unable to generate executive summary',
                'insights' => [],
                'recommendations' => []
            ], 500);
        }
    }

    /**
     * Get last month's closing balance
     */
    public function lastMonthBalance(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            // Get last month's last day
            $lastMonth = Carbon::now()->subMonth()->endOfMonth();
            $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
            
            // Get the balance at the end of last month
            $query = DB::table('corporation_wallet_journals')
                ->where('date', '<=', $lastMonth)
                ->where('date', '>=', $lastMonthStart);

            if ($corporationId) {
                $query->where('corporation_id', $corporationId);
                $query = JournalFilters::excludeInternalTransfers($query, (int) $corporationId);
            } else {
                $query = JournalFilters::excludeInternalTransfers($query);
            }

            // Get the last balance entry for the month
            $lastEntry = $query->orderBy('date', 'desc')
                ->orderBy('id', 'desc')
                ->first();
            
            $closingBalance = 0;
            
            if ($lastEntry) {
                // Use the balance from the last journal entry
                $closingBalance = (float)$lastEntry->balance;
            } else {
                // If no journal entries, try to get from corporation_wallet_balances
                $balanceQuery = DB::table('corporation_wallet_balances')
                    ->selectRaw('SUM(balance) as total');
                
                if ($corporationId) {
                    $balanceQuery->where('corporation_id', $corporationId);
                }
                
                $result = $balanceQuery->first();
                $closingBalance = $result ? (float)$result->total : 0;
            }
            
            return response()->json([
                'closing_balance' => $closingBalance,
                'month' => $lastMonthStart->format('Y-m'),
                'date' => $lastMonth->format('Y-m-d'),
                'corporation_id' => $corporationId
            ]);
            
        } catch (\Exception $e) {
            Log::error('AnalyticsController lastMonthBalance error', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Unable to fetch last month balance',
                'closing_balance' => 0,
                'month' => Carbon::now()->subMonth()->format('Y-m')
            ], 500);
        }
    }
    
    /**
     * Get division-specific daily cash flow
     */
    public function divisionDailyCashFlow(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            $divisionId = $request->get('division_id');
            $days = min(max((int)$request->get('days', 30), 7), 90);
            
            if (!$corporationId) {
                return response()->json([
                    'error' => 'Corporation ID required for division data',
                    'labels' => [],
                    'datasets' => []
                ], 400);
            }
            
            if (!$divisionId) {
                return response()->json([
                    'error' => 'Division ID required',
                    'labels' => [],
                    'datasets' => []
                ], 400);
            }
            
            $startDate = Carbon::now()->subDays($days);
            
            // Per-division charts are especially sensitive to inter-division
            // transfers, since the transfer half-pair lives entirely in one
            // division and inflates both income and expense totals there.
            $query = DB::table('corporation_wallet_journals')
                ->where('corporation_id', $corporationId)
                ->where('division', $divisionId)
                ->whereDate('date', '>=', $startDate);
            $query = JournalFilters::excludeInternalTransfers($query, (int) $corporationId);

            $query = $query->selectRaw('
                    DATE(date) as day,
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as daily_income,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as daily_expenses,
                    SUM(amount) as net_flow,
                    COUNT(*) as transaction_count
                ')
                ->groupBy('day')
                ->orderBy('day');

            $dailyData = $query->get();
            
            // Get division name - using the helper method properly
            $divisionNames = $this->getDivisionNames($corporationId);
            $divisionName = $divisionNames[$divisionId] ?? "Division {$divisionId}";
            
            // Format for chart
            $labels = [];
            $income = [];
            $expenses = [];
            $netFlow = [];
            $cumulative = 0;
            $cumulativeFlow = [];
            
            foreach ($dailyData as $day) {
                $labels[] = Carbon::parse($day->day)->format('M d');
                $income[] = (float)$day->daily_income;
                $expenses[] = -(float)$day->daily_expenses;
                $netFlow[] = (float)$day->net_flow;
                
                $cumulative += (float)$day->net_flow;
                $cumulativeFlow[] = $cumulative;
            }
            
            // Calculate statistics
            $totalIncome = array_sum($income);
            $totalExpenses = abs(array_sum($expenses));
            $avgDailyFlow = count($netFlow) > 0 ? array_sum($netFlow) / count($netFlow) : 0;
            
            return response()->json([
                'division_id' => $divisionId,
                'division_name' => $divisionName,
                'labels' => $labels,
                'datasets' => [
                    'income' => $income,
                    'expenses' => $expenses,
                    'net_flow' => $netFlow,
                    'cumulative' => $cumulativeFlow
                ],
                'statistics' => [
                    'total_income' => $totalIncome,
                    'total_expenses' => $totalExpenses,
                    'net_total' => $totalIncome - $totalExpenses,
                    'average_daily_flow' => round($avgDailyFlow, 2),
                    'days_positive' => count(array_filter($netFlow, function($v) { return $v > 0; })),
                    'days_negative' => count(array_filter($netFlow, function($v) { return $v < 0; })),
                ],
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => Carbon::now()->format('Y-m-d'),
                    'days' => $days
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('AnalyticsController divisionDailyCashFlow error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'corporation_id' => $request->get('corporation_id'),
                'division_id' => $request->get('division_id')
            ]);
            
            return response()->json([
                'error' => 'Unable to fetch division cash flow data',
                'labels' => [],
                'datasets' => []
            ], 500);
        }
    }
    
    /**
     * Get all divisions list with basic info
     */
    public function divisionsList(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            if (!$corporationId) {
                $corporationId = DB::table('corporation_wallet_balances')
                    ->whereNotNull('corporation_id')
                    ->value('corporation_id');
            }
            
            if (!$corporationId) {
                return response()->json(['divisions' => []]);
            }
            
            // Get current balances and division info
            $divisions = DB::table('corporation_wallet_balances')
                ->where('corporation_id', $corporationId)
                ->get();
            
            $divisionNames = $this->getDivisionNames($corporationId);
            
            $result = [];
            foreach ($divisions as $div) {
                $result[] = [
                    'id' => $div->division,
                    'name' => $divisionNames[$div->division] ?? "Division {$div->division}",
                    'balance' => (float)$div->balance
                ];
            }
            
            // Sort by division ID
            usort($result, function($a, $b) {
                return $a['id'] <=> $b['id'];
            });
            
            return response()->json([
                'divisions' => $result,
                'corporation_id' => $corporationId
            ]);
            
        } catch (\Exception $e) {
            Log::error('AnalyticsController divisionsList error', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Unable to fetch divisions list',
                'divisions' => []
            ], 500);
        }
    }

    // ========== HELPER METHODS ==========
    
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
    
    /**
     * Calculate intensity for heatmap
     */
    private function calculateIntensity($value, $allValues)
    {
        if (empty($allValues)) {
            return 0;
        }
        
        $min = min($allValues);
        $max = max($allValues);
        
        if ($max == $min) {
            return 0.5;
        }
        
        // Normalize to 0-1 scale
        $normalized = ($value - $min) / ($max - $min);
        
        // Convert to intensity (1-10)
        return round($normalized * 10);
    }
    
    /**
     * Get division names
     */
    private function getDivisionNames($corporationId)
    {
        try {
            $divisions = DB::table('corporation_divisions')
                ->where('corporation_id', $corporationId)
                ->pluck('name', 'division')
                ->toArray();
            
            // Fill in any missing with defaults
            for ($i = 1; $i <= 7; $i++) {
                if (!isset($divisions[$i]) || empty($divisions[$i])) {
                    $divisions[$i] = "Division $i";
                }
            }
            
            return $divisions;
            
        } catch (\Exception $e) {
            $divisions = [];
            for ($i = 1; $i <= 7; $i++) {
                $divisions[$i] = "Division $i";
            }
            return $divisions;
        }
    }

    /**
     * Top Contributors leaderboard for the Director view's Top Contributors tab.
     * Reads from the precomputed corpwalletmanager_character_contributions cache.
     */
    public function topContributors(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            if (! $corporationId) {
                return response()->json([
                    'success'      => false,
                    'message'      => 'Corporation not selected.',
                    'contributors' => [],
                ], 400);
            }

            $period = $request->get('period');
            if (! preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
                $period = Carbon::now()->format('Y-m');
            }

            $limit = (int) $request->get('limit', 20);

            $result = app(\CorpWalletManager\Services\ContributionService::class)
                ->getTopContributors((int) $corporationId, $period, $limit);

            return response()->json([
                'success'            => true,
                'period'             => $result['period'],
                'mm_available'       => $result['mm_available'],
                'has_alliance_tax'   => $result['has_alliance_tax'] ?? false,
                'alliance_tax_rates' => $result['alliance_tax_rates'] ?? [],
                'contributors'       => $result['contributors'],
            ]);
        } catch (\Exception $e) {
            Log::error('topContributors failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success'      => false,
                'message'      => 'Failed to load top contributors.',
                'contributors' => [],
            ], 500);
        }
    }

    /**
     * Composite payload powering the two supporting charts above the
     * Top Contributors leaderboard table: the Contribution
     * Concentration pie (Pareto split Top 1 / Top 2-5 / Top 6-10 /
     * Everyone else) and the Members vs External Contributors stacked
     * bar (current + prior period, each split into the member share
     * vs the external share).
     *
     * Both shapes are returned in a single response so the frontend
     * makes one round trip instead of two when the tab opens. Same
     * defensive predicates as the leaderboard so the three surfaces
     * on the same screen reconcile.
     *
     * 5-minute Redis cache keyed by `cwm:contributor-mix:{corp}:{period}`
     * mirrors the personalContribution / personalWalletStats pattern;
     * the contribution cache itself updates hourly so a 5-minute TTL
     * is at most ~5 minutes staler than the upstream without serving
     * wildly outdated numbers.
     */
    public function contributorMix(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            if (! $corporationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Corporation not selected.',
                ], 400);
            }

            $period = $request->get('period');
            if (! preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
                $period = Carbon::now()->format('Y-m');
            }

            $cacheKey = sprintf('cwm:contributor-mix:%d:%s', (int) $corporationId, $period);
            $ttl      = 300; // 5 minutes
            $cached   = Cache::get($cacheKey);
            if (is_array($cached)) {
                return response()->json($cached);
            }

            $result = app(\CorpWalletManager\Services\ContributionService::class)
                ->getContributorMix((int) $corporationId, $period);

            $payload = [
                'success'            => true,
                'corporation_id'     => $result['corporation_id'],
                'period'             => $result['period'],
                'concentration'      => $result['concentration'],
                'member_vs_external' => $result['member_vs_external'],
            ];

            Cache::put($cacheKey, $payload, $ttl);

            return response()->json($payload);
        } catch (\Exception $e) {
            Log::error('contributorMix failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success'            => false,
                'message'            => 'Failed to load contributor mix.',
                'concentration'      => ['total' => 0.0, 'buckets' => []],
                'member_vs_external' => null,
            ], 500);
        }
    }

    /**
     * Profit Attribution by activity for the Director view's Profit
     * Attribution tab. Returns a per-activity aggregate of the corp's
     * contribution income for the period (ratting / mission / industry
     * / tax_payment / donation_voluntary when MM is installed; merged
     * 'donation' bucket otherwise), with member counts, % of total
     * profit, average per contributing member, and trend vs the prior
     * calendar month.
     *
     * Where Top Contributors answers "who contributed?", this answers
     * "what activity types drove the income?" so directors can decide
     * where to invest corp resources.
     */
    public function profitAttribution(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            if (! $corporationId) {
                return response()->json([
                    'success'     => false,
                    'message'     => 'Corporation not selected.',
                    'by_activity' => [],
                ], 400);
            }

            $period = $request->get('period');
            if (! preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
                $period = Carbon::now()->format('Y-m');
            }

            $result = app(\CorpWalletManager\Services\ContributionService::class)
                ->getProfitAttribution((int) $corporationId, $period);

            return response()->json([
                'success'                  => true,
                'corporation_id'           => $result['corporation_id'],
                'period'                   => $result['period'],
                'mm_available'             => $result['mm_available'],
                'total_contribution'       => $result['total_contribution'],
                'prior_total_contribution' => $result['prior_total_contribution'],
                'by_activity'              => $result['by_activity'],
            ]);
        } catch (\Exception $e) {
            Log::error('profitAttribution failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success'     => false,
                'message'     => 'Failed to load profit attribution.',
                'by_activity' => [],
            ], 500);
        }
    }

    /**
     * Trailing-N-months profit attribution stacked-bar trend for the
     * Director view's Profit Attribution tab. Mirrors the
     * single-period profitAttribution endpoint shape philosophy so
     * the two together give the operator a hybrid (snapshot +
     * historical context) view. See
     * ContributionService::getProfitAttributionTrend for the data
     * model and MM-conditional bucket shape.
     */
    public function profitAttributionTrend(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            if (! $corporationId) {
                return response()->json([
                    'success'    => false,
                    'message'    => 'Corporation not selected.',
                    'categories' => [],
                ], 400);
            }

            $months = (int) $request->get('months', 12);
            if ($months < 1 || $months > 24) {
                $months = 12;
            }

            $result = app(\CorpWalletManager\Services\ContributionService::class)
                ->getProfitAttributionTrend((int) $corporationId, $months);

            return response()->json([
                'success'        => true,
                'corporation_id' => $result['corporation_id'],
                'months'         => $result['months'],
                'mm_available'   => $result['mm_available'],
                'periods'        => $result['periods'],
                'categories'     => $result['categories'],
            ]);
        } catch (\Exception $e) {
            Log::error('profitAttributionTrend failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success'    => false,
                'message'    => 'Failed to load profit attribution trend.',
                'categories' => [],
            ], 500);
        }
    }

    /**
     * Expense Attribution by category for the Director view's
     * Expense Attribution tab. Single-month snapshot: per-category
     * totals, journal-row counts, % of total, and trend vs the
     * immediately preceding calendar month.
     *
     * Counterpart to profitAttribution - the two together answer
     * "where did corp ISK come from / where did it go" for the same
     * calendar window. See ExpenseAttributionService for the
     * taxonomy and data model.
     */
    public function expenseAttribution(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            if (! $corporationId) {
                return response()->json([
                    'success'     => false,
                    'message'     => 'Corporation not selected.',
                    'by_category' => [],
                ], 400);
            }

            $period = $request->get('period');
            if (! preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
                $period = Carbon::now()->format('Y-m');
            }

            $result = app(\CorpWalletManager\Services\ExpenseAttributionService::class)
                ->getCurrentPeriod((int) $corporationId, $period);

            return response()->json([
                'success'             => true,
                'corporation_id'      => $result['corporation_id'],
                'period'              => $result['period'],
                'total_expense'       => $result['total_expense'],
                'prior_total_expense' => $result['prior_total_expense'],
                'by_category'         => $result['by_category'],
            ]);
        } catch (\Exception $e) {
            Log::error('expenseAttribution failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success'     => false,
                'message'     => 'Failed to load expense attribution.',
                'by_category' => [],
            ], 500);
        }
    }

    /**
     * Trailing-N-months expense attribution stacked-bar trend for
     * the Director view's Expense Attribution tab. Pivots the
     * per-period per-category aggregate so each category surfaces a
     * flat series of N totals (oldest first). See
     * ExpenseAttributionService::getTrend for the data model.
     */
    public function expenseAttributionTrend(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            if (! $corporationId) {
                return response()->json([
                    'success'    => false,
                    'message'    => 'Corporation not selected.',
                    'categories' => [],
                ], 400);
            }

            $months = (int) $request->get('months', 12);
            if ($months < 1 || $months > 24) {
                $months = 12;
            }

            // 5-minute cache: the trend reads raw journals over a 12-month
            // window which is the heaviest analytics query in the plugin.
            // The underlying journal only refreshes hourly via SeAT's ESI
            // sync, so a 5-minute TTL keeps repeat tab-opens instant without
            // ever serving badly stale numbers.
            $cacheKey = 'cwm:expense-attribution-trend:' . (int) $corporationId . ':' . $months;
            $result = Cache::remember($cacheKey, 300, function () use ($corporationId, $months) {
                return app(\CorpWalletManager\Services\ExpenseAttributionService::class)
                    ->getTrend((int) $corporationId, $months);
            });

            return response()->json([
                'success'        => true,
                'corporation_id' => $result['corporation_id'],
                'months'         => $result['months'],
                'periods'        => $result['periods'],
                'categories'     => $result['categories'],
            ]);
        } catch (\Exception $e) {
            Log::error('expenseAttributionTrend failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success'    => false,
                'message'    => 'Failed to load expense attribution trend.',
                'categories' => [],
            ], 500);
        }
    }

    /**
     * Alliance tax reconciliation for the Director view's Alliance Tax
     * tab. Returns a per-period view comparing expected alliance tax
     * (calculated from per-bucket rates × corp-wide income) against
     * actual alliance tax (sum of outgoing payments to configured
     * recipient ids). See AllianceTaxService for the data model.
     */
    public function allianceTaxReconciliation(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            if (! $corporationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Corporation not selected.',
                    'periods' => [],
                ], 400);
            }

            $months = max(1, min(36, (int) $request->get('months', 6)));

            $result = app(\CorpWalletManager\Services\AllianceTaxService::class)
                ->getReconciliation((int) $corporationId, $months);

            return response()->json([
                'success'              => true,
                'corporation_id'       => $result['corporation_id'],
                'months'               => $result['months'],
                'recipient_ids'        => $result['recipient_ids'],
                'description_keywords' => $result['description_keywords'],
                'has_recipients'       => $result['has_recipients'],
                'has_keywords'         => $result['has_keywords'],
                'has_match_rules'      => $result['has_match_rules'],
                'rates'                => $result['rates'],
                'periods'              => $result['periods'],
            ]);
        } catch (\Exception $e) {
            Log::error('allianceTaxReconciliation failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load alliance tax reconciliation.',
                'periods' => [],
            ], 500);
        }
    }
}
