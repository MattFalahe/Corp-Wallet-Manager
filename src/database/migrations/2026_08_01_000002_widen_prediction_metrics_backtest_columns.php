<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WidenPredictionMetricsBacktestColumns extends Migration
{
    /**
     * The backtest accuracy columns on corpwalletmanager_prediction_metrics
     * were created too narrow. bias_7d / bias_30d were DECIMAL(10,4) (max
     * ~999,999.9999) but bias is an ISK-scale prediction error - a large corp
     * easily runs a multi-billion ISK bias, which overflowed with SQLSTATE
     * 1264 "Out of range value for column 'bias_7d'" and, because
     * BacktestPredictions re-throws, aborted the whole backtest run. The
     * mape_7d / mape_30d columns (DECIMAL(6,2), max 9999.99%) have the same
     * exposure when a day's actual balance is tiny relative to the
     * prediction. Widen all four so the backtest can always store its result.
     */
    public function up(): void
    {
        if (! Schema::hasTable('corpwalletmanager_prediction_metrics')) {
            return;
        }

        $table = 'corpwalletmanager_prediction_metrics';
        $columns = [
            'mape_7d'  => 'DECIMAL(14,4) NULL DEFAULT NULL',
            'mape_30d' => 'DECIMAL(14,4) NULL DEFAULT NULL',
            'bias_7d'  => 'DECIMAL(30,4) NULL DEFAULT NULL',
            'bias_30d' => 'DECIMAL(30,4) NULL DEFAULT NULL',
        ];

        foreach ($columns as $column => $definition) {
            if (Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}");
            }
        }
    }

    public function down(): void
    {
        // Forward-only. Narrowing back would only re-introduce the overflow.
    }
}
