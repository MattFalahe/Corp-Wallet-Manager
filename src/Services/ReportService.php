<?php

namespace Seat\CorpWalletManager\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Seat\CorpWalletManager\Models\MonthlyBalance;
use Seat\CorpWalletManager\Models\Settings;
use Seat\CorpWalletManager\Models\Prediction;
use Seat\CorpWalletManager\Models\DivisionBalance;

class ReportService
{
    protected $corporationId;
    
    public function __construct($corporationId = null)
    {
        $this->corporationId = $corporationId ?: Settings::getSetting('selected_corporation_id');
    }
    
    /**
     * Generate Monthly Report
     */
    public function generateMonthlyReport($month = null)
    {
        $month = $month ?: Carbon::now()->subMonth();
        $monthStr = $month->format('Y-m');
        
        $data = [
            'period' => $month->format('F Y'),
            'month_string' => $monthStr,
            'corporation_id' => $this->corporationId,
            'generated_at' => now(),
        ];
        
        // Get corporation name if available
        try {
            $corpInfo = DB::table('corporation_infos')
                ->where('corporation_id', $this->corporationId)
                ->first();
            $data['corporation_name'] = $corpInfo ? $corpInfo->name : 'Corporation #' . $this->corporationId;
        } catch (\Exception $e) {
            $data['corporation_name'] = 'Corporation #' . $this->corporationId;
        }
        
        // Get monthly balance
        $monthlyBalance = MonthlyBalance::where('corporation_id', $this->corporationId)
            ->where('month', $monthStr)
            ->first();
        
        $data['ending_balance'] = $monthlyBalance ? (float)$monthlyBalance->balance : 0;
        
        // Get previous month for comparison
        $prevMonth = $month->copy()->subMonth()->format('Y-m');
        $prevBalance = MonthlyBalance::where('corporation_id', $this->corporationId)
            ->where('month', $prevMonth)
            ->first();
        
        $data['starting_balance'] = $prevBalance ? (float)$prevBalance->balance : 0;
        $data['change'] = $data['ending_balance'] - $data['starting_balance'];
        $data['change_percent'] = $data['starting_balance'] > 0 
            ? ($data['change'] / abs($data['starting_balance'])) * 100 
            : 0;
        
        // Get transaction statistics
        $stats = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
            ->selectRaw('
                COUNT(*) as total_transactions,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_expenses,
                AVG(amount) as avg_transaction,
                MAX(amount) as largest_income,
                MIN(amount) as largest_expense
            ')
            ->first();
        
        // Ensure stats are properly typed
        if ($stats) {
            $stats->total_transactions = (int)$stats->total_transactions;
            $stats->total_income = (float)$stats->total_income;
            $stats->total_expenses = (float)$stats->total_expenses;
            $stats->avg_transaction = (float)$stats->avg_transaction;
            $stats->largest_income = (float)$stats->largest_income;
            $stats->largest_expense = (float)$stats->largest_expense;
        } else {
            // Create default stats if no data
            $stats = (object)[
                'total_transactions' => 0,
                'total_income' => 0,
                'total_expenses' => 0,
                'avg_transaction' => 0,
                'largest_income' => 0,
                'largest_expense' => 0
            ];
        }
        
        $data['statistics'] = $stats;
        
        // Get top income/expense categories
        $data['top_income_sources'] = $this->getTopTransactionTypes($month, 'income', 5);
        $data['top_expense_sources'] = $this->getTopTransactionTypes($month, 'expense', 5);
        
        // Division breakdown
        $data['division_breakdown'] = $this->getDivisionBreakdown($month);
        
        // Daily breakdown
        $data['daily_breakdown'] = $this->getDailyBreakdown($month);
        
        // Health metrics
        $data['health_metrics'] = $this->calculateHealthMetrics($month);
        
        // Activity patterns
        $data['activity_patterns'] = $this->getActivityPatterns($month);
        
        return $data;
    }
    
    /**
     * Generate Quarterly Report
     */
    public function generateQuarterlyReport($quarter = null, $year = null)
    {
        if (!$quarter) {
            $currentMonth = Carbon::now()->month;
            $quarter = ceil($currentMonth / 3);
            // If we're in the first month of a quarter, report on previous quarter
            if ($currentMonth % 3 == 1) {
                $quarter = $quarter > 1 ? $quarter - 1 : 4;
                $year = $quarter == 4 ? Carbon::now()->year - 1 : Carbon::now()->year;
            } else {
                $year = Carbon::now()->year;
            }
        }
        
        $year = $year ?: Carbon::now()->year;
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $quarter * 3;
        
        $data = [
            'period' => "Q{$quarter} {$year}",
            'quarter' => $quarter,
            'year' => $year,
            'corporation_id' => $this->corporationId,
            'generated_at' => now(),
            'months' => []
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
        
        // Aggregate 3 months of data
        for ($m = $startMonth; $m <= $endMonth; $m++) {
            $monthDate = Carbon::create($year, $m, 1);
            $monthReport = $this->generateMonthlyReport($monthDate);
            $data['months'][] = $monthReport;
        }
        
        // Calculate quarterly totals
        $data['total_income'] = 0;
        $data['total_expenses'] = 0;
        $data['total_transactions'] = 0;
        
        foreach ($data['months'] as $month) {
            $data['total_income'] += $month['statistics']->total_income;
            $data['total_expenses'] += $month['statistics']->total_expenses;
            $data['total_transactions'] += $month['statistics']->total_transactions;
        }
        
        $data['net_change'] = array_sum(array_column($data['months'], 'change'));
        
        // Get starting and ending balances for the quarter
        $data['starting_balance'] = $data['months'][0]['starting_balance'] ?? 0;
        $data['ending_balance'] = $data['months'][2]['ending_balance'] ?? 0;
        
        // Trend analysis
        $data['trend'] = $this->analyzeTrend($data['months']);
        
        // Performance metrics
        $data['performance_metrics'] = $this->calculateQuarterlyPerformance($data);
        
        return $data;
    }
    
    /**
     * Generate Daily Summary
     */
    public function generateDailySummary($date = null)
    {
        $date = $date ?: Carbon::yesterday();
        
        $data = [
            'date' => $date->format('Y-m-d'),
            'formatted_date' => $date->format('F j, Y'),
            'corporation_id' => $this->corporationId,
        ];
        
        // Get daily transactions
        $stats = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereDate('date', $date)
            ->selectRaw('
                COUNT(*) as transactions,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses,
                SUM(amount) as net_flow
            ')
            ->first();
        
        // Ensure proper typing
        if ($stats) {
            $stats->transactions = (int)$stats->transactions;
            $stats->income = (float)$stats->income;
            $stats->expenses = (float)$stats->expenses;
            $stats->net_flow = (float)$stats->net_flow;
        } else {
            $stats = (object)[
                'transactions' => 0,
                'income' => 0,
                'expenses' => 0,
                'net_flow' => 0
            ];
        }
        
        $data['summary'] = $stats;
        
        // Current balance
        $balance = DB::table('corporation_wallet_balances')
            ->where('corporation_id', $this->corporationId)
            ->sum('balance');
        
        $data['current_balance'] = (float)$balance;
        
        // Get predictions for next 7 days
        $predictions = Prediction::where('corporation_id', $this->corporationId)
            ->where('date', '>=', Carbon::today())
            ->where('date', '<=', Carbon::today()->addDays(7))
            ->orderBy('date')
            ->get();
        
        $data['week_forecast'] = $predictions;
        
        // Top transactions of the day
        $data['top_transactions'] = $this->getTopDailyTransactions($date);
        
        // Compare to 7-day average
        $weekAgo = $date->copy()->subDays(7);
        $weekStats = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$weekAgo, $date])
            ->selectRaw('
                AVG(CASE WHEN DATE(date) = ? THEN NULL ELSE amount END) as avg_daily_flow,
                AVG(CASE WHEN amount > 0 THEN amount ELSE 0 END) as avg_income,
                AVG(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as avg_expenses
            ', [$date->format('Y-m-d')])
            ->first();
        
        $data['week_comparison'] = $weekStats;
        
        return $data;
    }
    
    /**
     * Get top transaction types for a month
     */
    private function getTopTransactionTypes($month, $type = 'income', $limit = 5)
    {
        $query = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
            ->whereNotNull('ref_type')
            ->selectRaw('ref_type, SUM(ABS(amount)) as total, COUNT(*) as count')
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
            return $item;
        });
    }
    
    /**
     * Get division breakdown for a month
     */
    private function getDivisionBreakdown($month)
    {
        $divisions = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
            ->whereNotNull('division')
            ->selectRaw('
                division,
                SUM(amount) as net_flow,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses,
                COUNT(*) as transactions
            ')
            ->groupBy('division')
            ->orderBy('net_flow', 'desc')
            ->get();
        
        // Get division names if available
        foreach ($divisions as $division) {
            $division->net_flow = (float)$division->net_flow;
            $division->income = (float)$division->income;
            $division->expenses = (float)$division->expenses;
            $division->transactions = (int)$division->transactions;
            
            try {
                $divInfo = DB::table('corporation_divisions')
                    ->where('corporation_id', $this->corporationId)
                    ->where('division', $division->division)
                    ->first();
                $division->name = $divInfo ? $divInfo->name : 'Division ' . $division->division;
            } catch (\Exception $e) {
                $division->name = 'Division ' . $division->division;
            }
        }
        
        return $divisions;
    }
    
    /**
     * Get daily breakdown for a month
     */
    private function getDailyBreakdown($month)
    {
        return DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
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
                return $item;
            });
    }
    
