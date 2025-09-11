<?php
namespace Seat\CorpWalletManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Seat\CorpWalletManager\Models\Settings;
use Seat\CorpWalletManager\Models\Prediction;
use Seat\CorpWalletManager\Models\MonthlyBalance;

class WalletController extends Controller
{
    /**
     * Get corporation ID from request or settings
     */
    private function getCorporationId(Request $request)
    {
        // First check if it's passed in the request
        $corporationId = $request->get('corporation_id');
        
        // If not in request, check settings
        if (!$corporationId) {
            $corporationId = Settings::getSetting('selected_corporation_id');
        }
        
        // Validate it's numeric if set
        if ($corporationId && !is_numeric($corporationId)) {
            return null;
        }
        
        return $corporationId;
    }

    /**
     * Get corporation info (name) by ID
     */
    public function getCorporationInfo(Request $request)
    {
        try {
            $corporationId = $request->get('corporation_id');
            
            if (!$corporationId) {
                return response()->json(['name' => null]);
            }
            
            // Try to get from corporation_infos table
            $corpInfo = DB::table('corporation_infos')
                ->where('corporation_id', $corporationId)
                ->first();
            
            if ($corpInfo) {
                return response()->json([
                    'corporation_id' => $corporationId,
                    'name' => $corpInfo->name
                ]);
            }
            
            // Fallback: try corporation_divisions to at least get some name
            $division = DB::table('corporation_divisions')
                ->where('corporation_id', $corporationId)
                ->first();
            
            if ($division && isset($division->corporation_name)) {
                return response()->json([
                    'corporation_id' => $corporationId,
                    'name' => $division->corporation_name
                ]);
            }
            
            // No name found
            return response()->json([
                'corporation_id' => $corporationId,
                'name' => null
            ]);
            
        } catch (\Exception $e) {
            Log::error('getCorporationInfo error', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json(['name' => null]);
        }
    }
    
    // ========== VIEW METHODS ==========
    
    public function director()
    {
        try {
            return view('corpwalletmanager::director');
        } catch (\Exception $e) {
            Log::error('CorpWalletManager director view error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load director view. Please check logs.');
        }
    }

    public function member()
    {
        try {
            return view('corpwalletmanager::member');
        } catch (\Exception $e) {
            Log::error('CorpWalletManager member view error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Unable to load member view. Please check logs.');
        }
    }

    // ========== API METHODS ==========

    /**
     * Return the latest balance + prediction for this month.
     */
    public function latest(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);

            $today = Carbon::today();
            $monthStart = $today->copy()->startOfMonth();

            // Latest recorded balance 
            $balanceQuery = MonthlyBalance::where('month', $monthStart->format('Y-m'));
            
            if ($corporationId) {
                $balanceQuery->where('corporation_id', $corporationId);
            }
            
            $latest_balance = $balanceQuery->sum('balance') ?? 0;

            // Predicted balance for today
            $predictionQuery = Prediction::whereDate('date', $today);
            
            if ($corporationId) {
                $predictionQuery->where('corporation_id', $corporationId);
            }
            
            $predicted = $predictionQuery->sum('predicted_balance') ?? 0;

            return response()->json([
                'balance'   => (float)$latest_balance,
                'predicted' => (float)$predicted,
                'date' => $today->format('Y-m-d'),
                'month' => $monthStart->format('Y-m'),
                'corporation_id' => $corporationId,
            ]);

        } catch (\Exception $e) {
            Log::error('CorpWalletManager latest API error', [
                'error' => $e->getMessage(),
                'corporation_id' => $request->get('corporation_id')
            ]);
            
            return response()->json([
                'error' => 'Unable to fetch latest data',
                'balance' => 0,
                'predicted' => 0,
                'date' => Carbon::today()->format('Y-m-d'),
                'month' => Carbon::today()->startOfMonth()->format('Y-m'),
            ], 500);
        }
    }

    /**
     * Return monthly comparison (last 6 months).
     */
    public function monthlyComparison(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);

            $monthsToShow = min(max((int)$request->get('months', 6), 1), 24); // Between 1 and 24 months
            
            $startDate = Carbon::today()->subMonths($monthsToShow)->startOfMonth();

            $query = MonthlyBalance::where('month', '>=', $startDate->format('Y-m'))
                ->orderBy('month');
                
            if ($corporationId) {
                $query->where('corporation_id', $corporationId);
            }

            $balances = $query->get()
                ->groupBy('month')
                ->map(function ($rows) {
                    return $rows->sum('balance');
                });

            $labels = $balances->keys()->map(function ($month) {
                try {
                    return Carbon::createFromFormat('Y-m', $month)->format('M Y');
                } catch (\Exception $e) {
                    return $month; // Fallback to raw format
                }
            })->toArray();
            
            $data = $balances->values()->map(function ($value) {
                return (float)$value;
            })->toArray();

            return response()->json([
                'labels' => $labels,
                'data'   => $data,
                'months_requested' => $monthsToShow,
                'corporation_id' => $corporationId,
            ]);

        } catch (\Exception $e) {
            Log::error('CorpWalletManager monthly comparison API error', [
                'error' => $e->getMessage(),
                'corporation_id' => $request->get('corporation_id'),
                'months' => $request->get('months')
            ]);
            
            return response()->json([
                'error' => 'Unable to fetch monthly data',
                'labels' => [],
                'data' => [],
                'months_requested' => $request->get('months', 6),
                'corporation_id' => $request->get('corporation_id'),
            ], 500);
        }
    }

    /**
     * Get prediction data for charts
     */
    public function predictions(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);

            $days = min(max((int)$request->get('days', 30), 1), 365); // Between 1 and 365 days
            
            $startDate = Carbon::today();
            $endDate = $startDate->copy()->addDays($days);
            
            $query = Prediction::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->orderBy('date');
                
            if ($corporationId) {
                $query->where('corporation_id', $corporationId);
            }

            $predictions = $query->get()
                ->groupBy('date')
                ->map(function ($rows) {
                    return (float)$rows->sum('predicted_balance');
                });

            $labels = $predictions->keys()->toArray();
            $data = $predictions->values()->toArray();

            return response()->json([
                'labels' => $labels,
                'data' => $data,
                'days_requested' => $days,
                'corporation_id' => $corporationId,
            ]);

        } catch (\Exception $e) {
            Log::error('CorpWalletManager predictions API error', [
                'error' => $e->getMessage(),
                'corporation_id' => $request->get('corporation_id'),
                'days' => $request->get('days')
            ]);
            
            return response()->json([
                'error' => 'Unable to fetch prediction data',
                'labels' => [],
                'data' => [],
                'days_requested' => $request->get('days', 30),
                'corporation_id' => $request->get('corporation_id'),
            ], 500);
        }
    }

    /**
     * Get division breakdown data
     */
    public function divisionBreakdown(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            // Division breakdown requires a corporation ID
            if (!$corporationId) {
                // Try to get the first available corporation
                $corporationId = DB::table('corporation_wallet_balances')
                    ->whereNotNull('corporation_id')
                    ->value('corporation_id');
                    
                if (!$corporationId) {
                    return response()->json([
                        'labels' => [],
                        'data' => [],
                        'error' => 'No corporation data available'
                    ]);
                }
            }

            $month = $request->get('month', Carbon::now()->format('Y-m'));
            
            // Validate month format
            if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
                return response()->json(['error' => 'Invalid month format'], 400);
            }

            $divisions = \Seat\CorpWalletManager\Models\DivisionBalance::where('corporation_id', $corporationId)
                ->where('month', $month)
                ->orderBy('division_id')
                ->get();

            // Get actual division names from the database
            $divisionNames = $this->getAllDivisionNames($corporationId);
            
            $labels = $divisions->pluck('division_id')->map(function ($divId) use ($divisionNames) {
                return $divisionNames[$divId] ?? "Division $divId";
            })->toArray();
            
            $data = $divisions->pluck('balance')->map(function ($value) {
                return (float)$value;
            })->toArray();

            return response()->json([
                'labels' => $labels,
                'data' => $data,
                'month' => $month,
                'corporation_id' => $corporationId,
            ]);

        } catch (\Exception $e) {
            Log::error('CorpWalletManager division breakdown API error', [
                'error' => $e->getMessage(),
                'corporation_id' => $request->get('corporation_id'),
                'month' => $request->get('month')
            ]);
            
            return response()->json([
                'error' => 'Unable to fetch division data',
                'labels' => [],
                'data' => [],
                'month' => $request->get('month', Carbon::now()->format('Y-m')),
                'corporation_id' => $request->get('corporation_id'),
            ], 500);
        }
    }

    /**
     * Get summary statistics
     */
    public function summary(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);

            $currentMonth = Carbon::now()->format('Y-m');
            $lastMonth = Carbon::now()->subMonth()->format('Y-m');

            // Current month balance
            $currentQuery = MonthlyBalance::where('month', $currentMonth);
            if ($corporationId) {
                $currentQuery->where('corporation_id', $corporationId);
            }
            $currentBalance = (float)($currentQuery->sum('balance') ?? 0);

            // Last month balance
            $lastQuery = MonthlyBalance::where('month', $lastMonth);
            if ($corporationId) {
                $lastQuery->where('corporation_id', $corporationId);
            }
            $lastBalance = (float)($lastQuery->sum('balance') ?? 0);

            // Calculate change
            $change = $currentBalance - $lastBalance;
            $changePercent = $lastBalance != 0 ? ($change / $lastBalance) * 100 : 0;

            // Next month prediction
            $nextMonth = Carbon::now()->addMonth()->startOfMonth();
            $predictionQuery = Prediction::whereDate('date', $nextMonth);
            if ($corporationId) {
                $predictionQuery->where('corporation_id', $corporationId);
            }
            $nextMonthPrediction = (float)($predictionQuery->sum('predicted_balance') ?? 0);

            return response()->json([
                'current_month' => [
                    'month' => $currentMonth,
                    'balance' => $currentBalance,
                ],
                'last_month' => [
                    'month' => $lastMonth,
                    'balance' => $lastBalance,
                ],
                'change' => [
                    'absolute' => $change,
                    'percent' => round($changePercent, 2),
                ],
                'prediction' => [
                    'month' => $nextMonth->format('Y-m'),
                    'balance' => $nextMonthPrediction,
                ],
                'corporation_id' => $corporationId,
            ]);

        } catch (\Exception $e) {
            Log::error('CorpWalletManager summary API error', [
                'error' => $e->getMessage(),
                'corporation_id' => $request->get('corporation_id')
            ]);
            
            return response()->json([
                'error' => 'Unable to fetch summary data',
                'current_month' => ['month' => Carbon::now()->format('Y-m'), 'balance' => 0],
                'last_month' => ['month' => Carbon::now()->subMonth()->format('Y-m'), 'balance' => 0],
                'change' => ['absolute' => 0, 'percent' => 0],
                'prediction' => ['month' => Carbon::now()->addMonth()->format('Y-m'), 'balance' => 0],
                'corporation_id' => $request->get('corporation_id'),
            ], 500);
        }
    }
    
    /**
     * Get actual wallet balance from corporation_wallet_balances table
     */
    public function walletActual(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            // If no corporation specified, get the first one we have
            if (!$corporationId) {
                $corporationId = DB::table('corporation_wallet_balances')
                    ->value('corporation_id');
            }
            
            // Use corporation_wallet_balances table which has the current balance
            $query = DB::table('corporation_wallet_balances')
                ->selectRaw('SUM(balance) as total_balance');
            
            if ($corporationId) {
                $query->where('corporation_id', $corporationId);
            }
            
            $result = $query->first();
            $balance = $result ? (float)$result->total_balance : 0;
            
            // Also get division count for info
            $divisionCountQuery = DB::table('corporation_wallet_balances');
            if ($corporationId) {
                $divisionCountQuery->where('corporation_id', $corporationId);
            }
            $divisionCount = $divisionCountQuery->count();
            
            return response()->json([
                'balance' => $balance,
                'corporation_id' => $corporationId,
                'divisions' => $divisionCount,
                'timestamp' => now()->toIso8601String(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('CorpWalletManager walletActual error', [
                'error' => $e->getMessage(),
                'corporation_id' => $request->get('corporation_id')
            ]);
            
            return response()->json([
                'error' => 'Unable to fetch actual balance',
                'balance' => 0,
                'corporation_id' => $request->get('corporation_id'),
            ], 500);
        }
    }

    /**
     * Get today's wallet changes
     */
    public function today(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            $today = Carbon::today();
            
            $query = DB::table('corporation_wallet_journals')
                ->whereDate('date', $today)
                ->selectRaw('SUM(amount) as total_change');
                
            if ($corporationId && is_numeric($corporationId)) {
                $query->where('corporation_id', $corporationId);
            }
            
            $result = $query->first();
            $change = $result ? (float)$result->total_change : 0;
            
            return response()->json([
                'change' => $change,
                'date' => $today->format('Y-m-d'),
                'corporation_id' => $corporationId,
            ]);
            
        } catch (\Exception $e) {
            Log::error('CorpWalletManager today API error', [
                'error' => $e->getMessage(),
                'corporation_id' => $request->get('corporation_id')
            ]);
            
            return response()->json([
                'error' => 'Unable to fetch today data',
                'change' => 0,
                'date' => Carbon::today()->format('Y-m-d'),
                'corporation_id' => $request->get('corporation_id'),
            ], 500);
        }
    }

    /**
     * Get current division breakdown with balances
     */
    public function divisionCurrent(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            if (!$corporationId) {
                // Get first available corporation
                $corporationId = DB::table('corporation_wallet_balances')
                    ->whereNotNull('corporation_id')
                    ->value('corporation_id');
            }
            
            if (!$corporationId) {
                return response()->json(['divisions' => []]);
            }
            
            $currentMonth = Carbon::now()->format('Y-m');
            
            // Get current division balances from corporation_wallet_balances
            $walletBalances = DB::table('corporation_wallet_balances')
                ->where('corporation_id', $corporationId)
                ->get()
                ->keyBy('division');
            
            // Get monthly changes from our processed table
            $monthlyChanges = \Seat\CorpWalletManager\Models\DivisionBalance::where('corporation_id', $corporationId)
                ->where('month', $currentMonth)
                ->get()
                ->keyBy('division_id');
            
            // Get actual division names from the database
            $divisionNames = $this->getAllDivisionNames($corporationId);
            
            $divisions = [];
            
            // Build division data with actual names
            foreach ($walletBalances as $walletBalance) {
                $monthChange = $monthlyChanges->get($walletBalance->division);
                
                $divisions[] = [
                    'id' => $walletBalance->division,
                    'name' => $divisionNames[$walletBalance->division] ?? "Division {$walletBalance->division}",
                    'balance' => (float)$walletBalance->balance,
                    'change' => $monthChange ? (float)$monthChange->balance : 0,
                ];
            }
            
            // Sort by division ID for consistent ordering
            usort($divisions, function($a, $b) {
                return $a['id'] <=> $b['id'];
            });
            
            return response()->json([
                'divisions' => $divisions,
                'corporation_id' => $corporationId,
                'month' => $currentMonth,
            ]);
            
        } catch (\Exception $e) {
            Log::error('CorpWalletManager divisionCurrent error', [
                'error' => $e->getMessage(),
                'corporation_id' => $request->get('corporation_id')
            ]);
            
            return response()->json([
                'error' => 'Unable to fetch division data',
                'divisions' => [],
            ], 500);
        }
    }

    /**
     * Get balance history (actual cumulative balances)
     */
    public function balanceHistory(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            $months = min(max((int)$request->get('months', 6), 1), 24);
            
            $startDate = Carbon::now()->subMonths($months)->startOfMonth();
            
            // Get monthly balances and calculate cumulative
            $query = MonthlyBalance::where('month', '>=', $startDate->format('Y-m'))
                ->orderBy('month');
                
            if ($corporationId && is_numeric($corporationId)) {
                $query->where('corporation_id', $corporationId);
            }
            
            $balances = $query->get()
                ->groupBy('month')
                ->map(function ($rows) {
                    return $rows->sum('balance');
                });
            
            // Calculate cumulative balances
            $cumulative = 0;
            $cumulativeBalances = [];
            $labels = [];
            
            foreach ($balances as $month => $balance) {
                $cumulative += $balance;
                $cumulativeBalances[] = $cumulative;
                $labels[] = Carbon::createFromFormat('Y-m', $month)->format('M Y');
            }
            
            return response()->json([
                'labels' => $labels,
                'data' => $cumulativeBalances,
                'months_requested' => $months,
                'corporation_id' => $corporationId,
            ]);
            
        } catch (\Exception $e) {
            Log::error('CorpWalletManager balanceHistory error', [
                'error' => $e->getMessage(),
                'corporation_id' => $request->get('corporation_id')
            ]);
            
            return response()->json([
                'error' => 'Unable to fetch balance history',
                'labels' => [],
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get income vs expenses breakdown
     */
    public function incomeExpense(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            $months = min(max((int)$request->get('months', 6), 1), 24);
            
            $startDate = Carbon::now()->subMonths($months)->startOfMonth();
            
            $query = DB::table('corporation_wallet_journals')
                ->selectRaw('
                    DATE_FORMAT(date, "%Y-%m") as month,
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses
                ')
                ->where('date', '>=', $startDate)
                ->groupBy('month')
                ->orderBy('month');
                
            if ($corporationId && is_numeric($corporationId)) {
                $query->where('corporation_id', $corporationId);
            }
            
            $results = $query->get();
            
            $labels = [];
            $income = [];
            $expenses = [];
            
            foreach ($results as $row) {
                $labels[] = Carbon::createFromFormat('Y-m', $row->month)->format('M Y');
                $income[] = (float)$row->income;
                $expenses[] = (float)$row->expenses;
            }
            
            return response()->json([
                'labels' => $labels,
                'income' => $income,
                'expenses' => $expenses,
                'months_requested' => $months,
                'corporation_id' => $corporationId,
            ]);
            
        } catch (\Exception $e) {
            Log::error('CorpWalletManager incomeExpense error', [
                'error' => $e->getMessage(),
                'corporation_id' => $request->get('corporation_id')
            ]);
            
            return response()->json([
                'error' => 'Unable to fetch income/expense data',
                'labels' => [],
                'income' => [],
                'expenses' => [],
            ], 500);
        }
    }

    /**
     * Get transaction breakdown by type (for pie charts)
     */
    public function transactionBreakdown(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            $type = $request->get('type', 'expense'); // 'income' or 'expense'
            $months = (int)$request->get('months', 1); // Default to current month
            
            $startDate = Carbon::now()->subMonths($months)->startOfMonth();
            
            // Build the query
            $query = DB::table('corporation_wallet_journals')
                ->selectRaw('
                    ref_type,
                    SUM(ABS(amount)) as total_amount,
                    COUNT(*) as transaction_count
                ')
                ->where('date', '>=', $startDate);
            
            // Filter by income or expense
            if ($type === 'income') {
                $query->where('amount', '>', 0);
            } else {
                $query->where('amount', '<', 0);
            }
            
            if ($corporationId && is_numeric($corporationId)) {
                $query->where('corporation_id', $corporationId);
            }
            
            $query->groupBy('ref_type')
                ->orderBy('total_amount', 'DESC');
            
            $results = $query->get();
            
            // Format transaction types for better display
            $typeNames = $this->getTransactionTypeNames();
            
            $labels = [];
            $values = [];
            $details = [];
            $other = 0;
            $otherCount = 0;
            
            foreach ($results as $index => $row) {
                $typeName = $typeNames[$row->ref_type] ?? $this->formatRefType($row->ref_type);
                $amount = (float)$row->total_amount;
                
                // Group small values into "Other"
                if ($index < 9) { // Show top 9 categories
                    $labels[] = $typeName;
                    $values[] = $amount;
                    $details[] = [
                        'label' => $typeName,
                        'value' => $amount,
                        'count' => $row->transaction_count,
                        'ref_type' => $row->ref_type
                    ];
                } else {
                    $other += $amount;
                    $otherCount += $row->transaction_count;
                }
            }
            
            // Add "Other" category if there are grouped items
            if ($other > 0) {
                $labels[] = 'Other';
                $values[] = $other;
                $details[] = [
                    'label' => 'Other',
                    'value' => $other,
                    'count' => $otherCount,
                    'ref_type' => 'other'
                ];
            }
            
            // Calculate percentages
            $total = array_sum($values);
            foreach ($details as &$detail) {
                $detail['percentage'] = $total > 0 ? round(($detail['value'] / $total) * 100, 1) : 0;
            }
            
            return response()->json([
                'labels' => $labels,
                'values' => $values,
                'details' => $details,
                'total' => $total,
                'type' => $type,
                'corporation_id' => $corporationId,
                'period' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => Carbon::now()->format('Y-m-d')
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('CorpWalletManager transactionBreakdown error', [
                'error' => $e->getMessage(),
                'type' => $request->get('type'),
                'corporation_id' => $request->get('corporation_id')
            ]);
            
            return response()->json([
                'error' => 'Unable to fetch transaction breakdown',
                'labels' => [],
                'values' => [],
                'details' => [],
            ], 500);
        }
    }

    // ========== ADDITIONAL METHODS FOR MEMBER VIEW ==========

    /**
     * Get member-appropriate health status
     */
    public function memberHealth(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            // Get basic health metrics without sensitive details
            $currentMonth = Carbon::now()->format('Y-m');
            $lastMonth = Carbon::now()->subMonth()->format('Y-m');
            
            // Current month balance trend
            $currentQuery = MonthlyBalance::where('month', $currentMonth);
            if ($corporationId) {
                $currentQuery->where('corporation_id', $corporationId);
            }
            $currentBalance = (float)($currentQuery->sum('balance') ?? 0);
            
            // Last month balance
            $lastQuery = MonthlyBalance::where('month', $lastMonth);
            if ($corporationId) {
                $lastQuery->where('corporation_id', $corporationId);
            }
            $lastBalance = (float)($lastQuery->sum('balance') ?? 0);
            
            // Calculate simple health score (0-100)
            $healthScore = 50; // Base score
            
            // Positive balance adds points
            if ($currentBalance > 0) {
                $healthScore += 20;
            }
            
            // Growth adds points
            if ($currentBalance > $lastBalance) {
                $growthRate = $lastBalance > 0 ? (($currentBalance - $lastBalance) / $lastBalance) : 0;
                $healthScore += min(30, $growthRate * 100); // Max 30 points for growth
            }
            
            // Check transaction activity
            $transactionCount = DB::table('corporation_wallet_journals')
                ->whereMonth('date', Carbon::now()->month)
                ->whereYear('date', Carbon::now()->year);
            
            if ($corporationId) {
                $transactionCount->where('corporation_id', $corporationId);
            }
            
            $transactions = $transactionCount->count();
            if ($transactions > 100) {
                $healthScore = min(100, $healthScore + 10);
            }
            
            return response()->json([
                'health_score' => round($healthScore),
                'has_positive_balance' => $currentBalance > 0,
                'is_growing' => $currentBalance > $lastBalance,
                'activity_level' => $transactions > 500 ? 'High' : ($transactions > 100 ? 'Medium' : 'Low'),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Member health API error', ['error' => $e->getMessage()]);
            return response()->json([
                'health_score' => 50,
                'has_positive_balance' => true,
                'is_growing' => false,
                'activity_level' => 'Unknown',
            ], 500);
        }
    }
    
    /**
     * Get member goals data
     */
    public function memberGoals(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            // Get or create goals from settings
            $savingsTarget = Settings::getSetting('goal_savings_target', 1000000000);
            $activityTarget = Settings::getSetting('goal_activity_target', 1000);
            $growthTarget = Settings::getSetting('goal_growth_target', 10);
            
            // Calculate current progress
            $currentBalance = DB::table('corporation_wallet_balances');
            if ($corporationId) {
                $currentBalance->where('corporation_id', $corporationId);
            }
            $balance = (float)($currentBalance->sum('balance') ?? 0);
            
            // Activity count
            $transactionCount = DB::table('corporation_wallet_journals')
                ->whereMonth('date', Carbon::now()->month)
                ->whereYear('date', Carbon::now()->year);
            
            if ($corporationId) {
                $transactionCount->where('corporation_id', $corporationId);
            }
            $transactions = $transactionCount->count();
            
            // Growth calculation
            $lastMonth = Carbon::now()->subMonth();
            $lastMonthBalance = MonthlyBalance::where('month', $lastMonth->format('Y-m'));
            if ($corporationId) {
                $lastMonthBalance->where('corporation_id', $corporationId);
            }
            $previousBalance = (float)($lastMonthBalance->sum('balance') ?? 0);
            
            $growthRate = $previousBalance > 0 
                ? (($balance - $previousBalance) / $previousBalance) * 100 
                : 0;
            
            // Calculate stretch goals
            $daysPositive = $this->calculateDaysPositive($corporationId);
            $allDivisionsProfit = $this->checkAllDivisionsProfit($corporationId);
            
            return response()->json([
                'savings' => [
                    'current' => $balance,
                    'target' => (float)$savingsTarget,
                    'percentage' => min(100, ($balance / $savingsTarget) * 100),
                ],
                'activity' => [
                    'current' => $transactions,
                    'target' => (int)$activityTarget,
                    'percentage' => min(100, ($transactions / $activityTarget) * 100),
                ],
                'growth' => [
                    'current' => round($growthRate, 1),
                    'target' => (float)$growthTarget,
                    'percentage' => min(100, ($growthRate / $growthTarget) * 100),
                ],
                'stretch_goals' => [
                    'positive_cashflow_30_days' => $daysPositive >= 30,
                    'zero_days_below_threshold' => $daysPositive >= 30,
                    'all_divisions_profitable' => $allDivisionsProfit,
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Member goals API error', ['error' => $e->getMessage()]);
            return response()->json([
                'error' => 'Unable to load goals',
                'savings' => ['current' => 0, 'target' => 1000000000, 'percentage' => 0],
                'activity' => ['current' => 0, 'target' => 1000, 'percentage' => 0],
                'growth' => ['current' => 0, 'target' => 10, 'percentage' => 0],
            ], 500);
        }
    }
    
    /**
     * Get member milestones
     */
    public function memberMilestones(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            $milestones = [];
            $events = [];
            
            // Check various milestones
            $daysPositive = $this->calculateDaysPositive($corporationId);
            if ($daysPositive >= 30) {
                $milestones[] = [
                    'icon' => 'fa-check-circle text-success',
                    'text' => "Maintained positive balance for {$daysPositive} days",
                    'achieved_at' => Carbon::now()->subDays(30)->format('Y-m-d'),
                ];
            } elseif ($daysPositive >= 15) {
                $milestones[] = [
                    'icon' => 'fa-check-circle text-info',
                    'text' => "Positive balance streak: {$daysPositive} days",
                    'achieved_at' => Carbon::now()->subDays(15)->format('Y-m-d'),
                ];
            }
            
            // Check for balance milestones
            $currentBalance = DB::table('corporation_wallet_balances');
            if ($corporationId) {
                $currentBalance->where('corporation_id', $corporationId);
            }
            $balance = (float)($currentBalance->sum('balance') ?? 0);
            
            if ($balance >= 10000000000) { // 10B
                $milestones[] = [
                    'icon' => 'fa-trophy text-warning',
                    'text' => 'Reached 10B ISK milestone',
                    'achieved_at' => Carbon::now()->format('Y-m-d'),
                ];
            } elseif ($balance >= 1000000000) { // 1B
                $milestones[] = [
                    'icon' => 'fa-trophy text-info',
                    'text' => 'Reached 1B ISK milestone',
                    'achieved_at' => Carbon::now()->format('Y-m-d'),
                ];
            }
            
            // Check activity milestones
            $monthlyTransactions = DB::table('corporation_wallet_journals')
                ->whereMonth('date', Carbon::now()->month)
                ->whereYear('date', Carbon::now()->year);
            
            if ($corporationId) {
                $monthlyTransactions->where('corporation_id', $corporationId);
            }
            $transactionCount = $monthlyTransactions->count();
            
            if ($transactionCount >= 1000) {
                $milestones[] = [
                    'icon' => 'fa-chart-line text-success',
                    'text' => 'Over 1000 transactions this month',
                    'achieved_at' => Carbon::now()->format('Y-m-d'),
                ];
            }
            
            // Add upcoming events
            $daysInMonth = Carbon::now()->daysInMonth;
            $currentDay = Carbon::now()->day;
            $daysUntilMonthEnd = $daysInMonth - $currentDay;
            
            $events[] = [
                'icon' => 'fa-calendar-check',
                'text' => "{$daysUntilMonthEnd} days until month end",
                'date' => Carbon::now()->endOfMonth()->format('Y-m-d'),
            ];
            
            // Simulated dividend date (15th of each month)
            $dividendDay = 15;
            $daysUntilDividend = $dividendDay > $currentDay 
                ? $dividendDay - $currentDay 
                : ($daysInMonth - $currentDay) + $dividendDay;
                
            $events[] = [
                'icon' => 'fa-coins',
                'text' => "{$daysUntilDividend} days until dividend payment",
                'date' => Carbon::now()->day(15)->format('Y-m-d'),
            ];
            
            return response()->json([
                'milestones' => $milestones,
                'events' => $events,
                'summary' => [
                    'total_milestones' => count($milestones),
                    'upcoming_events' => count($events),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Member milestones API error', ['error' => $e->getMessage()]);
            return response()->json([
                'milestones' => [],
                'events' => [],
                'summary' => ['total_milestones' => 0, 'upcoming_events' => 0],
            ], 500);
        }
    }
    
    /**
     * Log member access for tracking
     */
    public function logMemberAccess(Request $request)
    {
        try {
            $userId = auth()->id();
            $corporationId = $request->input('corporation_id');
            
            DB::table('corpwalletmanager_access_logs')->insert([
                'user_id' => $userId,
                'corporation_id' => $corporationId,
                'view_type' => $request->input('view', 'member'),
                'accessed_at' => Carbon::now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            
            return response()->json(['logged' => true]);
            
        } catch (\Exception $e) {
            // Don't fail the request if logging fails
            Log::warning('Failed to log member access', [
                'error' => $e->getMessage(),
                'user' => auth()->id(),
            ]);
            return response()->json(['logged' => false]);
        }
    }
    
    // Helper Methods
    
    /**
     * Calculate days with positive balance
     */
    private function calculateDaysPositive($corporationId)
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        $query = DB::table('corporation_wallet_journals')
            ->selectRaw('DATE(date) as day, SUM(amount) as daily_change')
            ->where('date', '>=', $thirtyDaysAgo)
            ->groupBy('day');
        
        if ($corporationId) {
            $query->where('corporation_id', $corporationId);
        }
        
        $dailyChanges = $query->get();
        $positiveDays = $dailyChanges->filter(function ($day) {
            return $day->daily_change > 0;
        })->count();
        
        return $positiveDays;
    }
    
    /**
     * Check if all divisions are profitable
     */
    private function checkAllDivisionsProfit($corporationId)
    {
        if (!$corporationId) {
            return false;
        }
        
        $currentMonth = Carbon::now()->format('Y-m');
        
        $divisions = DivisionBalance::where('corporation_id', $corporationId)
            ->where('month', $currentMonth)
            ->get();
        
        if ($divisions->isEmpty()) {
            return false;
        }
        
        return $divisions->every(function ($division) {
            return $division->balance > 0;
        });
    }

    // ========== HELPER METHODS ==========
    
    /**
     * Helper function to get division names from database
     */
    private function getDivisionName($divisionId, $corporationId = null)
    {
        try {
            // Try to get the division name from the corporation_divisions table
            if ($corporationId) {
                $division = DB::table('corporation_divisions')
                    ->where('corporation_id', $corporationId)
                    ->where('division', $divisionId)
                    ->first();
                
                if ($division && !empty($division->name)) {
                    return $division->name;
                }
            }
            
            // Fallback to default EVE division names if not found
            $defaultNames = [
                1 => 'Master Wallet',
                2 => '2nd Wallet Division',
                3 => '3rd Wallet Division',
                4 => '4th Wallet Division',
                5 => '5th Wallet Division',
                6 => '6th Wallet Division',
                7 => '7th Wallet Division',
            ];
            
            return $defaultNames[$divisionId] ?? "Division $divisionId";
            
        } catch (\Exception $e) {
            Log::warning('Failed to get division name from database', [
                'division_id' => $divisionId,
                'corporation_id' => $corporationId,
                'error' => $e->getMessage()
            ]);
            
            // Return a generic name if database lookup fails
            return "Division $divisionId";
        }
    }
    
    /**
     * Get all division names for a corporation
     */
    private function getAllDivisionNames($corporationId)
    {
        try {
            $divisions = DB::table('corporation_divisions')
                ->where('corporation_id', $corporationId)
                ->pluck('name', 'division')
                ->toArray();
            
            // Fill in any missing divisions with defaults
            for ($i = 1; $i <= 7; $i++) {
                if (!isset($divisions[$i]) || empty($divisions[$i])) {
                    $divisions[$i] = $this->getDivisionName($i);
                }
            }
            
            return $divisions;
            
        } catch (\Exception $e) {
            Log::warning('Failed to get all division names', [
                'corporation_id' => $corporationId,
                'error' => $e->getMessage()
            ]);
            
            // Return default names
            $divisions = [];
            for ($i = 1; $i <= 7; $i++) {
                $divisions[$i] = $this->getDivisionName($i);
            }
            return $divisions;
        }
    }

    /**
     * Get human-readable transaction type names
     */
    private function getTransactionTypeNames()
    {
        return [
            'player_trading' => 'Player Trading',
            'market_transaction' => 'Market Orders',
            'market_escrow' => 'Market Escrow',
            'transaction_tax' => 'Transaction Tax',
            'broker_fee' => 'Broker Fees',
            'bounty_prizes' => 'Bounty Prizes',
            'bounty_prize' => 'Bounty Prize',
            'agent_mission_reward' => 'Mission Rewards',
            'agent_mission_time_bonus_reward' => 'Mission Time Bonus',
            'corporation_account_withdrawal' => 'Corp Withdrawal',
            'corporation_dividend_payment' => 'Dividend Payment',
            'corporation_logo_change_cost' => 'Logo Change',
            'corporation_payment' => 'Corp Payment',
            'corporation_registration_fee' => 'Registration Fee',
            'courier_mission_escrow' => 'Courier Escrow',
            'cspa' => 'CSPA Charge',
            'cspaofflinerefund' => 'CSPA Refund',
            'daily_challenge_reward' => 'Daily Challenge',
            'daily_goal_payouts' => 'Daily Goal Payouts',
            'ess_escrow_transfer' => 'ESS Escrow Transfer',
            'copying' => 'Blueprint Copying',
            'industry_job_tax' => 'Industry Tax',
            'manufacturing' => 'Manufacturing',
            'researching_material_efficiency' => 'ME Research',
            'researching_time_efficiency' => 'TE Research',
            'researching_technology' => 'Tech Research',
            'reprocessing_tax' => 'Reprocessing Tax',
            'jump_clone_installation_fee' => 'Jump Clone Fee',
            'jump_clone_activation_fee' => 'Clone Jump Fee',
            'kill_right_fee' => 'Kill Right',
            'office_rental_fee' => 'Office Rental',
            'planetary_import_tax' => 'PI Import Tax',
            'planetary_export_tax' => 'PI Export Tax',
            'planetary_construction' => 'PI Construction',
            'skill_purchase' => 'Skill Purchase',
            'insurance' => 'Insurance',
            'docking_fee' => 'Docking Fee',
            'contract_auction_bid' => 'Contract Bid',
            'contract_auction_bid_refund' => 'Contract Refund',
            'contract_brokers_fee' => 'Contract Broker Fee',
            'contract_sales_tax' => 'Contract Tax',
            'contract_deposit' => 'Contract Deposit',
            'contract_deposit_refund' => 'Deposit Refund',
            'contract_reward' => 'Contract Reward',
            'contract_price' => 'Contract Price',
            'contract_reversal' => 'Contract Reversal',
            'contract_collateral' => 'Contract Collateral',
            'asset_safety_recovery_tax' => 'Asset Safety Tax',
            'structure_gate_jump' => 'Gate Jump Fee',
            'war_ally_contract' => 'War Ally Contract',
            'war_fee' => 'War Declaration',
            'war_fee_surrender' => 'War Surrender',
            'reaction' => 'Reactions',
            'reprocessing' => 'Reprocessing',
        ];
    }

    /**
     * Format ref_type to human-readable format
     */
    private function formatRefType($refType)
    {
        // Convert snake_case to Title Case
        $formatted = str_replace('_', ' ', $refType);
        return ucwords($formatted);
    }
}
