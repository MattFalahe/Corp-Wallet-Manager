<?php
namespace Seat\CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Seat\CorpWalletManager\Models\MonthlyBalance;
use Seat\CorpWalletManager\Models\Prediction;

class BackfillWalletData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        // Group journal entries by month
        $monthly = DB::table('corporation_wallet_journals')
            ->selectRaw('DATE_FORMAT(date, "%Y-%m") as month, SUM(amount) as balance')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        foreach ($monthly as $row) {
            MonthlyBalance::updateOrCreate(
                ['month' => $row->month],
                ['balance' => $row->balance]
            );
        }

        // Create simple predictions based on monthly averages
        $avg = $monthly->avg('balance');
        if ($avg !== null) {
            $next_month = now()->addMonth()->format('Y-m');
            Prediction::updateOrCreate(
                ['date' => $next_month],
                ['predicted_balance' => $avg]
            );
        }
    }
}
