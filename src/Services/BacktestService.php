<?php

namespace CorpWalletManager\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use CorpWalletManager\Support\JournalFilters;

/**
 * Compares yesterday-and-before predictions against the actual wallet
 * balance on the same day, rolls up the error into MAPE and bias over
 * 7- and 30-day windows, and upserts those numbers into the shared
 * corpwalletmanager_prediction_metrics row for the corp.
 *
 * PredictionService reads mape_30d from that row to drive confidence
 * intervals off observed accuracy rather than the old linear 2%/day
 * decay heuristic.
 */
class BacktestService
{
    private const WINDOW_SHORT_DAYS = 7;
    private const WINDOW_LONG_DAYS = 30;

    /**
     * Compute and persist error metrics for one corporation.
     * Returns the rolled-up metrics (or null if insufficient data).
     */
    public function runForCorporation(int $corporationId): ?array
    {
        $cutoff = Carbon::now()->subDays(self::WINDOW_LONG_DAYS)->startOfDay();
        $today = Carbon::today();

        // Predictions whose target date is in [cutoff, today) — i.e. days
        // where we have both a past prediction and a known actual.
        $predictions = DB::table('corpwalletmanager_predictions')
            ->where('corporation_id', $corporationId)
            ->whereDate('date', '>=', $cutoff)
            ->whereDate('date', '<', $today)
            ->orderBy('date')
            ->get(['date', 'predicted_balance']);

        if ($predictions->isEmpty()) {
            return null;
        }

        $actualByDate = $this->actualBalancesByDate($corporationId, $cutoff, $today);

        $paired = $predictions
            ->map(function ($row) use ($actualByDate) {
                $dateKey = Carbon::parse($row->date)->toDateString();
                $actual = $actualByDate[$dateKey] ?? null;
                if ($actual === null) {
                    return null;
                }
                return [
                    'date' => $dateKey,
                    'predicted' => (float) $row->predicted_balance,
                    'actual' => $actual,
                ];
            })
            ->filter()
            ->values();

        if ($paired->isEmpty()) {
            return null;
        }

        $short = $this->rollup($paired->filter(fn ($p) => Carbon::parse($p['date'])->gte(Carbon::now()->subDays(self::WINDOW_SHORT_DAYS))));
        $long = $this->rollup($paired);

        $metrics = [
            'mape_7d' => $short['mape'],
            'mape_30d' => $long['mape'],
            'bias_7d' => $short['bias'],
            'bias_30d' => $long['bias'],
            'last_backtest_at' => now(),
        ];

        DB::table('corpwalletmanager_prediction_metrics')->updateOrInsert(
            ['corporation_id' => $corporationId],
            array_merge($metrics, ['updated_at' => now()])
        );

        Log::info('BacktestService: metrics stored', array_merge(
            ['corporation_id' => $corporationId, 'sample_size_30d' => $paired->count()],
            $metrics
        ));

        return $metrics;
    }

    /**
     * Daily wallet balance at end-of-day for each day in [from, to).
     * Computed as a cumulative sum of corporation_wallet_journals amounts.
     *
     * Returns a map keyed by 'Y-m-d' string.
     */
    private function actualBalancesByDate(int $corporationId, Carbon $from, Carbon $to): array
    {
        // Start with the opening balance (sum of all journal entries strictly
        // before the window), then walk forward day by day accumulating.
        // Internal transfers net to zero across the +X/-X pair, so excluding
        // them keeps the running balance correct AND stays consistent with
        // the predictions we're scoring against (which were trained on
        // internal-transfer-stripped journals).
        $openingBalanceQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->where('date', '<', $from);
        $openingBalanceQuery = JournalFilters::excludeInternalTransfers($openingBalanceQuery, $corporationId);
        $openingBalance = (float) $openingBalanceQuery->sum('amount');

        $dailyQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->where('date', '>=', $from)
            ->where('date', '<', $to);
        $dailyQuery = JournalFilters::excludeInternalTransfers($dailyQuery, $corporationId);
        $daily = $dailyQuery
            ->selectRaw('DATE(date) as d, SUM(amount) as delta')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $running = $openingBalance;
        $byDate = [];

        // Fill every date in the window so we have an entry even for days
        // with no journal activity (balance is unchanged from previous day).
        $cursor = $from->copy();
        $deltasByKey = $daily->keyBy(fn ($r) => Carbon::parse($r->d)->toDateString());

        while ($cursor->lt($to)) {
            $key = $cursor->toDateString();
            if ($deltasByKey->has($key)) {
                $running += (float) $deltasByKey->get($key)->delta;
            }
            $byDate[$key] = $running;
            $cursor->addDay();
        }

        return $byDate;
    }

    private function rollup(Collection $paired): array
    {
        if ($paired->isEmpty()) {
            return ['mape' => null, 'bias' => null];
        }

        $mapeSum = 0.0;
        $biasSum = 0.0;
        $mapeCount = 0;

        foreach ($paired as $p) {
            $error = $p['predicted'] - $p['actual'];
            // MAPE is undefined when actual is zero; skip those days.
            if (abs($p['actual']) >= 1.0) {
                $mapeSum += abs($error / $p['actual']) * 100.0;
                $mapeCount++;
            }
            $biasSum += $error;
        }

        return [
            'mape' => $mapeCount > 0 ? round($mapeSum / $mapeCount, 2) : null,
            'bias' => round($biasSum / $paired->count(), 4),
        ];
    }
}
