<?php
namespace Seat\CorpWalletManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Seat\CorpWalletManager\Models\Settings;
use Seat\CorpWalletManager\Models\Prediction;
use Seat\CorpWalletManager\Models\MonthlyBalance;

class WalletController extends Controller
{
    public function director()
    {
        return view('corpwalletmanager::director');
    }

    public function member()
    {
        return view('corpwalletmanager::member');
    }

    /**
     * Return the latest balance + prediction for this month.
     */
    public function latest(Request $request)
    {
        $corporationId = $request->get('corporation_id');
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();

        // Latest recorded balance 
        $balanceQuery = MonthlyBalance::where('month', $monthStart->format('Y-m'));
        
        if ($corporationId) {
            $balanceQuery->where('corporation_id', $corporationId);
        }
        
        $latest_balance = $balanceQuery->sum('balance');

        // Predicted balance for today
        $predictionQuery = Prediction::whereDate('date', $today);
        
        if ($corporationId) {
            $predictionQuery->where('corporation_id', $corporationId);
        }
        
        $predicted = $predictionQuery->sum('predicted_balance');

        return response()->json([
            'balance'   => $latest_balance ?? 0,
            'predicted' => $predicted ?? 0,
            'date' => $today->format('Y-m-d'),
            'month' => $monthStart->format('Y-m'),
        ]);
    }

    /**
     * Return monthly comparison (last 6 months).
     */
    public function monthlyComparison(Request $request)
    {
        $corporationId = $request->get('corporation_id');
        $monthsToShow = $request->get('months', 6);
        
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
            return Carbon::createFromFormat('Y-m', $month)->format('M Y');
        })->toArray();
        
        $data = $balances->values()->toArray();

        return response()->json([
            'labels' => $labels,
            'data'   => $data,
            'months_requested' => $monthsToShow,
            'corporation_id' => $corporationId,
        ]);
    }

    /**
     * Get prediction data for charts
     */
    public function predictions(Request $request)
    {
        $corporationId = $request->get('corporation_id');
        $days = $request->get('days', 30);
        
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
                return $rows->sum('predicted_balance');
            });

        $labels = $predictions->keys()->toArray();
        $data = $predictions->values()->toArray();

        return response()->json([
            'labels' => $labels,
            'data' => $data,
            'days_requested' => $days,
            'corporation_id' => $corporationId,
        ]);
    }

    /**
     * Get division breakdown data
     */
    public function divisionBreakdown(Request $request)
    {
        $corporationId = $request->get('corporation_id');
        $month = $request->get('month', Carbon::now()->format('Y-m'));
        
        if (!$corporationId) {
            return response()->json(['error' => 'Corporation ID required'], 400);
        }

        $divisions = \Seat\CorpWalletManager\Models\DivisionBalance::where('corporation_id', $corporationId)
            ->where('month', $month)
            ->orderBy('division_id')
            ->get();

        $labels = $divisions->pluck('division_id')->map(function ($divId) {
            return "Division " . $divId;
        })->toArray();
        
        $data = $divisions->pluck('balance')->toArray();

        return response()->json([
            'labels' => $labels,
            'data' => $data,
            'month' => $month,
            'corporation_id' => $corporationId,
        ]);
    }

    /**
     * Get summary statistics
     */
    public function summary(Request $request)
    {
        $corporationId = $request->get('corporation_id');
        $currentMonth = Carbon::now()->format('Y-m');
        $lastMonth = Carbon::now()->subMonth()->format('Y-m');

        // Current month balance
        $currentQuery = MonthlyBalance::where('month', $currentMonth);
        if ($corporationId) {
            $currentQuery->where('corporation_id', $corporationId);
        }
        $currentBalance = $currentQuery->sum('balance');

        // Last month balance
        $lastQuery = MonthlyBalance::where('month', $lastMonth);
        if ($corporationId) {
            $lastQuery->where('corporation_id', $corporationId);
        }
        $lastBalance = $lastQuery->sum('balance');

        // Calculate change
        $change = $currentBalance - $lastBalance;
        $changePercent = $lastBalance != 0 ? ($change / $lastBalance) * 100 : 0;

        // Next month prediction
        $nextMonth = Carbon::now()->addMonth()->startOfMonth();
        $predictionQuery = Prediction::whereDate('date', $nextMonth);
        if ($corporationId) {
            $predictionQuery->where('corporation_id', $corporationId);
        }
        $nextMonthPrediction = $predictionQuery->sum('predicted_balance');

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
    }
}
