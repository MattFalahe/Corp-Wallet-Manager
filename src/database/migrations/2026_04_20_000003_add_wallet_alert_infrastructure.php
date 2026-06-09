<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWalletAlertInfrastructure extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Per-corporation latch for low-balance alerts, so the hourly
        // detector notifies once when a corporation crosses below the
        // threshold rather than every run while it stays low.
        if (! Schema::hasTable('corpwalletmanager_alert_state')) {
            Schema::create('corpwalletmanager_alert_state', function (Blueprint $table) {
                $table->bigInteger('corporation_id')->primary();
                $table->boolean('balance_is_low')->default(false);
                $table->timestamp('balance_low_notified_at')->nullable();
                $table->timestamps();
            });
        }

        // Alert subscription flags on the webhook rows (table created in
        // migration 000002). Guarded so this is safe to replay.
        if (Schema::hasTable('corpwalletmanager_webhooks')) {
            Schema::table('corpwalletmanager_webhooks', function (Blueprint $table) {
                if (! Schema::hasColumn('corpwalletmanager_webhooks', 'notify_large_transfer')) {
                    $table->boolean('notify_large_transfer')->default(true)->after('notify_on_demand_report');
                }
                if (! Schema::hasColumn('corpwalletmanager_webhooks', 'notify_low_balance')) {
                    $table->boolean('notify_low_balance')->default(true)->after('notify_large_transfer');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('corpwalletmanager_webhooks')) {
            Schema::table('corpwalletmanager_webhooks', function (Blueprint $table) {
                foreach (['notify_large_transfer', 'notify_low_balance'] as $column) {
                    if (Schema::hasColumn('corpwalletmanager_webhooks', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('corpwalletmanager_alert_state');
    }
}
