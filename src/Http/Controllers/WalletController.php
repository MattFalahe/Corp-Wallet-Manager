<?php
namespace CorpWalletManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use CorpWalletManager\Http\Controllers\Concerns\AuthorizesCorporationAccess;
use CorpWalletManager\Models\Settings;
use CorpWalletManager\Models\Prediction;
use CorpWalletManager\Models\MonthlyBalance;
use CorpWalletManager\Models\DivisionBalance;
use CorpWalletManager\Support\JournalFilters;

class WalletController extends Controller
{
    use AuthorizesCorporationAccess;

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

    /**
     * Persist the current user's chosen corporation in their session so
     * subsequent requests in this session default to it. Rejects corps the
     * user is not authorized to view.
     */
    public function setCorporation(Request $request)
    {
        $corpId = $request->input('corporation_id');
        if (!is_numeric($corpId) || (int) $corpId <= 0) {
            return response()->json(['error' => 'Invalid corporation_id'], 400);
        }

        if (!$this->setSessionCorporation((int) $corpId)) {
            return response()->json(['error' => 'Not authorized for that corporation'], 403);
        }

        return response()->json(['corporation_id' => (int) $corpId]);
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
            // Get all member view settings
            $settings = [
                'member_show_health' => Settings::getBooleanSetting('member_show_health', true),
                'member_show_trends' => Settings::getBooleanSetting('member_show_trends', true),
                'member_show_activity' => Settings::getBooleanSetting('member_show_activity', true),
                'member_show_goals' => Settings::getBooleanSetting('member_show_goals', true),
                'member_show_milestones' => Settings::getBooleanSetting('member_show_milestones', true),
                'member_show_balance' => Settings::getBooleanSetting('member_show_balance', true),
                'member_show_performance' => Settings::getBooleanSetting('member_show_performance', true),
                'member_data_delay' => Settings::getIntegerSetting('member_data_delay', 0),
                // Personal contribution + leaderboard surface (v3.0.0).
                'member_show_personal_contribution' => Settings::getBooleanSetting('member_show_personal_contribution', true),
                'member_show_leaderboard' => Settings::getBooleanSetting('member_show_leaderboard', true),
                'member_show_mm_compliance' => Settings::getBooleanSetting('member_show_mm_compliance', true),
                // My Personal Wallet tab (v3.0.0 follow-up). Operator toggle
                // for the third tab; default true. When false the tab nav
                // and the tab pane both disappear via Blade @if.
                'member_show_personal_wallet' => Settings::getBooleanSetting('member_show_personal_wallet', true),
                'member_leaderboard_mode' => (string) Settings::getSetting('member_leaderboard_mode', 'isk_visible'),
                'member_leaderboard_size' => Settings::getIntegerSetting('member_leaderboard_size', 10),
                // Used by the conditional MM Tax Compliance card so the
                // blade can suppress the row entirely when MM is absent
                // (the card would otherwise render an empty shell that
                // never resolves).
                'mm_available' => class_exists(\MiningManager\Models\TaxCode::class),
            ];

            return view('corpwalletmanager::member', compact('settings'));
        } catch (\Exception $e) {
            Log::error('CorpWalletManager member view error: ' . $e->getMessage());

            // Fallback with default settings if there's an error
            $settings = [
                'member_show_health' => true,
                'member_show_trends' => true,
                'member_show_activity' => true,
                'member_show_goals' => true,
                'member_show_milestones' => true,
                'member_show_balance' => true,
                'member_show_performance' => true,
                'member_data_delay' => 0,
                'member_show_personal_contribution' => true,
                'member_show_leaderboard' => true,
                'member_show_mm_compliance' => true,
                'member_show_personal_wallet' => true,
                'member_leaderboard_mode' => 'isk_visible',
                'member_leaderboard_size' => 10,
                'mm_available' => class_exists(\MiningManager\Models\TaxCode::class),
            ];

            return view('corpwalletmanager::member', compact('settings'));
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
            $days = min(max((int)$request->get('days', 30), 1), 90);
            
            $startDate = Carbon::today();
            $endDate = $startDate->copy()->addDays($days);
            
            $query = Prediction::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->orderBy('date');
                
            if ($corporationId) {
                $query->where('corporation_id', $corporationId);
            }
    
            $predictions = $query->get();
    
            // Group by date and aggregate
            $grouped = $predictions->groupBy('date')->map(function ($group) {
                $sumBalance = $group->sum('predicted_balance');
                $avgConfidence = $group->avg('confidence');
                $sumLower68 = $group->sum('lower_bound');
                $sumUpper68 = $group->sum('upper_bound');
                
                // Get metadata from first prediction in group
                $metadata = $group->first()->metadata ?? [];
                
                return [
                    'balance' => $sumBalance,
                    'confidence' => round($avgConfidence, 1),
                    'lower_68' => $sumLower68,
                    'upper_68' => $sumUpper68,
                    'lower_95' => $metadata['confidence_95_lower'] ?? null,
                    'upper_95' => $metadata['confidence_95_upper'] ?? null,
                    'factors' => [
                        'seasonal' => $metadata['seasonal_factor'] ?? null,
                        'momentum' => $metadata['momentum_factor'] ?? null,
                        'activity' => $metadata['activity_factor'] ?? null,
                    ]
                ];
            });
    
            $labels = $grouped->keys()->toArray();
            $predictions = $grouped->pluck('balance')->toArray();
            $confidenceValues = $grouped->pluck('confidence')->toArray();
            
            // Prepare confidence bands
            $confidenceBands = [
                'lower_68' => $grouped->pluck('lower_68')->toArray(),
                'upper_68' => $grouped->pluck('upper_68')->toArray(),
                'lower_95' => array_filter($grouped->pluck('lower_95')->toArray()),
                'upper_95' => array_filter($grouped->pluck('upper_95')->toArray()),
            ];
            
            // Get factors for tooltips
            $factors = $grouped->pluck('factors')->toArray();
    
            return response()->json([
                'labels' => $labels,
                'data' => $predictions,
                'predictions' => $predictions, // For backwards compatibility
                'confidence_values' => $confidenceValues,
                'confidence_bands' => $confidenceBands,
                'factors' => $factors,
                'days_requested' => $days,
                'corporation_id' => $corporationId,
                'method' => 'advanced_weighted',
                'based_on_months' => 12,
            ]);
    
        } catch (\Exception $e) {
            Log::error('WalletController predictions API error', [
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

            $divisions = \CorpWalletManager\Models\DivisionBalance::where('corporation_id', $corporationId)
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
                $query = JournalFilters::excludeInternalTransfers($query, (int) $corporationId);
            } else {
                $query = JournalFilters::excludeInternalTransfers($query);
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
            $monthlyChanges = \CorpWalletManager\Models\DivisionBalance::where('corporation_id', $corporationId)
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
                $query = JournalFilters::excludeInternalTransfers($query, (int) $corporationId);
            } else {
                $query = JournalFilters::excludeInternalTransfers($query);
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
                $query = JournalFilters::excludeInternalTransfers($query, (int) $corporationId);
            } else {
                $query = JournalFilters::excludeInternalTransfers($query);
            }

            $query->groupBy('ref_type')
                ->orderBy('total_amount', 'DESC');

            $results = $query->get();

            // Split out alliance tax from corporation_account_withdrawal /
            // player_donation when match rules are configured. Otherwise
            // the breakdown shows a fat "Corporation Account Withdrawal"
            // wedge that's really almost entirely the monthly alliance
            // remit, hiding the actual other-withdrawals breakdown
            // (payroll, structure fuel buys, contracts, etc).
            if ($type === 'expense' && $corporationId && is_numeric($corporationId)) {
                $allianceByRef = app(\CorpWalletManager\Services\AllianceTaxService::class)
                    ->getAllianceTaxByRefType((int) $corporationId, $startDate, Carbon::now());

                if (! empty($allianceByRef)) {
                    $allianceTotal = 0.0;
                    $allianceCount = 0;

                    $results = $results->map(function ($row) use ($allianceByRef, &$allianceTotal, &$allianceCount) {
                        if (isset($allianceByRef[$row->ref_type])) {
                            $share = $allianceByRef[$row->ref_type];
                            $row->total_amount = max(0.0, (float) $row->total_amount - (float) $share['amount']);
                            $row->transaction_count = max(0, (int) $row->transaction_count - (int) $share['count']);
                            $allianceTotal += (float) $share['amount'];
                            $allianceCount += (int) $share['count'];
                        }
                        return $row;
                    })->filter(function ($row) {
                        return (int) $row->transaction_count > 0;
                    })->values();

                    if ($allianceTotal > 0) {
                        $results->push((object) [
                            'ref_type'          => 'alliance_tax',
                            'total_amount'      => $allianceTotal,
                            'transaction_count' => $allianceCount,
                        ]);
                        $results = $results->sortByDesc('total_amount')->values();
                    }
                }
            }

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
                $transactionCount = JournalFilters::excludeInternalTransfers($transactionCount, (int) $corporationId);
            } else {
                $transactionCount = JournalFilters::excludeInternalTransfers($transactionCount);
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
     * Enhanced member goals data with real calculations
     */
    public function memberGoals(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            // Get goal settings
            $savingsTarget = (float)Settings::getSetting('goal_savings_target', 1000000000);
            $activityTarget = (int)Settings::getSetting('goal_activity_target', 1000);
            $growthTarget = (float)Settings::getSetting('goal_growth_target', 10);
            
            // Get current balance
            $currentBalance = DB::table('corporation_wallet_balances');
            if ($corporationId) {
                $currentBalance->where('corporation_id', $corporationId);
            }
            $balance = (float)($currentBalance->sum('balance') ?? 0);
            
            // Calculate savings percentage
            $savingsPercentage = $savingsTarget > 0 ? min(100, ($balance / $savingsTarget) * 100) : 0;
            
            // Get current month activity
            $currentMonth = Carbon::now();
            $transactionCount = DB::table('corporation_wallet_journals')
                ->whereMonth('date', $currentMonth->month)
                ->whereYear('date', $currentMonth->year);

            if ($corporationId) {
                $transactionCount->where('corporation_id', $corporationId);
                $transactionCount = JournalFilters::excludeInternalTransfers($transactionCount, (int) $corporationId);
            } else {
                $transactionCount = JournalFilters::excludeInternalTransfers($transactionCount);
            }
            $transactions = $transactionCount->count();
            
            // Calculate activity percentage
            $activityPercentage = $activityTarget > 0 ? min(100, ($transactions / $activityTarget) * 100) : 0;
            
            // Calculate growth
            $lastMonth = Carbon::now()->subMonth();
            $lastMonthBalance = MonthlyBalance::where('month', $lastMonth->format('Y-m'));
            if ($corporationId) {
                $lastMonthBalance->where('corporation_id', $corporationId);
            }
            $previousBalance = (float)($lastMonthBalance->sum('balance') ?? 0);
            
            $growthRate = $previousBalance > 0 
                ? (($balance - $previousBalance) / $previousBalance) * 100 
                : 0;
            
            // Calculate growth percentage against target
            $growthPercentage = $growthTarget > 0 ? min(100, max(0, ($growthRate / $growthTarget) * 100)) : 0;
            
            // Calculate stretch goals
            $daysPositive = $this->calculateDaysPositive($corporationId);
            $daysNegative = 30 - $daysPositive;
            $allDivisionsProfit = $this->checkAllDivisionsProfit($corporationId);
            
            // Check if zero days below threshold (simplified: positive balance maintained)
            $zeroNegativeDays = $daysNegative == 0;
            
            return response()->json([
                'savings' => [
                    'current' => $balance,
                    'target' => $savingsTarget,
                    'percentage' => round($savingsPercentage, 1),
                ],
                'activity' => [
                    'current' => $transactions,
                    'target' => $activityTarget,
                    'percentage' => round($activityPercentage, 1),
                ],
                'growth' => [
                    'current' => round($growthRate, 1),
                    'target' => $growthTarget,
                    'percentage' => round($growthPercentage, 1),
                ],
                'stretch_goals' => [
                    'positive_cashflow_30_days' => $daysPositive >= 30,
                    'zero_days_below_threshold' => $zeroNegativeDays,
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
     * Enhanced member milestones with real data
     */
    public function memberMilestones(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            $milestones = [];
            $events = [];
            
            // Calculate days with positive balance
            $daysPositive = $this->calculateDaysPositive($corporationId);
            
            // Add milestone based on positive days
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
            } elseif ($daysPositive >= 7) {
                $milestones[] = [
                    'icon' => 'fa-check-circle text-warning',
                    'text' => "Building momentum: {$daysPositive} days positive",
                    'achieved_at' => Carbon::now()->subDays(7)->format('Y-m-d'),
                ];
            }
            
            // Check balance milestones
            $currentBalance = DB::table('corporation_wallet_balances');
            if ($corporationId) {
                $currentBalance->where('corporation_id', $corporationId);
            }
            $balance = (float)($currentBalance->sum('balance') ?? 0);
            
            if ($balance >= 100000000000) { // 100B
                $milestones[] = [
                    'icon' => 'fa-trophy text-warning',
                    'text' => 'Reached 100B ISK milestone!',
                    'achieved_at' => Carbon::now()->format('Y-m-d'),
                ];
            } elseif ($balance >= 10000000000) { // 10B
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
            } elseif ($balance >= 500000000) { // 500M
                $milestones[] = [
                    'icon' => 'fa-trophy text-primary',
                    'text' => 'Reached 500M ISK milestone',
                    'achieved_at' => Carbon::now()->format('Y-m-d'),
                ];
            }
            
            // Check monthly activity milestones
            $monthlyTransactions = DB::table('corporation_wallet_journals')
                ->whereMonth('date', Carbon::now()->month)
                ->whereYear('date', Carbon::now()->year);

            if ($corporationId) {
                $monthlyTransactions->where('corporation_id', $corporationId);
                $monthlyTransactions = JournalFilters::excludeInternalTransfers($monthlyTransactions, (int) $corporationId);
            } else {
                $monthlyTransactions = JournalFilters::excludeInternalTransfers($monthlyTransactions);
            }
            $transactionCount = $monthlyTransactions->count();
            
            if ($transactionCount >= 5000) {
                $milestones[] = [
                    'icon' => 'fa-chart-line text-success',
                    'text' => 'Exceptional activity: Over 5000 transactions this month!',
                    'achieved_at' => Carbon::now()->format('Y-m-d'),
                ];
            } elseif ($transactionCount >= 1000) {
                $milestones[] = [
                    'icon' => 'fa-chart-line text-success',
                    'text' => 'High activity: Over 1000 transactions this month',
                    'achieved_at' => Carbon::now()->format('Y-m-d'),
                ];
            } elseif ($transactionCount >= 500) {
                $milestones[] = [
                    'icon' => 'fa-chart-line text-info',
                    'text' => 'Good activity: Over 500 transactions this month',
                    'achieved_at' => Carbon::now()->format('Y-m-d'),
                ];
            }
            
            // Check for best performance this quarter
            $quarterStart = Carbon::now()->firstOfQuarter();
            $bestWeekQuery = DB::table('corporation_wallet_journals')
                ->where('date', '>=', $quarterStart)
                ->selectRaw('YEARWEEK(date) as week, SUM(amount) as weekly_total')
                ->groupBy('week')
                ->orderBy('weekly_total', 'desc');

            if ($corporationId) {
                $bestWeekQuery->where('corporation_id', $corporationId);
                $bestWeekQuery = JournalFilters::excludeInternalTransfers($bestWeekQuery, (int) $corporationId);
            } else {
                $bestWeekQuery = JournalFilters::excludeInternalTransfers($bestWeekQuery);
            }

            $bestWeek = $bestWeekQuery->first();
            $currentWeek = Carbon::now()->format('YW');
            
            if ($bestWeek && $bestWeek->week == $currentWeek) {
                $milestones[] = [
                    'icon' => 'fa-star text-warning',
                    'text' => 'Best weekly performance in Q' . Carbon::now()->quarter,
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
            
            // Dividend dates (15th of each month or next month if passed)
            $dividendDay = 15;
            if ($currentDay <= $dividendDay) {
                $daysUntilDividend = $dividendDay - $currentDay;
                $dividendDate = Carbon::now()->day($dividendDay);
            } else {
                $nextMonth = Carbon::now()->addMonth()->day($dividendDay);
                $daysUntilDividend = Carbon::now()->diffInDays($nextMonth);
                $dividendDate = $nextMonth;
            }
            
            $events[] = [
                'icon' => 'fa-coins',
                'text' => "{$daysUntilDividend} days until dividend payment",
                'date' => $dividendDate->format('Y-m-d'),
            ];
            
            // Quarterly review (first Monday of each quarter)
            $quarterStart = Carbon::now()->firstOfQuarter();
            $firstMonday = $quarterStart->copy()->next(Carbon::MONDAY);
            
            if (Carbon::now()->lt($firstMonday)) {
                $daysUntilReview = Carbon::now()->diffInDays($firstMonday);
                $events[] = [
                    'icon' => 'fa-clipboard-check',
                    'text' => "{$daysUntilReview} days until quarterly review",
                    'date' => $firstMonday->format('Y-m-d'),
                ];
            } else {
                // Next quarter's review
                $nextQuarter = Carbon::now()->addQuarter()->firstOfQuarter()->next(Carbon::MONDAY);
                $daysUntilReview = Carbon::now()->diffInDays($nextQuarter);
                if ($daysUntilReview <= 30) {
                    $events[] = [
                        'icon' => 'fa-clipboard-check',
                        'text' => "{$daysUntilReview} days until quarterly review",
                        'date' => $nextQuarter->format('Y-m-d'),
                    ];
                }
            }
            
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
     * Get activity level data with real metrics
     */
    public function memberActivity(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            // Current month transactions
            $currentMonth = Carbon::now();
            $transactionQuery = DB::table('corporation_wallet_journals')
                ->whereMonth('date', $currentMonth->month)
                ->whereYear('date', $currentMonth->year);

            if ($corporationId) {
                $transactionQuery->where('corporation_id', $corporationId);
                $transactionQuery = JournalFilters::excludeInternalTransfers($transactionQuery, (int) $corporationId);
            } else {
                $transactionQuery = JournalFilters::excludeInternalTransfers($transactionQuery);
            }

            $currentTransactions = $transactionQuery->count();

            // Last month transactions for comparison
            $lastMonth = Carbon::now()->subMonth();
            $lastMonthQuery = DB::table('corporation_wallet_journals')
                ->whereMonth('date', $lastMonth->month)
                ->whereYear('date', $lastMonth->year);

            if ($corporationId) {
                $lastMonthQuery->where('corporation_id', $corporationId);
                $lastMonthQuery = JournalFilters::excludeInternalTransfers($lastMonthQuery, (int) $corporationId);
            } else {
                $lastMonthQuery = JournalFilters::excludeInternalTransfers($lastMonthQuery);
            }

            $lastMonthTransactions = $lastMonthQuery->count();

            // Calculate change
            $change = $lastMonthTransactions > 0
                ? (($currentTransactions - $lastMonthTransactions) / $lastMonthTransactions) * 100
                : 0;

            // Determine activity level
            $level = 'Low';
            if ($currentTransactions >= 1000) {
                $level = 'Very High';
            } elseif ($currentTransactions >= 500) {
                $level = 'High';
            } elseif ($currentTransactions >= 250) {
                $level = 'Medium';
            } elseif ($currentTransactions >= 100) {
                $level = 'Low-Medium';
            }

            return response()->json([
                'level' => $level,
                'transactions' => $currentTransactions,
                'change' => round($change, 1),
                'last_month_transactions' => $lastMonthTransactions,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Member activity API error', ['error' => $e->getMessage()]);
            return response()->json([
                'level' => 'Unknown',
                'transactions' => 0,
                'change' => 0,
            ], 500);
        }
    }
    
    /**
     * Get performance metrics for radar chart
     */
    public function memberPerformanceMetrics(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            // Calculate stability (based on daily variance)
            $stabilityScore = $this->calculateStabilityScore($corporationId);
            
            // Calculate growth score
            $growthScore = $this->calculateGrowthScore($corporationId);
            
            // Calculate activity score
            $activityScore = $this->calculateActivityScore($corporationId);
            
            // Calculate efficiency score
            $efficiencyScore = $this->calculateEfficiencyScore($corporationId);
            
            // Calculate compliance score (based on positive days)
            $complianceScore = $this->calculateComplianceScore($corporationId);
            
            return response()->json([
                'metrics' => [
                    'stability' => round($stabilityScore, 1),
                    'growth' => round($growthScore, 1),
                    'activity' => round($activityScore, 1),
                    'efficiency' => round($efficiencyScore, 1),
                    'compliance' => round($complianceScore, 1),
                ],
                'labels' => ['Stability', 'Growth', 'Activity', 'Efficiency', 'Compliance'],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Member performance metrics API error', ['error' => $e->getMessage()]);
            return response()->json([
                'metrics' => [
                    'stability' => 50,
                    'growth' => 50,
                    'activity' => 50,
                    'efficiency' => 50,
                    'compliance' => 50,
                ],
                'labels' => ['Stability', 'Growth', 'Activity', 'Efficiency', 'Compliance'],
            ], 500);
        }
    }
    
    /**
     * Get weekly activity pattern
     */
    public function memberWeeklyPattern(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            $weeks = 4; // Last 4 weeks
            
            $startDate = Carbon::now()->subWeeks($weeks)->startOfWeek();
            
            $query = DB::table('corporation_wallet_journals')
                ->where('date', '>=', $startDate)
                ->selectRaw('
                    DAYOFWEEK(date) as day_of_week,
                    COUNT(*) as transaction_count,
                    SUM(ABS(amount)) as volume
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
            
            // Map to day names and calculate activity percentage
            $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $maxTransactions = $data->max('transaction_count') ?: 1;
            
            $patterns = [];
            $labels = [];
            $activity = [];
            
            // Initialize all days with 0
            for ($i = 1; $i <= 7; $i++) {
                $dayData = $data->firstWhere('day_of_week', $i);
                $dayIndex = $i - 1;
                $dayName = $dayNames[$dayIndex];
                
                // Remap to Monday-Sunday order
                $displayOrder = [
                    'Monday' => 0,
                    'Tuesday' => 1,
                    'Wednesday' => 2,
                    'Thursday' => 3,
                    'Friday' => 4,
                    'Saturday' => 5,
                    'Sunday' => 6,
                ];
                
                $orderIndex = $displayOrder[$dayName];
                
                if ($dayData) {
                    $activityLevel = ($dayData->transaction_count / $maxTransactions) * 100;
                } else {
                    $activityLevel = 0;
                }
                
                $patterns[$orderIndex] = [
                    'day' => $dayName,
                    'transactions' => $dayData ? $dayData->transaction_count : 0,
                    'volume' => $dayData ? (float)$dayData->volume : 0,
                    'activity' => round($activityLevel, 1),
                ];
            }
            
            // Sort by display order
            ksort($patterns);
            
            // Extract for chart
            foreach ($patterns as $pattern) {
                $labels[] = substr($pattern['day'], 0, 3); // Mon, Tue, etc.
                $activity[] = $pattern['activity'];
            }
            
            // Find best and worst days
            $maxActivity = max($activity);
            $minActivity = min($activity);
            $bestDayIndex = array_search($maxActivity, $activity);
            $worstDayIndex = array_search($minActivity, $activity);
            
            return response()->json([
                'labels' => $labels,
                'activity' => $activity,
                'patterns' => array_values($patterns),
                'best_day' => $labels[$bestDayIndex] ?? 'N/A',
                'worst_day' => $labels[$worstDayIndex] ?? 'N/A',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Member weekly pattern API error', ['error' => $e->getMessage()]);
            return response()->json([
                'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'activity' => [0, 0, 0, 0, 0, 0, 0],
                'patterns' => [],
                'best_day' => 'N/A',
                'worst_day' => 'N/A',
            ], 500);
        }
    }
    
    /**
     * Get comprehensive monthly summary
     */
    public function memberMonthlySummary(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            
            // Get balance change
            $summaryData = $this->summary($request)->getData();
            
            // Get current month transactions
            $currentMonth = Carbon::now();
            $transactionQuery = DB::table('corporation_wallet_journals')
                ->whereMonth('date', $currentMonth->month)
                ->whereYear('date', $currentMonth->year);

            if ($corporationId) {
                $transactionQuery->where('corporation_id', $corporationId);
                $transactionQuery = JournalFilters::excludeInternalTransfers($transactionQuery, (int) $corporationId);
            } else {
                $transactionQuery = JournalFilters::excludeInternalTransfers($transactionQuery);
            }

            $currentTransactions = $transactionQuery->count();

            // Last month transactions
            $lastMonth = Carbon::now()->subMonth();
            $lastMonthQuery = DB::table('corporation_wallet_journals')
                ->whereMonth('date', $lastMonth->month)
                ->whereYear('date', $lastMonth->year);

            if ($corporationId) {
                $lastMonthQuery->where('corporation_id', $corporationId);
                $lastMonthQuery = JournalFilters::excludeInternalTransfers($lastMonthQuery, (int) $corporationId);
            } else {
                $lastMonthQuery = JournalFilters::excludeInternalTransfers($lastMonthQuery);
            }

            $lastMonthTransactions = $lastMonthQuery->count();
            
            // Calculate activity change
            $activityChange = $lastMonthTransactions > 0 
                ? (($currentTransactions - $lastMonthTransactions) / $lastMonthTransactions) * 100
                : 0;
            
            // Calculate days positive
            $daysPositive = $this->calculateDaysPositive($corporationId);
            
            // Calculate stability index (inverse of volatility)
            $volatility = $this->calculateVolatility($corporationId);
            $stabilityIndex = max(0, 100 - $volatility);
            
            return response()->json([
                'current_balance' => $summaryData->current_month->balance ?? 0,
                'balance_change_percent' => $summaryData->change->percent ?? 0,
                'monthly_transactions' => $currentTransactions,
                'activity_change_percent' => round($activityChange, 1),
                'days_positive' => $daysPositive,
                'stability_index' => round($stabilityIndex, 1),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Member monthly summary API error', ['error' => $e->getMessage()]);
            return response()->json([
                'current_balance' => 0,
                'balance_change_percent' => 0,
                'monthly_transactions' => 0,
                'activity_change_percent' => 0,
                'days_positive' => 0,
                'stability_index' => 0,
            ], 500);
        }
    }
    
    // === HELPER METHODS FOR CALCULATIONS ===
    
    private function calculateStabilityScore($corporationId)
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        $query = DB::table('corporation_wallet_journals')
            ->where('date', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(date) as day, SUM(amount) as daily_change')
            ->groupBy('day');

        if ($corporationId) {
            $query->where('corporation_id', $corporationId);
            $query = JournalFilters::excludeInternalTransfers($query, (int) $corporationId);
        } else {
            $query = JournalFilters::excludeInternalTransfers($query);
        }

        $dailyChanges = $query->pluck('daily_change')->toArray();

        if (empty($dailyChanges)) {
            return 50;
        }

        // Calculate coefficient of variation
        $mean = array_sum($dailyChanges) / count($dailyChanges);
        if ($mean == 0) {
            return 50;
        }
        
        $variance = 0;
        foreach ($dailyChanges as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= count($dailyChanges);
        $stdDev = sqrt($variance);
        
        $coefficientOfVariation = abs($stdDev / $mean);
        
        // Convert to 0-100 score (lower CV = higher stability)
        $stabilityScore = max(0, min(100, 100 - ($coefficientOfVariation * 50)));
        
        return $stabilityScore;
    }
    
    private function calculateGrowthScore($corporationId)
    {
        $currentMonth = Carbon::now()->format('Y-m');
        $lastMonth = Carbon::now()->subMonth()->format('Y-m');
        
        $currentQuery = MonthlyBalance::where('month', $currentMonth);
        $lastQuery = MonthlyBalance::where('month', $lastMonth);
        
        if ($corporationId) {
            $currentQuery->where('corporation_id', $corporationId);
            $lastQuery->where('corporation_id', $corporationId);
        }
        
        $currentBalance = (float)($currentQuery->sum('balance') ?? 0);
        $lastBalance = (float)($lastQuery->sum('balance') ?? 0);
        
        if ($lastBalance <= 0) {
            return $currentBalance > 0 ? 100 : 0;
        }
        
        $growthRate = (($currentBalance - $lastBalance) / $lastBalance) * 100;
        
        // Convert to 0-100 score
        // 20% growth = 100 score, -20% = 0 score
        $growthScore = max(0, min(100, 50 + ($growthRate * 2.5)));
        
        return $growthScore;
    }
    
    private function calculateActivityScore($corporationId)
    {
        $currentMonth = Carbon::now();
        
        $query = DB::table('corporation_wallet_journals')
            ->whereMonth('date', $currentMonth->month)
            ->whereYear('date', $currentMonth->year);

        if ($corporationId) {
            $query->where('corporation_id', $corporationId);
            $query = JournalFilters::excludeInternalTransfers($query, (int) $corporationId);
        } else {
            $query = JournalFilters::excludeInternalTransfers($query);
        }

        $transactions = $query->count();

        // Score based on transaction count
        // 1000+ transactions = 100 score
        $activityScore = min(100, ($transactions / 1000) * 100);
        
        return $activityScore;
    }
    
    private function calculateEfficiencyScore($corporationId)
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        $query = DB::table('corporation_wallet_journals')
            ->where('date', '>=', $thirtyDaysAgo)
            ->selectRaw('
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses
            ');

        if ($corporationId) {
            $query->where('corporation_id', $corporationId);
            $query = JournalFilters::excludeInternalTransfers($query, (int) $corporationId);
        } else {
            $query = JournalFilters::excludeInternalTransfers($query);
        }

        $result = $query->first();

        if (!$result || $result->expenses == 0) {
            return 50;
        }
        
        $ratio = $result->income / $result->expenses;
        
        // Ratio of 1.5+ = 100 score, 0.5 = 0 score
        $efficiencyScore = max(0, min(100, ($ratio - 0.5) * 66.67));
        
        return $efficiencyScore;
    }
    
    private function calculateComplianceScore($corporationId)
    {
        $daysPositive = $this->calculateDaysPositive($corporationId);
        
        // 30 days positive = 100 score
        $complianceScore = min(100, ($daysPositive / 30) * 100);
        
        return $complianceScore;
    }
    
    private function calculateVolatility($corporationId)
    {
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        
        $query = DB::table('corporation_wallet_journals')
            ->where('date', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(date) as day, SUM(amount) as daily_change')
            ->groupBy('day');

        if ($corporationId) {
            $query->where('corporation_id', $corporationId);
            $query = JournalFilters::excludeInternalTransfers($query, (int) $corporationId);
        } else {
            $query = JournalFilters::excludeInternalTransfers($query);
        }

        $dailyChanges = $query->pluck('daily_change')->toArray();

        if (count($dailyChanges) < 2) {
            return 0;
        }
        
        $mean = array_sum($dailyChanges) / count($dailyChanges);
        if ($mean == 0) {
            return 50;
        }
        
        $variance = 0;
        foreach ($dailyChanges as $value) {
            $variance += pow($value - $mean, 2);
        }
        $variance /= (count($dailyChanges) - 1);
        $stdDev = sqrt($variance);
        
        // Return as percentage
        return abs(($stdDev / abs($mean)) * 100);
    }
    
    /**
     * Personal contribution for the logged-in user for the given period.
     *
     * Returns the viewer's monthly contribution rolled up across every
     * character they own (alts roll into the main, alt_count exposed).
     * Includes rank + percentile vs the corp cohort, trend vs prior
     * period, and the per-bucket strip the sparkline renders. Applies
     * NPC + corp-self guards on character ids.
     *
     * Scope rule: rolls up across ALL the user's owned characters
     * regardless of current corp affiliation. The viewer's lifetime
     * contribution to this corp should reflect every character that
     * ever participated, including an old main who later moved to a
     * different corp. The leaderboard surface (getTopContributors)
     * has the opposite policy - it filters to current corp members
     * only - and the difference is intentional.
     *
     * Income-only rule: total_amount in the response is the sum of
     * the five positive contribution buckets (ratting + mission +
     * industry + tax_payment + donation_voluntary). The withdrawal
     * bucket is still surfaced under by_bucket.withdrawal as an
     * informational field but does NOT roll into total_amount; that
     * matches the way the corp leaderboard ranks rows so the
     * card-vs-leaderboard reconciliation is meaningful.
     */
    public function personalContribution(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);

            $period = $request->get('period');
            if (! preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
                $period = Carbon::now()->format('Y-m');
            }

            $emptyShape = [
                'corporation_id' => $corporationId,
                'period'         => $period,
                'main_character' => null,
                'alt_count'      => 0,
                'total_amount'   => 0.0,
                'by_bucket'      => [
                    'ratting' => 0.0, 'mission' => 0.0, 'industry' => 0.0,
                    'tax_payment' => 0.0, 'donation_voluntary' => 0.0, 'withdrawal' => 0.0,
                ],
                'rank'           => null,
                'rank_total'     => 0,
                'percentile'     => null,
                'prior_total'    => null,
                'trend_pct'      => null,
                'months_active'  => 0,
                'lifetime_total' => 0.0,
            ];

            if (! $corporationId) {
                return response()->json($emptyShape);
            }

            // Aggregate across ALL the user's owned characters, regardless of
            // which corp those characters are currently in. The corp scoping
            // happens at the contribution-cache query below via the
            // `WHERE corporation_id = $thisCorp` predicate paired with the
            // `whereIn('character_id', ...)` set. This way a main who moved
            // out of the corp still shows their lifetime contribution to it.
            $characterIds = $this->viewerOwnedCharacterIds();
            if (empty($characterIds)) {
                return response()->json($emptyShape);
            }

            $userId = (int) auth()->id();

            // ----- Cache layer ---------------------------------------------
            // Per-user, per-corp, per-period key. The character-id crc32
            // suffix invalidates the entry naturally when the viewer links
            // a new alt or revokes one (rare, but cheap to detect). The
            // hourly classifier cron rewrites the underlying cache table,
            // so a 5-minute TTL keeps the surface at most ~5 minutes
            // staler than the upstream cache without ever serving wildly
            // outdated numbers.
            //
            // When `?refresh=1` is present we skip the read (the refresh
            // button on the tab nav passes it) but still WRITE the fresh
            // value back so the next non-refresh hit is fast again.
            $charIdHash = crc32(implode(',', $characterIds));
            $cacheKey   = sprintf('cwm:personal-contribution:%d:%d:%s:%u', $userId, (int) $corporationId, $period, $charIdHash);
            $ttl        = 300; // 5 minutes
            $refresh    = $request->boolean('refresh');
            if (! $refresh) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    return response()->json($cached);
                }
            }

            // Resolve the viewer's main character (defaults to the user's
            // main_character_id when set, else the first character we have).
            $mainCharacterId = (int) (DB::table('users')->where('id', $userId)->value('main_character_id') ?? 0);
            if ($mainCharacterId <= 0 || ! in_array($mainCharacterId, $characterIds, true)) {
                $mainCharacterId = (int) $characterIds[0];
            }

            $mainName = (string) (DB::table('character_infos')
                ->where('character_id', $mainCharacterId)
                ->value('name') ?? ('Character ' . $mainCharacterId));

            $priorPeriod = $this->priorPeriod($period);

            // ----- Fused aggregate query -----------------------------------
            // Single round-trip that produces every header figure for both
            // the headline strip (current totals + buckets + withdrawal),
            // the trend pill (prior-period income), the lifetime block
            // (lifetime income + months_active), all via conditional sums
            // over a single scan of the viewer's contribution-cache rows.
            //
            // This replaces what used to be FOUR separate queries (current
            // / prior / lifetime / months_active) plus a GROUP BY enumerate
            // for the months count, which all repeated the same
            // `WHERE corporation_id = ? AND character_id IN (...)` slice.
            //
            // EXPLAIN result: ref scan on
            // `corpwalletmanager_character_contributions` using the
            // `cwm_char_contrib_corp_period` index (corp+period composite)
            // for the per-period buckets and `cwm_char_contrib_unique`
            // (corp+char+period) for the lifetime slice; estimated rows
            // = (months a viewer has been in the corp) * (alt count),
            // typically well under a thousand even for a long-tenured
            // 10-alt main.
            $incomeExpr = '(COALESCE(ratting_amount,0) ' .
                ' + COALESCE(mission_amount,0) ' .
                ' + COALESCE(industry_amount,0) ' .
                ' + COALESCE(tax_payment_amount,0) ' .
                ' + COALESCE(donation_voluntary_amount,0))';

            $agg = DB::table('corpwalletmanager_character_contributions')
                ->where('corporation_id', $corporationId)
                ->whereIn('character_id', $characterIds)
                ->where('character_id', '>=', 90000000)
                ->whereColumn('character_id', '!=', 'corporation_id')
                ->selectRaw(
                    // Current-period buckets + total (the headline strip).
                    'COALESCE(SUM(CASE WHEN period = ? THEN COALESCE(ratting_amount,0)            ELSE 0 END), 0) AS ratting, ' .
                    'COALESCE(SUM(CASE WHEN period = ? THEN COALESCE(mission_amount,0)            ELSE 0 END), 0) AS mission, ' .
                    'COALESCE(SUM(CASE WHEN period = ? THEN COALESCE(industry_amount,0)           ELSE 0 END), 0) AS industry, ' .
                    'COALESCE(SUM(CASE WHEN period = ? THEN COALESCE(tax_payment_amount,0)        ELSE 0 END), 0) AS tax_payment, ' .
                    'COALESCE(SUM(CASE WHEN period = ? THEN COALESCE(donation_voluntary_amount,0) ELSE 0 END), 0) AS donation_voluntary, ' .
                    'COALESCE(SUM(CASE WHEN period = ? THEN COALESCE(withdrawal_amount,0)         ELSE 0 END), 0) AS withdrawal, ' .
                    'COALESCE(SUM(CASE WHEN period = ? THEN ' . $incomeExpr . ' ELSE 0 END), 0) AS current_total, ' .
                    // Prior-period income total (drives the trend pill).
                    'COALESCE(SUM(CASE WHEN period = ? THEN ' . $incomeExpr . ' ELSE 0 END), 0) AS prior_total, ' .
                    // Lifetime + active-months stay row-grained: COUNT of
                    // distinct periods with positive income is the
                    // "months_active" metric, and SUM across every row is
                    // the lifetime income (withdrawal excluded by policy).
                    'COALESCE(SUM(' . $incomeExpr . '), 0) AS lifetime_total, ' .
                    'COUNT(DISTINCT CASE WHEN ' . $incomeExpr . ' > 0 THEN period END) AS months_active',
                    [$period, $period, $period, $period, $period, $period, $period, $priorPeriod]
                )
                ->first();

            $totalAmount = (float) ($agg->current_total ?? 0);
            $priorTotal  = (float) ($agg->prior_total ?? 0);
            $lifetime    = (float) ($agg->lifetime_total ?? 0);
            $monthsActive = (int) ($agg->months_active ?? 0);

            // Rank vs the corp cohort (main-grouped totals). Lean SQL-only
            // impl - we skip the full leaderboard build (name resolution +
            // MM tax lookups for 1000 entries would dominate the request).
            $rankInfo = $this->computeViewerRankFast((int) $corporationId, $period, $characterIds);

            $trendPct = null;
            if ($priorTotal > 0.0) {
                $pct = (($totalAmount - $priorTotal) / $priorTotal) * 100.0;
                $trendPct = max(-1000.0, min(1000.0, $pct));
            }

            $payload = [
                'corporation_id' => (int) $corporationId,
                'period'         => $period,
                'main_character' => [
                    'id'      => $mainCharacterId,
                    'name'    => $mainName,
                    'is_main' => true,
                ],
                'alt_count'      => max(0, count(array_unique($characterIds)) - 1),
                'total_amount'   => $totalAmount,
                'by_bucket'      => [
                    'ratting'            => (float) ($agg->ratting ?? 0),
                    'mission'            => (float) ($agg->mission ?? 0),
                    'industry'           => (float) ($agg->industry ?? 0),
                    'tax_payment'        => (float) ($agg->tax_payment ?? 0),
                    'donation_voluntary' => (float) ($agg->donation_voluntary ?? 0),
                    'withdrawal'         => (float) ($agg->withdrawal ?? 0),
                ],
                'rank'           => $rankInfo['rank'],
                'rank_total'     => $rankInfo['rank_total'],
                'percentile'     => $rankInfo['percentile'],
                'prior_total'    => $priorTotal > 0.0 ? $priorTotal : null,
                'trend_pct'      => $trendPct,
                'months_active'  => $monthsActive,
                'lifetime_total' => $lifetime,
            ];

            Cache::put($cacheKey, $payload, $ttl);

            return response()->json($payload);
        } catch (\Exception $e) {
            Log::error('personalContribution failed', ['error' => $e->getMessage()]);
            return response()->json([
                'corporation_id' => null,
                'period'         => Carbon::now()->format('Y-m'),
                'main_character' => null,
                'alt_count'      => 0,
                'total_amount'   => 0.0,
                'by_bucket'      => [
                    'ratting' => 0.0, 'mission' => 0.0, 'industry' => 0.0,
                    'tax_payment' => 0.0, 'donation_voluntary' => 0.0, 'withdrawal' => 0.0,
                ],
                'rank'           => null,
                'rank_total'     => 0,
                'percentile'     => null,
                'prior_total'    => null,
                'trend_pct'      => null,
                'months_active'  => 0,
                'lifetime_total' => 0.0,
            ], 500);
        }
    }

    /**
     * Top contributors leaderboard with the operator-configured privacy
     * mode applied SERVER-SIDE. Members never see hidden values via
     * devtools because the masked fields are nulled out before the
     * response leaves the controller.
     *
     * Mode handling:
     *   - isk_visible: total_amount + pct_of_corp both kept.
     *   - percentage:  total_amount still in the payload (the % is
     *                  derived from it on the server); frontend renders
     *                  only the % column. Sending both lets the viewer's
     *                  own row's % math match for free.
     *   - rank_only:   total_amount AND pct_of_corp NULLED OUT so the
     *                  response carries no raw numbers at all.
     *
     * Always returns the viewer's own row at viewer_row when they are
     * outside the top N (so they always see their rank). Main-character
     * grouping mirrors the director leaderboard so a contributor's row
     * here matches the row a director would see at the same period.
     */
    public function memberLeaderboard(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            $period = $request->get('period');
            if (! preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
                $period = Carbon::now()->format('Y-m');
            }

            // Operator-configured mode + size; mode is the privacy gate.
            $mode = (string) Settings::getSetting('member_leaderboard_mode', 'isk_visible');
            if (! in_array($mode, ['isk_visible', 'percentage', 'rank_only'], true)) {
                $mode = 'isk_visible';
            }
            $size = (int) Settings::getIntegerSetting('member_leaderboard_size', 10);
            if (! in_array($size, [5, 10, 20], true)) {
                $size = 10;
            }

            $emptyShape = [
                'corporation_id' => $corporationId,
                'period'         => $period,
                'mode'           => $mode,
                'size'           => $size,
                'corp_total'     => 0.0,
                'top'            => [],
                'viewer_row'     => null,
                'viewer_in_top'  => false,
            ];

            if (! $corporationId) {
                return response()->json($emptyShape);
            }

            // Reuse the director's main-character-grouped leaderboard so
            // both surfaces stay reconcilable. Ask for size + 1 first;
            // if the viewer isn't in there, we top up with their row.
            $top = app(\CorpWalletManager\Services\ContributionService::class)
                ->getTopContributors((int) $corporationId, $period, max($size, 50));

            $contributors = $top['contributors'] ?? [];
            if (empty($contributors)) {
                return response()->json($emptyShape);
            }

            // Compute corp total ONCE across the full main-grouped set so
            // pct_of_corp denominators are stable regardless of trim.
            $corpTotal = 0.0;
            foreach ($contributors as $row) {
                $corpTotal += (float) ($row['total_contribution_amount'] ?? 0);
            }

            // Annotate every row with rank, is_viewer, and the safe-to-display
            // alt_count + character_name. The director service already
            // resolved names through EntityNameResolver, so we just forward.
            // Use the corp-agnostic owner set so a member's row is still
            // flagged "you" even when one of their alts (or their old main)
            // sits in the corp but they themselves are signed in elsewhere.
            $viewerCharacterIds = $this->viewerOwnedCharacterIds();
            $viewerMainSet = $this->mainsForViewerCharacters($viewerCharacterIds);

            $annotated = [];
            $rank = 0;
            foreach ($contributors as $row) {
                $rank++;
                $total = (float) ($row['total_contribution_amount'] ?? 0);
                $pct = $corpTotal > 0.0 ? ($total / $corpTotal) * 100.0 : 0.0;
                $charId = (int) ($row['character_id'] ?? $row['main_character_id'] ?? 0);
                $isViewer = $charId > 0 && in_array($charId, $viewerMainSet, true);

                $annotated[] = [
                    'rank'           => $rank,
                    'character_id'   => $charId,
                    'character_name' => (string) ($row['character_name'] ?? ('Character ' . $charId)),
                    'total_amount'   => $total,
                    'pct_of_corp'    => $pct,
                    'alt_count'      => (int) ($row['alt_count'] ?? 0),
                    'is_viewer'      => $isViewer,
                ];
            }

            // Pick top N, then locate the viewer's row in the full set.
            $topRows = array_slice($annotated, 0, $size);
            $viewerRow = null;
            $viewerInTop = false;
            foreach ($topRows as $r) {
                if ($r['is_viewer']) {
                    $viewerInTop = true;
                    break;
                }
            }
            if (! $viewerInTop) {
                foreach ($annotated as $r) {
                    if ($r['is_viewer']) {
                        $viewerRow = $r;
                        break;
                    }
                }
            }

            // Apply the privacy mode SERVER-SIDE. rank_only NULLS both
            // total_amount and pct_of_corp so a curious member opening
            // devtools can't peek at the raw numbers we are supposed to
            // be hiding. percentage keeps total_amount because the % is
            // derived from it; the frontend simply doesn't render the ISK
            // column in that mode.
            $applyMode = static function (array &$row) use ($mode): void {
                if ($mode === 'rank_only') {
                    $row['total_amount'] = null;
                    $row['pct_of_corp']  = null;
                }
            };
            foreach ($topRows as &$r) {
                $applyMode($r);
            }
            unset($r);
            if ($viewerRow !== null) {
                $applyMode($viewerRow);
            }

            return response()->json([
                'corporation_id' => (int) $corporationId,
                'period'         => $period,
                'mode'           => $mode,
                'size'           => $size,
                'corp_total'     => $mode === 'rank_only' ? null : $corpTotal,
                'top'            => array_values($topRows),
                'viewer_row'     => $viewerRow,
                'viewer_in_top'  => $viewerInTop,
            ]);
        } catch (\Exception $e) {
            Log::error('memberLeaderboard failed', ['error' => $e->getMessage()]);
            return response()->json([
                'corporation_id' => null,
                'period'         => Carbon::now()->format('Y-m'),
                'mode'           => 'isk_visible',
                'size'           => 10,
                'corp_total'     => 0.0,
                'top'            => [],
                'viewer_row'     => null,
                'viewer_in_top'  => false,
            ], 500);
        }
    }

    /**
     * Personal Mining Manager tax compliance for the viewer's characters
     * in this corp + period. Wraps
     * ContributionService::getCharacterTaxCompliance.
     *
     * Returns mm_available=false (and only that key) when MM is absent
     * so the frontend can suppress the card without confusing operators
     * with an "ok 100%" reading on missing data.
     */
    public function personalMmCompliance(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            $period = $request->get('period');
            if (! preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
                $period = Carbon::now()->format('Y-m');
            }

            $service = app(\CorpWalletManager\Services\ContributionService::class);
            if (! $service->isMmInstalled()) {
                return response()->json(['mm_available' => false]);
            }
            if (! $corporationId) {
                return response()->json(['mm_available' => true, 'corporation_id' => null]);
            }

            // Aggregate compliance across every character the user owns.
            // MM's tax service already takes (character_id, corporation_id),
            // so feeding it owned chars from any current corp keeps the
            // historical compliance picture intact when a member has moved
            // chars between corps.
            $characterIds = $this->viewerOwnedCharacterIds();
            if (empty($characterIds)) {
                return response()->json([
                    'corporation_id' => (int) $corporationId,
                    'period'         => $period,
                    'mm_available'   => true,
                    'amount_owed'    => 0.0,
                    'amount_paid'    => 0.0,
                    'compliance_pct' => null,
                    'consecutive_overdue_periods' => 0,
                    'breakdown_by_character' => [],
                ]);
            }

            $totalOwed = 0.0;
            $totalPaid = 0.0;
            $worstConsecutive = 0;
            $breakdown = [];

            foreach ($characterIds as $cid) {
                if ($cid < 90000000 || $cid === (int) $corporationId) {
                    continue;
                }
                // Always ask for trailing 3 months so consecutive_overdue
                // has enough window; the displayed pair is the current period
                // owed/paid from the by_period[0] slot.
                $result = $service->getCharacterTaxCompliance((int) $cid, (int) $corporationId, 3);
                if ($result === null) {
                    continue;
                }
                $byPeriod = $result['by_period'] ?? [];
                $currentSlot = null;
                foreach ($byPeriod as $row) {
                    if ((string) ($row['period'] ?? '') === $period) {
                        $currentSlot = $row;
                        break;
                    }
                }
                $owed = (float) ($currentSlot['owed'] ?? 0);
                $paid = (float) ($currentSlot['paid'] ?? 0);
                $totalOwed += $owed;
                $totalPaid += $paid;
                $worstConsecutive = max($worstConsecutive, (int) ($result['consecutive_overdue'] ?? 0));

                $charName = (string) (DB::table('character_infos')->where('character_id', $cid)->value('name') ?? ('Character ' . $cid));
                $breakdown[] = [
                    'character_id'   => $cid,
                    'character_name' => $charName,
                    'amount_owed'    => $owed,
                    'amount_paid'    => $paid,
                    'compliance_pct' => $owed > 0.0 ? min(100.0, ($paid / $owed) * 100.0) : null,
                ];
            }

            $compliancePct = $totalOwed > 0.0
                ? min(100.0, ($totalPaid / $totalOwed) * 100.0)
                : null;

            return response()->json([
                'corporation_id'               => (int) $corporationId,
                'period'                       => $period,
                'mm_available'                 => true,
                'amount_owed'                  => $totalOwed,
                'amount_paid'                  => $totalPaid,
                'compliance_pct'               => $compliancePct,
                'consecutive_overdue_periods'  => $worstConsecutive,
                'breakdown_by_character'       => $breakdown,
            ]);
        } catch (\Exception $e) {
            Log::error('personalMmCompliance failed', ['error' => $e->getMessage()]);
            return response()->json(['mm_available' => false], 500);
        }
    }

    /**
     * Personal milestone ladder for the viewer in this corp. Reads
     * `corpwalletmanager_member_milestone_state.highest_milestone_isk`
     * for the viewer's characters, surfaces the rungs reached + the
     * next rung's percent-to-go, and returns the recent crossings.
     *
     * Only emits lifetime milestones (1B/5B/10B/25B/50B/100B); the
     * stall / compliance ones from the state row are HR-facing, not
     * member-facing, and are deliberately excluded.
     */
    public function personalMilestones(Request $request)
    {
        try {
            $corporationId = $this->getCorporationId($request);
            $emptyShape = [
                'corporation_id' => $corporationId,
                'reached'        => [],
                'next_milestone' => null,
            ];
            if (! $corporationId) {
                return response()->json($emptyShape);
            }

            // Milestones rolled up across every character the user owns.
            // The state row + the contribution cache are both keyed by
            // (corporation_id, character_id), so the cache query below
            // intersects the owner set with the requested corp - chars who
            // never contributed to this corp simply have no rows and don't
            // double-count milestones from a different corp.
            $characterIds = $this->viewerOwnedCharacterIds();
            if (empty($characterIds)) {
                return response()->json($emptyShape);
            }

            $ladder = [
                1_000_000_000   => '1B',
                5_000_000_000   => '5B',
                10_000_000_000  => '10B',
                25_000_000_000  => '25B',
                50_000_000_000  => '50B',
                100_000_000_000 => '100B',
            ];

            $stateRows = DB::table('corpwalletmanager_member_milestone_state')
                ->where('corporation_id', $corporationId)
                ->whereIn('character_id', $characterIds)
                ->where('character_id', '>=', 90000000)
                ->whereColumn('character_id', '!=', 'corporation_id')
                ->get(['character_id', 'highest_milestone_isk', 'updated_at']);

            // Lifetime totals across the viewer's characters (used both
            // for the per-character "next milestone" projection and the
            // group-level next-rung calculation).
            $lifetimePerChar = DB::table('corpwalletmanager_character_contributions')
                ->where('corporation_id', $corporationId)
                ->whereIn('character_id', $characterIds)
                ->where('character_id', '>=', 90000000)
                ->whereColumn('character_id', '!=', 'corporation_id')
                ->selectRaw('character_id, SUM(total_contribution_amount) AS lifetime')
                ->groupBy('character_id')
                ->pluck('lifetime', 'character_id');

            $charNames = DB::table('character_infos')
                ->whereIn('character_id', $characterIds)
                ->pluck('name', 'character_id');

            $reached = [];
            $highestAcrossAll = 0.0;
            foreach ($stateRows as $row) {
                $cid = (int) $row->character_id;
                $highestForChar = (float) ($row->highest_milestone_isk ?? 0);
                if ($highestForChar <= 0) {
                    continue;
                }
                $highestAcrossAll = max($highestAcrossAll, $highestForChar);
                // The state row only records the highest rung crossed, so
                // we synthesise the entry from the rung + when the row
                // was last updated (best available "crossed_at" signal).
                $rungLabel = $ladder[(int) $highestForChar] ?? null;
                if ($rungLabel === null) {
                    continue;
                }
                $reached[] = [
                    'milestone'       => $rungLabel,
                    'crossed_at'      => (string) ($row->updated_at !== null
                        ? Carbon::parse($row->updated_at)->format('Y-m-d')
                        : Carbon::now()->format('Y-m-d')),
                    'character_id'    => $cid,
                    'character_name'  => (string) ($charNames[$cid] ?? ('Character ' . $cid)),
                ];
            }

            // Sort newest first by crossed_at.
            usort($reached, fn ($a, $b) => strcmp($b['crossed_at'], $a['crossed_at']));

            // Next milestone is the next rung above the highest already
            // crossed. We use the viewer's GROUP lifetime (sum across
            // characters in this corp) so the "74% of the way to 25B"
            // headline reflects everything they've contributed, not just
            // their highest single alt.
            $groupLifetime = (float) array_sum(array_map('floatval', $lifetimePerChar->toArray()));
            $nextMilestone = null;
            foreach ($ladder as $isk => $label) {
                if ($isk > $highestAcrossAll) {
                    $pctToNext = $isk > 0 ? min(100.0, ($groupLifetime / $isk) * 100.0) : 0.0;
                    $nextMilestone = [
                        'target'           => $label,
                        'current_lifetime' => $groupLifetime,
                        'pct_to_next'      => (float) $pctToNext,
                    ];
                    break;
                }
            }

            return response()->json([
                'corporation_id' => (int) $corporationId,
                'reached'        => $reached,
                'next_milestone' => $nextMilestone,
            ]);
        } catch (\Exception $e) {
            Log::error('personalMilestones failed', ['error' => $e->getMessage()]);
            return response()->json([
                'corporation_id' => null,
                'reached'        => [],
                'next_milestone' => null,
            ], 500);
        }
    }

    /**
     * Personal wallet snapshot for the viewer aggregated across every
     * character they own (every row in `refresh_tokens` for the user
     * with `deleted_at IS NULL`). Reads from the precomputed
     * `corpwalletmanager_personal_wallet_aggregates` table; the actual
     * scan of `character_wallet_journals` happens in the hourly
     * background job (PersonalWalletAggregator + ComputePersonalWallet
     * Aggregates) so the tab open is a small, bounded lookup.
     *
     * Returns the period's income / expense / net totals, the top 5 ref_type
     * sources for each direction, the top 5 single transactions for each
     * direction, a 6-month end-of-month balance sparkline (sum of each
     * character's stored end_of_month_balance per period), the prior
     * period's totals + trend %, and a per-character breakdown table so
     * members can see which alt is the big earner.
     *
     * Query budget per tab open (after the cache miss): three small
     * lookups against the aggregate table (current period across N
     * chars, prior period across N chars, sparkline across N chars x
     * 6 months) plus a names + main-character resolve. All bounded by
     * N (the viewer's alt count), never by journal volume.
     *
     * Empty-state handling: if no aggregate row exists for a (character,
     * period) tuple (newly installed plugin, or character not yet
     * aggregated) we render muted placeholders rather than fall back
     * to the live journal. The hourly cron (or the operator-run
     * backfill command after upgrading) fills the gap.
     */
    public function personalWalletStats(Request $request)
    {
        try {
            // Resolve the corp id only so the existing AuthorizesCorporationAccess
            // gate runs (consistent with the other personal endpoints), even
            // though the personal-wallet payload itself isn't corp-scoped.
            $corporationId = $this->getCorporationId($request);

            $period = $request->get('period');
            if (! preg_match('/^\d{4}-\d{2}$/', (string) $period)) {
                $period = Carbon::now()->format('Y-m');
            }

            $emptyShape = [
                'period'             => $period,
                'corporation_id'     => $corporationId,
                'characters'         => [],
                'aggregate' => [
                    'income_total'             => 0.0,
                    'expense_total'            => 0.0,
                    'net_flow'                 => 0.0,
                    'transaction_count'        => 0,
                    'top_income_sources'       => [],
                    'top_expense_sources'      => [],
                    'top_income_transactions'  => [],
                    'top_expense_transactions' => [],
                ],
                'by_character'      => new \stdClass(),
                'sparkline_balance' => [],
                'prior_period'      => [
                    'income_total'  => 0.0,
                    'expense_total' => 0.0,
                    'net_flow'      => 0.0,
                ],
                'trend' => [
                    'income_pct'  => null,
                    'expense_pct' => null,
                    'net_pct'     => null,
                ],
            ];

            $ownedIds = $this->viewerOwnedCharacterIds();
            if (empty($ownedIds)) {
                return response()->json($emptyShape);
            }

            $userId = (int) auth()->id();

            // ----- Cache layer ---------------------------------------------
            // Per-user, per-period key. The character-id crc32 suffix
            // naturally invalidates the entry when the viewer links or
            // revokes an alt. The aggregate table itself only refreshes
            // hourly, so a 5-minute TTL on top is cheap insurance against
            // a hot-tab burst all hammering the read endpoint.
            //
            // When `?refresh=1` is present (wired to the tab nav's
            // refresh button) we bypass the read but still write the
            // fresh value back so the next vanilla hit is fast.
            $charIdHash = crc32(implode(',', $ownedIds));
            $cacheKey   = sprintf('cwm:personal-wallet-stats:%d:%s:%u', $userId, $period, $charIdHash);
            $ttl        = 300; // 5 minutes
            $refresh    = $request->boolean('refresh');
            if (! $refresh) {
                $cached = Cache::get($cacheKey);
                if (is_array($cached)) {
                    return response()->json($cached);
                }
            }

            // Resolve display names for the per-character section. Missing
            // rows fall back to "Character {id}" so the table stays full
            // even if a character_infos row is missing.
            $charNames = DB::table('character_infos')
                ->whereIn('character_id', $ownedIds)
                ->pluck('name', 'character_id');

            // Mark the user's main so the table can pin / highlight it.
            $mainCharacterId = (int) (DB::table('users')->where('id', $userId)->value('main_character_id') ?? 0);

            $charactersMeta = [];
            foreach ($ownedIds as $cid) {
                $charactersMeta[] = [
                    'id'      => (int) $cid,
                    'name'    => (string) ($charNames[$cid] ?? ('Character ' . $cid)),
                    'is_main' => $cid === $mainCharacterId,
                ];
            }

            $priorPeriod = $this->priorPeriod($period);

            // ----- Aggregate-table reads -----------------------------------
            // The old implementation ran six on-demand SQL aggregates
            // against the raw character_wallet_journals on every tab
            // open. Even with proper indexing that scaled with the
            // size of the journal (years of history x N alts) and on
            // busy installs took minutes to return. The replacement
            // does the heavy work once an hour in the background and
            // serves reads from a small precomputed table keyed on
            // (character_id, period).
            //
            // Current period: one row per character the viewer owns.
            $currentRows = DB::table('corpwalletmanager_personal_wallet_aggregates')
                ->whereIn('character_id', $ownedIds)
                ->where('period', $period)
                ->get();

            // Prior period: one row per character the viewer owns. Used
            // for the trend % vs last month.
            $priorRows = DB::table('corpwalletmanager_personal_wallet_aggregates')
                ->whereIn('character_id', $ownedIds)
                ->where('period', $priorPeriod)
                ->get(['character_id', 'income_total', 'expense_total']);

            // Sparkline: one row per (character, period) for the
            // trailing 6 months. We sum end_of_month_balance per period
            // across characters in PHP.
            $sparkStart = Carbon::now()->subMonthsNoOverflow(5)->startOfMonth();
            $sparkPeriods = [];
            for ($i = 0; $i < 6; $i++) {
                $sparkPeriods[] = $sparkStart->copy()->addMonthsNoOverflow($i)->format('Y-m');
            }
            $sparkRows = DB::table('corpwalletmanager_personal_wallet_aggregates')
                ->whereIn('character_id', $ownedIds)
                ->whereIn('period', $sparkPeriods)
                ->get(['period', 'end_of_month_balance']);

            // ----- Merge current period across characters ------------------
            $incomeTotal  = 0.0;
            $expenseTotal = 0.0;
            $txnCount     = 0;

            // Initialise per-character buckets so a character with no
            // aggregate row this period still appears in the table as
            // zeros (the JS already renders that as "no activity").
            $byCharTotals = [];
            foreach ($ownedIds as $cid) {
                $byCharTotals[$cid] = [
                    'income_total'      => 0.0,
                    'expense_total'     => 0.0,
                    'net_flow'          => 0.0,
                    'transaction_count' => 0,
                ];
            }

            // Accumulators for the four merged top-5 lists.
            $refIncomeAcc  = [];  // ref_type => ['amount'=>F, 'count'=>I, 'label'=>S]
            $refExpenseAcc = [];
            $txIncomeAcc   = [];
            $txExpenseAcc  = [];

            foreach ($currentRows as $r) {
                $cid = (int) $r->character_id;
                $rIncome  = (float) ($r->income_total ?? 0);
                $rExpense = (float) ($r->expense_total ?? 0);
                $rTxn     = (int) ($r->transaction_count ?? 0);

                $incomeTotal  += $rIncome;
                $expenseTotal += $rExpense;
                $txnCount     += $rTxn;

                if (isset($byCharTotals[$cid])) {
                    $byCharTotals[$cid]['income_total']      = $rIncome;
                    $byCharTotals[$cid]['expense_total']     = $rExpense;
                    $byCharTotals[$cid]['net_flow']          = $rIncome - $rExpense;
                    $byCharTotals[$cid]['transaction_count'] = $rTxn;
                }

                // Merge per-character top-5 ref-type lists. Aggregator
                // already wrote one bounded JSON array per direction
                // per (character, period), so each character contributes
                // at most 5 entries here.
                $jsonIncomeRef  = json_decode((string) ($r->top_income_ref_types ?? '[]'), true) ?: [];
                $jsonExpenseRef = json_decode((string) ($r->top_expense_ref_types ?? '[]'), true) ?: [];
                $jsonIncomeTx   = json_decode((string) ($r->top_income_transactions ?? '[]'), true) ?: [];
                $jsonExpenseTx  = json_decode((string) ($r->top_expense_transactions ?? '[]'), true) ?: [];

                foreach ($jsonIncomeRef as $entry) {
                    $rt = (string) ($entry['ref_type'] ?? '');
                    if ($rt === '') continue;
                    if (! isset($refIncomeAcc[$rt])) {
                        $refIncomeAcc[$rt] = [
                            'ref_type' => $rt,
                            'label'    => (string) ($entry['label'] ?? $this->refTypeLabel($rt)),
                            'amount'   => 0.0,
                            'count'    => 0,
                        ];
                    }
                    $refIncomeAcc[$rt]['amount'] += (float) ($entry['amount'] ?? 0);
                    $refIncomeAcc[$rt]['count']  += (int) ($entry['count'] ?? 0);
                }
                foreach ($jsonExpenseRef as $entry) {
                    $rt = (string) ($entry['ref_type'] ?? '');
                    if ($rt === '') continue;
                    if (! isset($refExpenseAcc[$rt])) {
                        $refExpenseAcc[$rt] = [
                            'ref_type' => $rt,
                            'label'    => (string) ($entry['label'] ?? $this->refTypeLabel($rt)),
                            'amount'   => 0.0,
                            'count'    => 0,
                        ];
                    }
                    $refExpenseAcc[$rt]['amount'] += (float) ($entry['amount'] ?? 0);
                    $refExpenseAcc[$rt]['count']  += (int) ($entry['count'] ?? 0);
                }

                // For top transactions, stamp character_name now because
                // the aggregator stored character_id only.
                $charName = (string) ($charNames[$cid] ?? ('Character ' . $cid));
                foreach ($jsonIncomeTx as $entry) {
                    $entry['character_id']   = $cid;
                    $entry['character_name'] = $charName;
                    $entry['amount']         = (float) ($entry['amount'] ?? 0);
                    $txIncomeAcc[] = $entry;
                }
                foreach ($jsonExpenseTx as $entry) {
                    $entry['character_id']   = $cid;
                    $entry['character_name'] = $charName;
                    $entry['amount']         = (float) ($entry['amount'] ?? 0);
                    $txExpenseAcc[] = $entry;
                }
            }

            // Re-sort + trim merged ref-type lists to top 5.
            usort($refIncomeAcc, fn ($a, $b) => $b['amount'] <=> $a['amount']);
            usort($refExpenseAcc, fn ($a, $b) => $b['amount'] <=> $a['amount']);
            $topIncomeSources  = array_values(array_slice($refIncomeAcc, 0, 5));
            $topExpenseSources = array_values(array_slice($refExpenseAcc, 0, 5));

            // Re-sort + trim merged top transactions to top 5.
            usort($txIncomeAcc, fn ($a, $b) => $b['amount'] <=> $a['amount']);
            usort($txExpenseAcc, fn ($a, $b) => $b['amount'] <=> $a['amount']);
            $topIncomeTransactions  = array_values(array_slice($txIncomeAcc, 0, 5));
            $topExpenseTransactions = array_values(array_slice($txExpenseAcc, 0, 5));

            // ----- Prior period totals -------------------------------------
            $priorIncome  = 0.0;
            $priorExpense = 0.0;
            foreach ($priorRows as $r) {
                $priorIncome  += (float) ($r->income_total ?? 0);
                $priorExpense += (float) ($r->expense_total ?? 0);
            }
            $priorNet = $priorIncome - $priorExpense;

            $pct = static function (float $current, float $prior): ?float {
                if ($prior == 0.0) return null;
                $p = (($current - $prior) / abs($prior)) * 100.0;
                return max(-1000.0, min(1000.0, $p));
            };

            $currentNet = $incomeTotal - $expenseTotal;
            $trend = [
                'income_pct'  => $pct($incomeTotal, $priorIncome),
                'expense_pct' => $pct($expenseTotal, $priorExpense),
                'net_pct'     => $pct($currentNet, $priorNet),
            ];

            // ----- 6-month sparkline ---------------------------------------
            // Sum stored end_of_month_balance across the viewer's
            // characters per period; missing rows contribute zero so
            // the JS always sees a dense 6-point series.
            $sumByMonth = [];
            foreach ($sparkRows as $r) {
                $p = (string) $r->period;
                if (! isset($sumByMonth[$p])) {
                    $sumByMonth[$p] = 0.0;
                }
                $sumByMonth[$p] += (float) ($r->end_of_month_balance ?? 0);
            }
            $sparkline = [];
            foreach ($sparkPeriods as $p) {
                $sparkline[] = [
                    'period'  => $p,
                    'balance' => (float) ($sumByMonth[$p] ?? 0.0),
                ];
            }

            // Build the by_character map (numeric-keyed). Use a stdClass
            // sentinel so the JSON encoder emits an empty object rather
            // than an empty array when no characters are present.
            $byCharacter = [];
            foreach ($byCharTotals as $cid => $totals) {
                $byCharacter[(int) $cid] = [
                    'name'              => (string) ($charNames[$cid] ?? ('Character ' . $cid)),
                    'income_total'      => $totals['income_total'],
                    'expense_total'     => $totals['expense_total'],
                    'net_flow'          => $totals['net_flow'],
                    'transaction_count' => $totals['transaction_count'],
                ];
            }

            $payload = [
                'period'             => $period,
                'corporation_id'     => $corporationId,
                'characters'         => $charactersMeta,
                'aggregate' => [
                    'income_total'             => $incomeTotal,
                    'expense_total'            => $expenseTotal,
                    'net_flow'                 => $currentNet,
                    'transaction_count'        => $txnCount,
                    'top_income_sources'       => $topIncomeSources,
                    'top_expense_sources'      => $topExpenseSources,
                    'top_income_transactions'  => $topIncomeTransactions,
                    'top_expense_transactions' => $topExpenseTransactions,
                ],
                'by_character'      => empty($byCharacter) ? new \stdClass() : $byCharacter,
                'sparkline_balance' => $sparkline,
                'prior_period' => [
                    'income_total'  => $priorIncome,
                    'expense_total' => $priorExpense,
                    'net_flow'      => $priorNet,
                ],
                'trend' => $trend,
            ];

            // by_character keys are integers; the JSON encoder will see
            // an associative shape and emit an object, but stash an
            // empty-array sentinel as the empty value via new \stdClass()
            // (already handled above). Cache::put serialises through PHP
            // serialize() so stdClass round-trips correctly.
            Cache::put($cacheKey, $payload, $ttl);

            return response()->json($payload);
        } catch (\Exception $e) {
            Log::error('personalWalletStats failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'period'             => Carbon::now()->format('Y-m'),
                'corporation_id'     => null,
                'characters'         => [],
                'aggregate' => [
                    'income_total'             => 0.0,
                    'expense_total'            => 0.0,
                    'net_flow'                 => 0.0,
                    'transaction_count'        => 0,
                    'top_income_sources'       => [],
                    'top_expense_sources'      => [],
                    'top_income_transactions'  => [],
                    'top_expense_transactions' => [],
                ],
                'by_character'      => new \stdClass(),
                'sparkline_balance' => [],
                'prior_period'      => [
                    'income_total'  => 0.0,
                    'expense_total' => 0.0,
                    'net_flow'      => 0.0,
                ],
                'trend' => [
                    'income_pct'  => null,
                    'expense_pct' => null,
                    'net_pct'     => null,
                ],
            ], 500);
        }
    }

    /**
     * Human-readable label for a SeAT `ref_type` slug. CCP's ref_type strings
     * are stable snake_case names like `bounty_prizes` or `market_transaction`
     * so we title-case + space them. Special-cased a handful where the
     * naive transform doesn't read right.
     */
    private function refTypeLabel(string $refType): string
    {
        $special = [
            'bounty_prizes'                  => 'Bounty Prizes',
            'agent_mission_reward'           => 'Mission Reward',
            'agent_mission_time_bonus_reward' => 'Mission Time Bonus',
            'agent_mission_collateral_paid'  => 'Mission Collateral Paid',
            'agent_mission_collateral_refunded' => 'Mission Collateral Refunded',
            'corporation_account_withdrawal' => 'Corp Withdrawal',
            'player_donation'                => 'Player Donation',
            'player_trading'                 => 'Player Trade',
            'market_transaction'             => 'Market Transaction',
            'market_escrow'                  => 'Market Escrow',
            'broker_reimbursement'           => 'Broker Reimbursement',
            'transaction_tax'                => 'Sales Tax',
            'brokers_fee'                    => 'Broker Fee',
            'contract_price'                 => 'Contract Price',
            'contract_price_payment_corp'    => 'Contract Price (Corp)',
            'contract_reward'                => 'Contract Reward',
            'contract_collateral'            => 'Contract Collateral',
            'contract_deposit'               => 'Contract Deposit',
            'contract_deposit_refund'        => 'Contract Deposit Refund',
            'contract_brokers_fee'           => 'Contract Broker Fee',
            'office_rental_fee'              => 'Office Rental Fee',
            'jump_clone_installation_fee'    => 'Jump Clone Install Fee',
            'jump_clone_activation_fee'      => 'Jump Clone Activation Fee',
            'industry_job_tax'               => 'Industry Tax',
            'manufacturing'                  => 'Manufacturing',
            'researching_time_productivity'  => 'Research (Time)',
            'researching_material_productivity' => 'Research (Material)',
            'copying'                        => 'Copying',
            'invention'                      => 'Invention',
            'reaction'                       => 'Reaction',
            'reprocessing_tax'               => 'Reprocessing Tax',
            'docking_fee'                    => 'Docking Fee',
            'project_discovery_reward'       => 'Project Discovery',
            'ess_escrow_transfer'            => 'ESS Escrow',
            'planetary_construction'         => 'Planetary Construction',
            'planetary_export_tax'           => 'Planetary Export Tax',
            'planetary_import_tax'           => 'Planetary Import Tax',
        ];
        if (isset($special[$refType])) {
            return $special[$refType];
        }
        if ($refType === '') {
            return 'Unknown';
        }
        return ucwords(str_replace('_', ' ', $refType));
    }

    /**
     * Every player character the authenticated user owns, regardless of
     * which corporation those characters are currently in. Resolves via
     * `refresh_tokens.user_id` -> `character_id` with no affiliation
     * join, so a viewer's main that has since transferred to a different
     * corp still counts toward their lifetime contribution to the corp
     * being inspected (the corp scoping happens at the contribution-cache
     * query, not here).
     *
     * This is the canonical "all of this user's characters" resolver for
     * the member-facing surface. Both the contribution cache (corp-scoped
     * via `WHERE corporation_id = $thisCorp AND character_id IN (...)`)
     * and the personal-wallet aggregator (no corp filter, journal is
     * inherently per-character) call it.
     */
    private function viewerOwnedCharacterIds(): array
    {
        $userId = (int) auth()->id();
        if ($userId <= 0) {
            return [];
        }

        return DB::table('refresh_tokens')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->pluck('character_id')
            ->map(fn ($id) => (int) $id)
            // Defensive NPC guard - refresh_tokens only ever holds player
            // characters by definition, but we keep the floor in case of
            // a future migration that loosens that invariant.
            ->filter(fn ($id) => $id >= 90000000)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The set of main_character_ids associated with the viewer's
     * character set. Used to flag the viewer's own row on the
     * leaderboard regardless of whether they appear as a main or an
     * alt-rolled-up entry. When a character has no main mapping (no
     * users row, no refresh_token), the character itself is its own
     * main and is included here as such.
     */
    private function mainsForViewerCharacters(array $characterIds): array
    {
        if (empty($characterIds)) {
            return [];
        }

        $userId = (int) auth()->id();
        if ($userId <= 0) {
            return $characterIds;
        }

        $main = (int) (DB::table('users')->where('id', $userId)->value('main_character_id') ?? 0);

        // Every character the user owns can be its own main on the
        // leaderboard when SeAT's main resolution chain misses (no
        // main set). Adding both lets us match either rendering shape.
        $set = $characterIds;
        if ($main > 0) {
            $set[] = $main;
        }
        return array_values(array_unique(array_map('intval', $set)));
    }

    /**
     * Calculate the viewer's rank in this period by main-grouped total.
     * Matches the director-leaderboard grouping semantics so the
     * member-facing rank and the director's rank stay reconcilable.
     */
    private function computeViewerRank(int $corporationId, string $period, array $viewerCharacterIds): array
    {
        $shape = ['rank' => null, 'rank_total' => 0, 'percentile' => null];
        try {
            $service = app(\CorpWalletManager\Services\ContributionService::class);
            $board   = $service->getTopContributors($corporationId, $period, 1000);
            $rows    = $board['contributors'] ?? [];
            if (empty($rows)) {
                return $shape;
            }

            $viewerMainSet = $this->mainsForViewerCharacters($viewerCharacterIds);
            $rank = 0;
            $viewerRank = null;
            foreach ($rows as $row) {
                $rank++;
                $charId = (int) ($row['character_id'] ?? $row['main_character_id'] ?? 0);
                if ($charId > 0 && in_array($charId, $viewerMainSet, true)) {
                    $viewerRank = $rank;
                    break;
                }
            }
            $total = count($rows);
            $percentile = null;
            if ($viewerRank !== null && $total > 0) {
                // Rank 1 = top of corp = 100; rank=total = 0.
                $percentile = max(0.0, min(100.0, (($total - $viewerRank) / max(1, $total - 1)) * 100.0));
            }
            return [
                'rank'       => $viewerRank,
                'rank_total' => $total,
                'percentile' => $percentile,
            ];
        } catch (\Throwable $e) {
            Log::warning('computeViewerRank failed', ['error' => $e->getMessage()]);
            return $shape;
        }
    }

    /**
     * SQL-only rank computation for personalContribution. Replaces the
     * old computeViewerRank() which built the entire leaderboard (with
     * EntityNameResolver fallback to ESI + per-row MM tax lookups for up
     * to 1000 entries) just to read the viewer's row index off the
     * sorted array - all of that name and tax work was throwaway for
     * this caller.
     *
     * The grouping convention matches the director leaderboard so the
     * member-facing rank and the director-facing rank stay reconcilable:
     * rows are pulled from the contribution cache for the period, the
     * income-only sum is computed inline, NPC/corp-self/affiliation
     * guards apply, then per-character totals are rolled up into
     * main-character buckets via refresh_tokens -> users.main_character_id
     * (characters without a main resolve to themselves).
     *
     * Returns ['rank' => N|null, 'rank_total' => N, 'percentile' => N|null].
     *
     * EXPLAIN result: ref scan on
     * `corpwalletmanager_character_contributions` via
     * `cwm_char_contrib_corp_period` (corporation_id, period) composite
     * index; estimated rows = corp_active_members_in_period (typically
     * a few hundred even for big corps). The optional whereExists
     * affiliation join is index-backed on
     * (character_id, corporation_id). Refresh_tokens + users lookups
     * use their primary keys.
     */
    private function computeViewerRankFast(int $corporationId, string $period, array $viewerCharacterIds): array
    {
        $shape = ['rank' => null, 'rank_total' => 0, 'percentile' => null];
        try {
            if (empty($viewerCharacterIds)) {
                return $shape;
            }

            $incomeExpr = '(COALESCE(ratting_amount,0) ' .
                ' + COALESCE(mission_amount,0) ' .
                ' + COALESCE(industry_amount,0) ' .
                ' + COALESCE(tax_payment_amount,0) ' .
                ' + COALESCE(donation_voluntary_amount,0))';

            $hasAffiliations = Schema::hasTable('character_affiliations');
            $hasAllianceInfos = Schema::hasTable('alliance_infos');

            // Pull per-character income totals for the period, applying
            // the same defensive guards as the leaderboard. This is the
            // candidate set we group into mains. Indexed scan via
            // (corporation_id, period).
            $rows = DB::table('corpwalletmanager_character_contributions')
                ->where('corporation_id', $corporationId)
                ->where('period', $period)
                ->where('character_id', '>=', 90000000)
                ->whereColumn('character_id', '!=', 'corporation_id')
                ->whereNotIn('character_id', function ($q) {
                    $q->select('corporation_id')->from('corporation_infos');
                })
                ->when($hasAllianceInfos, function ($outer) {
                    $outer->whereNotIn('character_id', function ($q) {
                        $q->select('alliance_id')->from('alliance_infos');
                    });
                })
                ->when($hasAffiliations, function ($outer) use ($corporationId) {
                    $outer->whereExists(function ($q) use ($corporationId) {
                        $q->select(DB::raw(1))
                            ->from('character_affiliations')
                            ->whereColumn('character_affiliations.character_id', 'corpwalletmanager_character_contributions.character_id')
                            ->where('character_affiliations.corporation_id', $corporationId);
                    });
                })
                ->selectRaw('character_id, ' . $incomeExpr . ' AS income_total')
                // WHERE not HAVING — MariaDB strict mode rejects HAVING on
                // non-grouping columns when the query has no GROUP BY.
                ->whereRaw($incomeExpr . ' > 0')
                ->get();

            if ($rows->isEmpty()) {
                return $shape;
            }

            $charIds = $rows->pluck('character_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

            // Resolve each char -> main via refresh_tokens (user) ->
            // users.main_character_id. Characters without a main resolve
            // to themselves (their own main).
            $charToUser = DB::table('refresh_tokens')
                ->whereIn('character_id', $charIds)
                ->whereNull('deleted_at')
                ->pluck('user_id', 'character_id');
            $userToMain = [];
            if ($charToUser->isNotEmpty()) {
                $userIds = $charToUser->values()->unique()->all();
                $userToMain = DB::table('users')
                    ->whereIn('id', $userIds)
                    ->whereNotNull('main_character_id')
                    ->pluck('main_character_id', 'id')
                    ->toArray();
            }
            $mainMap = [];
            foreach ($charToUser as $cid => $uid) {
                if (isset($userToMain[$uid])) {
                    $mainMap[(int) $cid] = (int) $userToMain[$uid];
                }
            }

            // Roll rows into main-grouped totals.
            $mainTotals = [];
            foreach ($rows as $r) {
                $charId = (int) $r->character_id;
                $mainId = $mainMap[$charId] ?? $charId;
                if (! isset($mainTotals[$mainId])) {
                    $mainTotals[$mainId] = 0.0;
                }
                $mainTotals[$mainId] += (float) $r->income_total;
            }

            // The viewer's main set - matches the leaderboard's notion
            // of "the viewer's row" even when an alt is on the board
            // because their main has no contribution this period.
            $viewerMainSet = $this->mainsForViewerCharacters($viewerCharacterIds);
            $viewerTotal = 0.0;
            foreach ($viewerMainSet as $vmId) {
                if (isset($mainTotals[(int) $vmId])) {
                    $viewerTotal = max($viewerTotal, $mainTotals[(int) $vmId]);
                }
            }

            $total = count($mainTotals);
            if ($total === 0 || $viewerTotal <= 0.0) {
                return ['rank' => null, 'rank_total' => $total, 'percentile' => null];
            }

            // Rank = 1 + (# of mains with a strictly higher total).
            $higher = 0;
            foreach ($mainTotals as $mid => $t) {
                if ($t > $viewerTotal) {
                    $higher++;
                }
            }
            $viewerRank = $higher + 1;

            $percentile = null;
            if ($total > 0) {
                $percentile = max(0.0, min(100.0, (($total - $viewerRank) / max(1, $total - 1)) * 100.0));
            }

            return [
                'rank'       => $viewerRank,
                'rank_total' => $total,
                'percentile' => $percentile,
            ];
        } catch (\Throwable $e) {
            Log::warning('computeViewerRankFast failed', ['error' => $e->getMessage()]);
            return $shape;
        }
    }

    /**
     * Calculate the prior calendar period (Y-m string) from a Y-m input.
     * Used by personalContribution for the trend vs last month figure.
     */
    private function priorPeriod(string $period): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $period, $m)) {
            return Carbon::now()->subMonth()->format('Y-m');
        }
        $year = (int) $m[1];
        $month = (int) $m[2];
        $month--;
        if ($month < 1) {
            $month = 12;
            $year--;
        }
        return sprintf('%04d-%02d', $year, $month);
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
            $query = JournalFilters::excludeInternalTransfers($query, (int) $corporationId);
        } else {
            $query = JournalFilters::excludeInternalTransfers($query);
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
            'alliance_tax' => 'Alliance Tax',
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
