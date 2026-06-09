<?php

namespace CorpWalletManager\Services\Models;

use Illuminate\Support\Collection;

/**
 * Minimal prediction-model contract for ensemble selection.
 *
 * Models are scored on a holdout window by ModelSelector and the winner
 * per corporation drives ComputeDailyPrediction. The interface is
 * deliberately small: given a daily-change history, produce a predicted
 * daily_change for a future day. Balance-level math, smoothing, and
 * metadata attachment are the caller's responsibility.
 */
interface PredictionModel
{
    /**
     * Short identifier stored in predictions.prediction_method so we can
     * tell later which model produced a given prediction.
     */
    public function name(): string;

    /**
     * Fit any internal parameters from the daily-change history. Each
     * history row is an object with at minimum:
     *   - day: 'Y-m-d' string
     *   - daily_change: float
     * History is expected to be ordered oldest-first.
     */
    public function fit(Collection $history): void;

    /**
     * Predict the daily_change for a specific future date. Requires fit()
     * to have been called. Returns 0.0 for dates the model can't predict.
     */
    public function predictDailyChange(\DateTimeInterface $targetDate): float;
}
