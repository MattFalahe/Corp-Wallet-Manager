<?php

namespace CorpWalletManager\Services\Models;

use Illuminate\Support\Collection;

/**
 * Seasonal naive forecast: the predicted daily_change for day D is the
 * same day-of-week's average over the last ~12 weeks. A stronger
 * baseline than EWMA for corps with pronounced weekly patterns (e.g.
 * Saturday-peak PvP corps, weekday-heavy ratting corps).
 *
 * Falls back to an overall mean if fewer than 3 observations exist for
 * the requested day-of-week.
 */
class SeasonalNaiveModel implements PredictionModel
{
    private const WINDOW_DAYS = 84; // 12 weeks
    private const MIN_SAMPLES_PER_DOW = 3;

    /** @var array<int, float> keyed by ISO day-of-week (1=Mon..7=Sun) */
    private array $dowAverages = [];
    private float $fallback = 0.0;

    public function name(): string
    {
        return 'seasonal_naive';
    }

    public function fit(Collection $history): void
    {
        $recent = $history->slice(-self::WINDOW_DAYS);

        $this->fallback = $recent->avg('daily_change') ?? 0.0;
        $this->dowAverages = [];

        $grouped = $recent->groupBy(function ($row) {
            return (int) (new \DateTimeImmutable($row->day))->format('N'); // 1..7
        });

        foreach ($grouped as $dow => $bucket) {
            if ($bucket->count() < self::MIN_SAMPLES_PER_DOW) {
                continue;
            }
            $this->dowAverages[(int) $dow] = (float) $bucket->avg('daily_change');
        }
    }

    public function predictDailyChange(\DateTimeInterface $targetDate): float
    {
        $dow = (int) $targetDate->format('N');
        return $this->dowAverages[$dow] ?? $this->fallback;
    }
}
