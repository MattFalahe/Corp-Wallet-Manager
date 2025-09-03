<?php
namespace Seat\CorpWalletManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Seat\CorpWalletManager\Models\Settings;
use Seat\CorpWalletManager\Models\Prediction;
use Seat\CorpWalletManager\Models\MonthlyBalance;

class WalletController extends Controller
{
    // ========== EXISTING METHODS ==========
    
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

    /**
     * Return the latest balance + prediction for this month.
     */
    public function latest(Request $request)
    {
        try {
            $corporationId = $request->get('corporation_id');
            if ($corporationId && !is_numeric($corporationId)) {
                return response()->json(['error' => 'Invalid corporation ID'], 400);
            }

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
            $corporationId = $request->get('corporation_id');
            if ($corporationId && !is_numeric($corporationId)) {
                return response()->json(['error' => 'Invalid corporation ID'], 400);
            }

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
            $corporationId = $request->get('corporation_id');
            if ($corporationId && !is_numeric($corporationId)) {
                return response()->json(['error' => 'Invalid corporation ID'], 400);
            }

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
            $corporationId = $request->get('corporation_id');
            if (!$corporationId || !is_numeric($corporationId)) {
                return response()->json(['error' => 'Corporation ID required'], 400);
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

            $labels = $divisions->pluck('division_id')->map(function ($divId) {
                return "Division " . $divId;
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
            $corporationId = $request->get('corporation_id');
            if ($corporationId && !is_numeric($corporationId)) {
                return response()->json(['error' => 'Invalid corporation ID'], 400);
            }

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
    
    // ========== NEW METHODS ==========
    
    /**
     * Get actual wallet balance from corporation_wallet_balances table
     */
    public function walletActual(Request $request)
    {
        try {
            $corporationId = $request->get('corporation_id');
            
            // If no corporation specified, get the first one we have
            if (!$corporationId) {
                $corporationId = \DB::table('corporation_wallet_balances')
                    ->value('corporation_id');
            }
            
            // Use corporation_wallet_balances table which has the current balance
            $query = \DB::table('corporation_wallet_balances')
                ->selectRaw('SUM(balance) as total_balance')
                ->where('corporation_id', $corporationId);
            
            $result = $query->first();
            $balance = $result ? (float)$result->total_balance : 0;
            
            // Also get division count for info
            $divisionCount = \DB::table('corporation_wallet_balances')
                ->where('corporation_id', $corporationId)
                ->count();
            
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
            $corporationId = $request->get('corporation_id');
            $today = Carbon::today();
            
            $query = \DB::table('corporation_wallet_journals')
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
            $corporationId = $request->get('corporation_id');
            if (!$corporationId || !is_numeric($corporationId)) {
                // Get first available corporation
                $corporationId = \DB::table('corporation_wallet_balances')
                    ->whereNotNull('corporation_id')
                    ->value('corporation_id');
            }
            
            if (!$corporationId) {
                return response()->json(['divisions' => []]);
            }
            
            $currentMonth = Carbon::now()->format('Y-m');
            
            // Get current division balances from corporation_wallet_balances
            $walletBalances = \DB::table('corporation_wallet_balances')
                ->where('corporation_id', $corporationId)
                ->get()
                ->keyBy('division');
            
            // Get monthly changes from our processed table
            $monthlyChanges = \Seat\CorpWalletManager\Models\DivisionBalance::where('corporation_id', $corporationId)
                ->where('month', $currentMonth)
                ->get()
                ->keyBy('division_id');
            
            $divisions = [];
            
            // Build division data
            foreach ($walletBalances as $walletBalance) {
                $monthChange = $monthlyChanges->get($walletBalance->division);
                
                $divisions[] = [
                    'id' => $walletBalance->division,
                    'name' => $this->getDivisionName($walletBalance->division),
                    'balance' => (float)$walletBalance->balance,
                    'change' => $monthChange ? (float)$monthChange->balance : 0,
                ];
            }
            
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
            $corporationId = $request->get('corporation_id');
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
            $corporationId = $request->get('corporation_id');
            $months = min(max((int)$request->get('months', 6), 1), 24);
            
            $startDate = Carbon::now()->subMonths($months)->startOfMonth();
            
            $query = \DB::table('corporation_wallet_journals')
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
            $corporationId = $request->get('corporation_id');
            $type = $request->get('type', 'expense'); // 'income' or 'expense'
            $months = (int)$request->get('months', 1); // Default to current month
            
            $startDate = Carbon::now()->subMonths($months)->startOfMonth();
            
            // Build the query
            $query = \DB::table('corporation_wallet_journals')
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

    // ========== HELPER METHODS ==========
    
    /**
     * Helper function to get division names
     */
    private function getDivisionName($divisionId)
    {
        $names = [
            1 => 'Master Wallet',
            2 => 'Corp Production',
            3 => 'Corp Markets',
            4 => 'Taxes and Bills',
            5 => 'Corp SRP',
            6 => 'Corp Buyback',
            7 => 'Reserves',
        ];
        
        return $names[$divisionId] ?? "Division $divisionId";
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
