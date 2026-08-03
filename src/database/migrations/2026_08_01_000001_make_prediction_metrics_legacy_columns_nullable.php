<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MakePredictionMetricsLegacyColumnsNullable extends Migration
{
    /**
     * corpwalletmanager_prediction_metrics holds one row per corp, seeded by
     * two independent jobs with disjoint column sets: ComputeDailyPrediction
     * fills the prediction-quality columns (prediction_date, data_points_used,
     * average_confidence, volatility_factor, trend_strength) and BacktestService
     * fills the accuracy columns (mape_*, bias_*, last_backtest_at).
     *
     * Whichever job runs first for a corp has to be able to insert the row with
     * only its own subset. The prediction-quality columns were created NOT NULL
     * with no default, so a backtest that ran before a prediction row existed
     * failed with "Field 'prediction_date' doesn't have a default value"
     * (SQLSTATE 1364), which aborted the whole backtest run. Relaxing them to
     * nullable lets either job seed the row; the other fills its columns on its
     * next pass. Existing rows keep their values.
     */
    public function up(): void
    {
        if (! Schema::hasTable('corpwalletmanager_prediction_metrics')) {
            return;
        }

        $table = 'corpwalletmanager_prediction_metrics';
        $columns = [
            'prediction_date'    => 'TIMESTAMP NULL DEFAULT NULL',
            'data_points_used'   => 'INT NULL DEFAULT NULL',
            'average_confidence' => 'DECIMAL(5,2) NULL DEFAULT NULL',
            'volatility_factor'  => 'DECIMAL(10,4) NULL DEFAULT NULL',
            'trend_strength'     => 'DECIMAL(10,4) NULL DEFAULT NULL',
        ];

        foreach ($columns as $column => $definition) {
            if (Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}");
            }
        }
    }

    public function down(): void
    {
        // Forward-only. Re-imposing NOT NULL could fail against rows this fix
        // legitimately allowed to be null, and would only reintroduce the
        // insert crash. No-op down() is intentional.
    }
}
