<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('corpwalletmanager_prediction_metrics')) {
            return;
        }

        Schema::table('corpwalletmanager_prediction_metrics', function (Blueprint $table) {
            if (!Schema::hasColumn('corpwalletmanager_prediction_metrics', 'mape_7d')) {
                $table->decimal('mape_7d', 6, 2)->nullable()->after('trend_strength');
            }
            if (!Schema::hasColumn('corpwalletmanager_prediction_metrics', 'mape_30d')) {
                $table->decimal('mape_30d', 6, 2)->nullable()->after('mape_7d');
            }
            if (!Schema::hasColumn('corpwalletmanager_prediction_metrics', 'bias_7d')) {
                $table->decimal('bias_7d', 10, 4)->nullable()->after('mape_30d');
            }
            if (!Schema::hasColumn('corpwalletmanager_prediction_metrics', 'bias_30d')) {
                $table->decimal('bias_30d', 10, 4)->nullable()->after('bias_7d');
            }
            if (!Schema::hasColumn('corpwalletmanager_prediction_metrics', 'last_backtest_at')) {
                $table->timestamp('last_backtest_at')->nullable()->after('bias_30d');
            }
        });
    }

    public function down(): void
    {
        // Forward-only. Dropping columns would lose backtest history on
        // rollback with no benefit. No-op down() is intentional.
    }
};