    /**
     * Calculate health metrics for a month
     */
    private function calculateHealthMetrics($month)
    {
        $metrics = [];
        
        // Get current balance
        $currentBalance = DB::table('corporation_wallet_balances')
            ->where('corporation_id', $this->corporationId)
            ->sum('balance');
        $currentBalance = (float)$currentBalance;
        
        // Get monthly data
        $monthlyData = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
            ->selectRaw('
                COUNT(*) as transaction_count,
                AVG(amount) as avg_transaction,
                STDDEV(amount) as volatility,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_expenses
            ')
            ->first();
        
        // Liquidity Score (0-100): Based on current balance
        $targetBalance = 10000000000; // 10 billion ISK as target
        $metrics['liquidity_score'] = min(100, ($currentBalance / $targetBalance) * 100);
        
        // Stability Score (0-100): Based on volatility
        if ($monthlyData && $monthlyData->volatility && $monthlyData->avg_transaction != 0) {
            $coefficientOfVariation = abs($monthlyData->volatility / $monthlyData->avg_transaction);
            $metrics['stability_score'] = max(0, 100 - ($coefficientOfVariation * 20));
        } else {
            $metrics['stability_score'] = 100;
        }
        
        // Activity Score (0-100): Based on transaction count
        $targetTransactions = 1000; // Target 1000 transactions per month
        $metrics['activity_score'] = $monthlyData ? 
            min(100, ($monthlyData->transaction_count / $targetTransactions) * 100) : 0;
        
        // Efficiency Score (0-100): Income vs Expenses ratio
        if ($monthlyData && $monthlyData->total_expenses > 0) {
            $efficiency = ($monthlyData->total_income / $monthlyData->total_expenses);
            $metrics['efficiency_score'] = min(100, $efficiency * 50);
        } else {
            $metrics['efficiency_score'] = $monthlyData && $monthlyData->total_income > 0 ? 100 : 50;
        }
        
        // Overall Health Score
        $metrics['overall_health'] = (
            $metrics['liquidity_score'] * 0.3 +
            $metrics['stability_score'] * 0.2 +
            $metrics['activity_score'] * 0.2 +
            $metrics['efficiency_score'] * 0.3
        );
        
        return $metrics;
    }
    
    /**
     * Get activity patterns for a month
     */
    private function getActivityPatterns($month)
    {
        // Day of week analysis
        $dayOfWeek = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
            ->selectRaw('
                DAYOFWEEK(date) as day_num,
                DAYNAME(date) as day_name,
                COUNT(*) as transactions,
                SUM(amount) as net_flow
            ')
            ->groupBy('day_num', 'day_name')
            ->orderBy('day_num')
            ->get()
            ->map(function($item) {
                $item->transactions = (int)$item->transactions;
                $item->net_flow = (float)$item->net_flow;
                return $item;
            });
        
        // Hour of day analysis
        $hourOfDay = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereMonth('date', $month->month)
            ->whereYear('date', $month->year)
            ->selectRaw('
                HOUR(date) as hour,
                COUNT(*) as transactions,
                SUM(amount) as net_flow
            ')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(function($item) {
                $item->transactions = (int)$item->transactions;
                $item->net_flow = (float)$item->net_flow;
                return $item;
            });
        
        return [
            'by_day_of_week' => $dayOfWeek,
            'by_hour' => $hourOfDay,
            'busiest_day' => $dayOfWeek->sortByDesc('transactions')->first(),
            'most_profitable_day' => $dayOfWeek->sortByDesc('net_flow')->first(),
            'busiest_hour' => $hourOfDay->sortByDesc('transactions')->first()
        ];
    }
    
    /**
     * Analyze trend from monthly data
     */
    private function analyzeTrend($months)
    {
        if (count($months) < 2) {
            return 'insufficient_data';
        }
        
        $balances = array_column($months, 'ending_balance');
        
        // Calculate linear regression trend
        $n = count($balances);
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumX += $i;
            $sumY += $balances[$i];
            $sumXY += $i * $balances[$i];
            $sumX2 += $i * $i;
        }
        
        if (($n * $sumX2 - $sumX * $sumX) == 0) {
            return 'stable';
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $avgBalance = $sumY / $n;
        
        // Determine trend based on slope relative to average
        if ($avgBalance == 0) {
            return 'stable';
        }
        
        $slopePercent = ($slope / $avgBalance) * 100;
        
        if ($slopePercent > 5) {
            return 'improving';
        } elseif ($slopePercent < -5) {
            return 'declining';
        } else {
            return 'stable';
        }
    }
    
    /**
     * Calculate quarterly performance metrics
     */
    private function calculateQuarterlyPerformance($quarterData)
    {
        $metrics = [];
        
        // Growth rate
        if ($quarterData['starting_balance'] > 0) {
            $metrics['growth_rate'] = (($quarterData['ending_balance'] - $quarterData['starting_balance']) 
                / $quarterData['starting_balance']) * 100;
        } else {
            $metrics['growth_rate'] = 0;
        }
        
        // Average monthly income/expense
        $metrics['avg_monthly_income'] = $quarterData['total_income'] / 3;
        $metrics['avg_monthly_expenses'] = $quarterData['total_expenses'] / 3;
        $metrics['avg_monthly_profit'] = $metrics['avg_monthly_income'] - $metrics['avg_monthly_expenses'];
        
        // Profit margin
        if ($quarterData['total_income'] > 0) {
            $metrics['profit_margin'] = (($quarterData['total_income'] - $quarterData['total_expenses']) 
                / $quarterData['total_income']) * 100;
        } else {
            $metrics['profit_margin'] = 0;
        }
        
        // Transaction volume growth (compare to previous quarter if available)
        $prevQuarter = $quarterData['quarter'] > 1 ? $quarterData['quarter'] - 1 : 4;
        $prevYear = $quarterData['quarter'] > 1 ? $quarterData['year'] : $quarterData['year'] - 1;
        
        $prevQuarterTransactions = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereRaw('QUARTER(date) = ?', [$prevQuarter])
            ->whereYear('date', $prevYear)
            ->count();
        
        if ($prevQuarterTransactions > 0) {
            $metrics['transaction_growth'] = (($quarterData['total_transactions'] - $prevQuarterTransactions) 
                / $prevQuarterTransactions) * 100;
        } else {
            $metrics['transaction_growth'] = 0;
        }
        
        return $metrics;
    }
    
    /**
     * Get top daily transactions
     */
    private function getTopDailyTransactions($date, $limit = 5)
    {
        return DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereDate('date', $date)
            ->selectRaw('
                date,
                amount,
                ref_type,
                reason,
                division
            ')
            ->orderByRaw('ABS(amount) DESC')
            ->limit($limit)
            ->get()
            ->map(function($item) {
                $item->amount = (float)$item->amount;
                return $item;
            });
    }
}
