<?php

namespace CorpWalletManager\Services\Models;

use Illuminate\Support\Collection;

/**
 * Ordinary least-squares fit on the last N days of daily_change.
 * Good baseline for corps with a clear upward/downward trajectory;
 * ignores all seasonality.
 */
class LinearTrendModel implements PredictionModel
{
    private const WINDOW_DAYS = 60;

    private ?float $slope = null;
    private ?float $intercept = null;
    private ?\DateTimeInterface $anchor = null;

    public function name(): string
    {
        return 'linear_trend';
    }

    public function fit(Collection $history): void
    {
        $recent = $history->slice(-self::WINDOW_DAYS)->values();
        $n = $recent->count();

        if ($n < 7) {
            $this->slope = 0.0;
            $this->intercept = $recent->avg('daily_change') ?? 0.0;
            $this->anchor = $n > 0 ? new \DateTimeImmutable($recent->first()->day) : null;
            return;
        }

        $this->anchor = new \DateTimeImmutable($recent->first()->day);

        $sumX = 0.0; $sumY = 0.0; $sumXY = 0.0; $sumX2 = 0.0;
        foreach ($recent as $i => $row) {
            $x = (float) $i;
            $y = (float) $row->daily_change;
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $denom = ($n * $sumX2) - ($sumX * $sumX);
        if (abs($denom) < 1e-9) {
            $this->slope = 0.0;
            $this->intercept = $sumY / $n;
            return;
        }

        $this->slope = (($n * $sumXY) - ($sumX * $sumY)) / $denom;
        $this->intercept = ($sumY - ($this->slope * $sumX)) / $n;
    }

    public function predictDailyChange(\DateTimeInterface $targetDate): float
    {
        if ($this->slope === null || $this->intercept === null || $this->anchor === null) {
            return 0.0;
        }

        $daysFromAnchor = (int) (new \DateTimeImmutable($this->anchor->format('Y-m-d')))
            ->diff(new \DateTimeImmutable($targetDate->format('Y-m-d')))
            ->format('%r%a');

        return $this->intercept + $this->slope * $daysFromAnchor;
    }
}
