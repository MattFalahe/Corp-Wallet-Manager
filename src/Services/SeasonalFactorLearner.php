<?php

namespace CorpWalletManager\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use CorpWalletManager\Support\JournalFilters;

/**
 * Derives per-corporation seasonal multipliers (day-of-week,
 * week-of-month, month-of-year) from actual daily wallet history.
 *
 * Replaces the hardcoded constants previously used in PredictionService.
 * Factors are cached 24h because they barely move day-to-day. If a corp
 * has insufficient history for a given dimension, that dimension is
 * returned as neutral 1.0s so the prediction pipeline degrades
 * gracefully instead of amplifying noise from a small sample.
 */
class SeasonalFactorLearner
{
    private const CACHE_TTL_SECONDS = 86400; // 24h
    private const LOOKBACK_DAYS = 365;
    private const MIN_DAYS_FOR_SHORT_CYCLES = 240; // ~8 months
    private const MIN_MONTHS_FOR_YEARLY_CYCLE = 12;
    private const MIN_SAMPLES_PER_BUCKET = 3;
    private const FACTOR_MIN = 0.5;
    private const FACTOR_MAX = 1.5;

    /**
     * Treat |mean| below this (in ISK) as "no usable baseline" — ratio
     * factors explode when the denominator is near zero. Any active corp
     * will have daily movement >> 1M, so this is a very conservative guard.
     */
    private const MEAN_USABILITY_THRESHOLD = 1_000_000.0;

    public function getFactors(int $corporationId): array
    {
        try {
            return Cache::remember(
                $this->cacheKey($corporationId),
                self::CACHE_TTL_SECONDS,
                fn () => $this->computeFactors($corporationId)
            );
        } catch (\Throwable $e) {
            Log::warning('SeasonalFactorLearner: falling back to neutral factors', [
                'corporation_id' => $corporationId,
                'error' => $e->getMessage(),
            ]);
            return $this->allNeutral();
        }
    }

    /**
     * Force-recompute the next time getFactors() is called. Intended for
     * admin console / debugging; normal operation relies on the 24h TTL.
     */
    public function invalidate(int $corporationId): void
    {
        Cache::forget($this->cacheKey($corporationId));
    }

    private function computeFactors(int $corporationId): array
    {
        $since = Carbon::now()->subDays(self::LOOKBACK_DAYS)->startOfDay();

        // Strip internal transfers — their +X/-X halves can land on
        // different DATE() buckets, distorting the per-day SUM that drives
        // the day-of-week / week-of-month / month-of-year factors and the
        // overall mean used as the denominator in bucketFactors().
        $dailyQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->where('date', '>=', $since);
        $dailyQuery = JournalFilters::excludeInternalTransfers($dailyQuery, $corporationId);
        $daily = $dailyQuery
            ->selectRaw('DATE(date) as d, SUM(amount) as daily_change')
            ->groupBy('d')
            ->get()
            ->map(fn ($row) => (object) [
                'date'   => Carbon::parse($row->d),
                'change' => (float) $row->daily_change,
            ]);

        if ($daily->isEmpty()) {
            return $this->allNeutral();
        }

        $totalDays = $daily->count();
        $distinctMonths = $daily->pluck('date')
            ->map(fn ($d) => $d->format('Y-m'))
            ->unique()
            ->count();
        $overallMean = $daily->avg('change');

        return [
            'day_of_week'   => $this->dayOfWeekFactors($daily, $overallMean, $totalDays),
            'week_of_month' => $this->weekOfMonthFactors($daily, $overallMean, $totalDays),
            'month_of_year' => $this->monthOfYearFactors($daily, $overallMean, $distinctMonths),
        ];
    }

    private function dayOfWeekFactors(Collection $daily, float $overallMean, int $totalDays): array
    {
        if ($totalDays < self::MIN_DAYS_FOR_SHORT_CYCLES || !$this->meanIsUsable($overallMean)) {
            return $this->neutral(range(1, 7));
        }
        $grouped = $daily->groupBy(fn ($row) => $row->date->dayOfWeekIso); // 1=Mon..7=Sun
        return $this->bucketFactors($grouped, $overallMean, range(1, 7));
    }

    private function weekOfMonthFactors(Collection $daily, float $overallMean, int $totalDays): array
    {
        if ($totalDays < self::MIN_DAYS_FOR_SHORT_CYCLES || !$this->meanIsUsable($overallMean)) {
            return $this->neutral(range(1, 4));
        }
        // Match PredictionService's prior 1..4 bucket layout; days 29-31 collapse into week 4.
        $grouped = $daily->groupBy(fn ($row) => (int) min((int) ceil($row->date->day / 7), 4));
        return $this->bucketFactors($grouped, $overallMean, range(1, 4));
    }

    private function monthOfYearFactors(Collection $daily, float $overallMean, int $distinctMonths): array
    {
        if ($distinctMonths < self::MIN_MONTHS_FOR_YEARLY_CYCLE || !$this->meanIsUsable($overallMean)) {
            return $this->neutral(range(1, 12));
        }
        $grouped = $daily->groupBy(fn ($row) => (int) $row->date->month);
        return $this->bucketFactors($grouped, $overallMean, range(1, 12));
    }

    private function bucketFactors(Collection $grouped, float $overallMean, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $bucket = $grouped->get($k);
            if ($bucket === null || $bucket->count() < self::MIN_SAMPLES_PER_BUCKET) {
                $out[$k] = 1.0;
                continue;
            }
            $factor = $bucket->avg('change') / $overallMean;
            $out[$k] = max(self::FACTOR_MIN, min(self::FACTOR_MAX, $factor));
        }
        return $out;
    }

    private function meanIsUsable(float $mean): bool
    {
        return abs($mean) >= self::MEAN_USABILITY_THRESHOLD;
    }

    private function neutral(array $keys): array
    {
        return array_fill_keys($keys, 1.0);
    }

    private function allNeutral(): array
    {
        return [
            'day_of_week'   => $this->neutral(range(1, 7)),
            'week_of_month' => $this->neutral(range(1, 4)),
            'month_of_year' => $this->neutral(range(1, 12)),
        ];
    }

    private function cacheKey(int $corporationId): string
    {
        return "cwm:seasonal_factors:{$corporationId}";
    }
}
