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
    public function latest()
    {
        // Current month and today
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();

        // Latest recorded balance (sum of all divisions)
        $latest_balance = MonthlyBalance::where('month', $monthStart->format('Y-m'))
            ->sum('balance');

        // Predicted balance for the current month
        $predicted = Prediction::whereDate('date', $today)
            ->sum('predicted_balance');

        return response()->json([
            'balance'   => $latest_balance ?? 0,
            'predicted' => $predicted ?? 0,
        ]);
    }

    /**
     * Return monthly comparison (last 6 months).
     */
    public function monthlyComparison()
    {
        $six_months_ago = Carbon::today()->subMonths(6)->startOfMonth();

        $balances = MonthlyBalance::where('month', '>=', $six_months_ago->format('Y-m'))
            ->orderBy('month')
            ->get()
            ->groupBy('month')
            ->map(function ($rows) {
                return $rows->sum('balance');
            });

        $labels = $balances->keys()->toArray();
        $data   = $balances->values()->toArray();

        return response()->json([
            'labels' => $labels,
            'data'   => $data,
        ]);
    }
}
