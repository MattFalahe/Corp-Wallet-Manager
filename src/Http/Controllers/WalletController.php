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
        $endDate = $startDate
