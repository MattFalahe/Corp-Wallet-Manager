<?php

namespace Seat\CorpWalletManager\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Custom Date Range Report Extension for ReportService
 * Add this method to your ReportService.php
 */
trait CustomReportTrait
{
    /**
     * Generate Custom Date Range Report with enhanced analytics
     */
    public function generateCustomRangeReport($startDate, $endDate, $options = [])
    {
        // Parse dates
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        
        // Validate date range
        if ($start->gt($end)) {
            throw new \InvalidArgumentException('Start date must be before end date');
        }
        
        $daysDiff = $start->diffInDays($end);
        
        $data = [
            'period' => $start->format('M j, Y') . ' - ' . $end->format('M j, Y'),
            'period_type' => 'custom',
            'days_covered' => $daysDiff + 1,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'corporation_id' => $this->corporationId,
            'generated_at' => now(),
        ];
        
        // Get corporation name
        try {
            $corpInfo = DB::table('corporation_infos')
                ->where('corporation_id', $this->corporationId)
                ->first();
            $data['corporation_name'] = $corpInfo ? $corpInfo->name : 'Corporation #' . $this->corporationId;
        } catch (\Exception $e) {
            $data['corporation_name'] = 'Corporation #' . $this->corporationId;
        }
        
        // Get transaction statistics for the period
        $stats = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$start, $end])
            ->selectRaw('
                COUNT(*) as total_transactions,
                COUNT(DISTINCT DATE(date)) as active_days,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_expenses,
                SUM(amount) as net_change,
                AVG(amount) as avg_transaction,
                MAX(amount) as largest_income,
                MIN(amount) as largest_expense,
                STDDEV(amount) as volatility
            ')
            ->first();
        
        // Type cast statistics
        if ($stats) {
            $stats->total_transactions = (int)$stats->total_transactions;
            $stats->active_days = (int)$stats->active_days;
            $stats->total_income = (float)$stats->total_income;
            $stats->total_expenses = (float)$stats->total_expenses;
            $stats->net_change = (float)$stats->net_change;
            $stats->avg_transaction = (float)$stats->avg_transaction;
            $stats->largest_income = (float)$stats->largest_income;
            $stats->largest_expense = (float)$stats->largest_expense;
            $stats->volatility = (float)$stats->volatility;
            
            // Calculate daily averages
            $stats->avg_daily_income = $daysDiff > 0 ? $stats->total_income / ($daysDiff + 1) : 0;
            $stats->avg_daily_expenses = $daysDiff > 0 ? $stats->total_expenses / ($daysDiff + 1) : 0;
            $stats->avg_daily_transactions = $daysDiff > 0 ? $stats->total_transactions / ($daysDiff + 1) : 0;
            $stats->activity_rate = $daysDiff > 0 ? ($stats->active_days / ($daysDiff + 1)) * 100 : 0;
        } else {
            $stats = $this->getEmptyStats();
        }
        
        $data['statistics'] = $stats;
        
        // Get balance at start and end of period
        $this->calculatePeriodBalances($data, $start, $end);
        
        // Get top transaction types
        $data['top_income_sources'] = $this->getTopTransactionTypesForPeriod($start, $end, 'income', 5);
        $data['top_expense_sources'] = $this->getTopTransactionTypesForPeriod($start, $end, 'expense', 5);
        
        // Division breakdown for the period
        $data['division_breakdown'] = $this->getDivisionBreakdownForPeriod($start, $end);
        
        // Weekly breakdown if period > 7 days
        if ($daysDiff > 7) {
            $data['weekly_breakdown'] = $this->getWeeklyBreakdown($start, $end);
        } else {
            // Daily breakdown for shorter periods
            $data['daily_breakdown'] = $this->getDailyBreakdownForPeriod($start, $end);
        }
        
        // Activity heatmap
        $data['activity_heatmap'] = $this->getActivityHeatmap($start, $end);
        
        // Comparison with previous period of same length
        if ($options['include_comparison'] ?? true) {
            $data['previous_period'] = $this->getPreviousPeriodComparison($start, $end, $daysDiff);
        }
        
        // Performance metrics
        $data['performance_metrics'] = $this->calculatePeriodPerformance($data, $daysDiff);
        
        // Predictions for next similar period
        if ($options['include_forecast'] ?? false) {
            $data['forecast'] = $this->generatePeriodForecast($data, $daysDiff);
        }
        
        return $data;
    }
    
    /**
     * Calculate balance changes for the period
     */
    private function calculatePeriodBalances(&$data, $start, $end)
    {
        // Get balance just before start date
        $startingBalance = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->where('date', '<', $start)
            ->sum('amount');
        
        // Get all transactions during period
        $periodChange = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$start, $end])
            ->sum('amount');
        
        $data['starting_balance'] = (float)$startingBalance;
        $data['ending_balance'] = (float)($startingBalance + $periodChange);
        $data['change'] = (float)$periodChange;
        $data['change_percent'] = $data['starting_balance'] != 0 
            ? ($data['change'] / abs($data['starting_balance'])) * 100 
            : 0;
    }
    
    /**
     * Get top transaction types for a specific period
     */
    private function getTopTransactionTypesForPeriod($start, $end, $type = 'income', $limit = 5)
    {
        $query = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$start, $end])
            ->whereNotNull('ref_type')
            ->selectRaw('
                ref_type, 
                SUM(ABS(amount)) as total, 
                COUNT(*) as count,
                AVG(ABS(amount)) as average
            ')
            ->groupBy('ref_type')
            ->orderBy('total', 'desc')
            ->limit($limit);
        
        if ($type === 'income') {
            $query->where('amount', '>', 0);
        } else {
            $query->where('amount', '<', 0);
        }
        
        return $query->get()->map(function($item) {
            $item->total = (float)$item->total;
            $item->count = (int)$item->count;
            $item->average = (float)$item->average;
            return $item;
        });
    }
    
    /**
     * Get division breakdown for a specific period
     */
    private function getDivisionBreakdownForPeriod($start, $end)
    {
        return DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$start, $end])
            ->whereNotNull('division')
            ->selectRaw('
                division,
                SUM(amount) as net_flow,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses,
                COUNT(*) as transactions,
                COUNT(DISTINCT DATE(date)) as active_days
            ')
            ->groupBy('division')
            ->orderBy('net_flow', 'desc')
            ->get()
            ->map(function($item) {
                $item->net_flow = (float)$item->net_flow;
                $item->income = (float)$item->income;
                $item->expenses = (float)$item->expenses;
                $item->transactions = (int)$item->transactions;
                $item->active_days = (int)$item->active_days;
                
                // Try to get division name
                try {
                    $divInfo = DB::table('corporation_divisions')
                        ->where('corporation_id', $this->corporationId)
                        ->where('division', $item->division)
                        ->first();
                    $item->name = $divInfo ? $divInfo->name : 'Division ' . $item->division;
                } catch (\Exception $e) {
                    $item->name = 'Division ' . $item->division;
                }
                
                return $item;
            });
    }
    
    /**
     * Get weekly breakdown for longer periods
     */
    private function getWeeklyBreakdown($start, $end)
    {
        $weeks = [];
        $currentWeek = $start->copy()->startOfWeek();
        
        while ($currentWeek->lte($end)) {
            $weekEnd = $currentWeek->copy()->endOfWeek();
            if ($weekEnd->gt($end)) {
                $weekEnd = $end;
            }
            
            $weekData = DB::table('corporation_wallet_journals')
                ->where('corporation_id', $this->corporationId)
                ->whereBetween('date', [$currentWeek, $weekEnd])
                ->selectRaw('
                    COUNT(*) as transactions,
                    SUM(amount) as net_flow,
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses
                ')
                ->first();
            
            $weeks[] = [
                'week_start' => $currentWeek->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'week_label' => 'Week of ' . $currentWeek->format('M j'),
                'transactions' => (int)($weekData->transactions ?? 0),
                'net_flow' => (float)($weekData->net_flow ?? 0),
                'income' => (float)($weekData->income ?? 0),
                'expenses' => (float)($weekData->expenses ?? 0)
            ];
            
            $currentWeek->addWeek();
        }
        
        return $weeks;
    }
    
    /**
     * Get daily breakdown for shorter periods
     */
    private function getDailyBreakdownForPeriod($start, $end)
    {
        return DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$start, $end])
            ->selectRaw('
                DATE(date) as day,
                SUM(amount) as net_flow,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses,
                COUNT(*) as transactions
            ')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(function($item) {
                $item->net_flow = (float)$item->net_flow;
                $item->income = (float)$item->income;
                $item->expenses = (float)$item->expenses;
                $item->transactions = (int)$item->transactions;
                $item->day_name = Carbon::parse($item->day)->format('D, M j');
                $item->is_weekend = Carbon::parse($item->day)->isWeekend();
                return $item;
            });
    }
    
    /**
     * Get activity heatmap data
     */
    private function getActivityHeatmap($start, $end)
    {
        return DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$start, $end])
            ->selectRaw('
                HOUR(date) as hour,
                DAYOFWEEK(date) as day_of_week,
                COUNT(*) as transactions,
                SUM(amount) as net_flow
            ')
            ->groupBy('hour', 'day_of_week')
            ->get()
            ->map(function($item) {
                $item->hour = (int)$item->hour;
                $item->day_of_week = (int)$item->day_of_week;
                $item->transactions = (int)$item->transactions;
                $item->net_flow = (float)$item->net_flow;
                return $item;
            });
    }
    
    /**
     * Compare with previous period of same length
     */
    private function getPreviousPeriodComparison($start, $end, $daysDiff)
    {
        $prevEnd = $start->copy()->subDay();
        $prevStart = $prevEnd->copy()->subDays($daysDiff);
        
        $prevStats = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$prevStart, $prevEnd])
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_expenses,
                SUM(amount) as net_change
            ')
            ->first();
        
        return [
            'period' => $prevStart->format('M j') . ' - ' . $prevEnd->format('M j, Y'),
            'total_income' => (float)($prevStats->total_income ?? 0),
            'total_expenses' => (float)($prevStats->total_expenses ?? 0),
            'net_change' => (float)($prevStats->net_change ?? 0),
            'total_transactions' => (int)($prevStats->total_transactions ?? 0),
            'income_change' => isset($prevStats->total_income) && $prevStats->total_income > 0 
                ? ((($this->statistics->total_income ?? 0) - $prevStats->total_income) / $prevStats->total_income) * 100
                : 0,
            'expense_change' => isset($prevStats->total_expenses) && $prevStats->total_expenses > 0
                ? ((($this->statistics->total_expenses ?? 0) - $prevStats->total_expenses) / $prevStats->total_expenses) * 100
                : 0
        ];
    }
    
    /**
     * Calculate performance metrics for the period
     */
    private function calculatePeriodPerformance($data, $daysDiff)
    {
        $metrics = [];
        
        // Profitability
        $metrics['profit_margin'] = $data['statistics']->total_income > 0
            ? (($data['statistics']->total_income - $data['statistics']->total_expenses) / $data['statistics']->total_income) * 100
            : 0;
        
        // Daily burn rate
        $metrics['daily_burn_rate'] = $daysDiff > 0 
            ? $data['statistics']->total_expenses / ($daysDiff + 1)
            : 0;
        
        // Daily profit rate
        $metrics['daily_profit_rate'] = $daysDiff > 0
            ? ($data['statistics']->total_income - $data['statistics']->total_expenses) / ($daysDiff + 1)
            : 0;
        
        // Transaction intensity
        $metrics['transaction_intensity'] = $daysDiff > 0
            ? $data['statistics']->total_transactions / ($daysDiff + 1)
            : 0;
        
        // Volatility index (coefficient of variation)
        if ($data['statistics']->avg_transaction != 0 && $data['statistics']->volatility > 0) {
            $metrics['volatility_index'] = abs($data['statistics']->volatility / $data['statistics']->avg_transaction) * 100;
        } else {
            $metrics['volatility_index'] = 0;
        }
        
        // Performance rating
        $profitScore = min(100, max(0, 50 + ($metrics['profit_margin'] / 2)));
        $activityScore = min(100, ($data['statistics']->activity_rate ?? 0));
        $stabilityScore = max(0, 100 - $metrics['volatility_index']);
        
        $metrics['overall_performance'] = ($profitScore + $activityScore + $stabilityScore) / 3;
        
        return $metrics;
    }
    
    /**
     * Generate forecast for next similar period
     */
    private function generatePeriodForecast($data, $daysDiff)
    {
        // Simple forecast based on current period performance
        return [
            'next_period_start' => Carbon::parse($data['end_date'])->addDay()->format('Y-m-d'),
            'next_period_end' => Carbon::parse($data['end_date'])->addDays($daysDiff + 1)->format('Y-m-d'),
            'expected_income' => $data['statistics']->total_income * 1.05, // 5% growth assumption
            'expected_expenses' => $data['statistics']->total_expenses * 1.02, // 2% increase assumption
            'expected_net_change' => ($data['statistics']->total_income * 1.05) - ($data['statistics']->total_expenses * 1.02),
            'confidence' => 75 // Medium confidence for custom periods
        ];
    }
    
    /**
     * Get empty statistics object
     */
    private function getEmptyStats()
    {
        return (object)[
            'total_transactions' => 0,
            'active_days' => 0,
            'total_income' => 0,
            'total_expenses' => 0,
            'net_change' => 0,
            'avg_transaction' => 0,
            'largest_income' => 0,
            'largest_expense' => 0,
            'volatility' => 0,
            'avg_daily_income' => 0,
            'avg_daily_expenses' => 0,
            'avg_daily_transactions' => 0,
            'activity_rate' => 0
        ];
    }
}

// Add this trait to your existing ReportService class:
// class ReportService
// {
//     use CustomReportTrait;
//     
//     // ... existing code ...
// }
