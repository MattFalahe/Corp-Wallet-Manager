<?php

namespace CorpWalletManager\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use CorpWalletManager\Services\Models\EwmaModel;
use CorpWalletManager\Services\Models\LinearTrendModel;
use CorpWalletManager\Services\Models\PredictionModel;
use CorpWalletManager\Services\Models\SeasonalNaiveModel;
use CorpWalletManager\Support\JournalFilters;

/**
 * Picks the best-fitting simple model per corporation by running each
 * candidate over a rolling holdout window.
 *
 * This does NOT replace the Advanced Weighted model (PredictionService);
 * it runs alongside it. ComputeDailyPrediction consults the selector's
 * winner to decide whether the weighted model's output should be blended
 * or replaced for corps whose data fits a simpler regime better.
 *
 * Winner is cached 24h — the selection barely moves day-to-day and we
 * don't want the holdout evaluation on every prediction run.
 */
class ModelSelector
{
    private const HOLDOUT_DAYS = 14;
    private const TRAIN_DAYS = 120;
    private const CACHE_TTL_SECONDS = 86400;

    /**
     * Returns ['name' => <model name>, 'mape' => <holdout MAPE>, 'scores' => [name => mape, ...]]
     * or null if there's not enough history to evaluate.
     */
    public function selectBestModel(int $corporationId): ?array
    {
        try {
            return Cache::remember(
                "cwm:model_selection:{$corporationId}",
                self::CACHE_TTL_SECONDS,
                fn () => $this->evaluate($corporationId)
            );
        } catch (\Throwable $e) {
            Log::warning('ModelSelector: evaluation failed', [
                'corporation_id' => $corporationId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function evaluate(int $corporationId): ?array
    {
        $history = $this->loadDailyHistory(
            $corporationId,
            Carbon::now()->subDays(self::TRAIN_DAYS + self::HOLDOUT_DAYS)->startOfDay(),
            Carbon::today()
        );

        $needed = self::TRAIN_DAYS + self::HOLDOUT_DAYS;
        if ($history->count() < $needed / 2) {
            return null;
        }

        // Last HOLDOUT_DAYS form the evaluation window; everything before trains.
        $cutoffIndex = max(0, $history->count() - self::HOLDOUT_DAYS);
        $train = $history->slice(0, $cutoffIndex)->values();
        $holdout = $history->slice($cutoffIndex)->values();

        if ($train->isEmpty() || $holdout->isEmpty()) {
            return null;
        }

        $candidates = $this->candidates();
        $scores = [];

        foreach ($candidates as $model) {
            $model->fit($train);
            $scores[$model->name()] = $this->mapeOnHoldout($model, $holdout);
        }

        // Filter out nulls (model couldn't score), pick minimum.
        $scored = array_filter($scores, fn ($v) => $v !== null);
        if (empty($scored)) {
            return null;
        }
        asort($scored);
        $winner = array_key_first($scored);

        return [
            'name' => $winner,
            'mape' => $scored[$winner],
            'scores' => $scores,
        ];
    }

    /** @return PredictionModel[] */
    private function candidates(): array
    {
        return [
            new EwmaModel(),
            new LinearTrendModel(),
            new SeasonalNaiveModel(),
        ];
    }

    private function mapeOnHoldout(PredictionModel $model, Collection $holdout): ?float
    {
        $sum = 0.0;
        $count = 0;
        foreach ($holdout as $row) {
            $actual = (float) $row->daily_change;
            if (abs($actual) < 1.0) {
                continue;
            }
            $predicted = $model->predictDailyChange(new \DateTimeImmutable($row->day));
            $sum += abs(($predicted - $actual) / $actual) * 100.0;
            $count++;
        }
        return $count > 0 ? round($sum / $count, 2) : null;
    }

    private function loadDailyHistory(int $corporationId, Carbon $from, Carbon $to): Collection
    {
        // Same reason as the other prediction-pipeline queries: internal
        // transfers shouldn't influence the holdout MAPE that decides
        // which model gets to replace advanced_weighted for this corp.
        $query = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->where('date', '>=', $from)
            ->where('date', '<', $to);
        $query = JournalFilters::excludeInternalTransfers($query, $corporationId);

        return $query
            ->selectRaw('DATE(date) as day, SUM(amount) as daily_change')
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }
}
