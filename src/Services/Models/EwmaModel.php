<?php

namespace CorpWalletManager\Services\Models;

use Illuminate\Support\Collection;

/**
 * Exponentially weighted moving average.
 * y_hat_t = alpha * y_{t-1} + (1 - alpha) * y_hat_{t-1}
 *
 * Alpha=0.3 gives recent days roughly 3x the weight of week-old days
 * and negligible weight to anything older than ~20 days. Good fit for
 * corps whose cashflow pattern shifts gradually.
 */
class EwmaModel implements PredictionModel
{
    private const ALPHA = 0.3;
    private const MIN_HISTORY = 14;

    private ?float $level = null;

    public function name(): string
    {
        return 'ewma';
    }

    public function fit(Collection $history): void
    {
        if ($history->count() < self::MIN_HISTORY) {
            $this->level = $history->avg('daily_change') ?? 0.0;
            return;
        }

        // Initialize with the first observation; walk forward, updating level.
        $level = (float) $history->first()->daily_change;
        foreach ($history->slice(1) as $row) {
            $level = self::ALPHA * (float) $row->daily_change + (1.0 - self::ALPHA) * $level;
        }
        $this->level = $level;
    }

    public function predictDailyChange(\DateTimeInterface $targetDate): float
    {
        // EWMA forecast is flat — tomorrow and 30 days out are the same level.
        // Horizon-aware models layer seasonality / trend on top of this.
        return $this->level ?? 0.0;
    }
}
