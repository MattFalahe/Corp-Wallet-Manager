<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Infrastructure for the two new anomaly detection alert types added in
 * 3.x: contribution_drop (a member whose prior 3-month contribution avg
 * collapses to <20% of the prior window) and unusual_recipient (a corp
 * withdrawal to a second_party_id that has never received from this corp
 * before, above a configurable threshold).
 *
 *   - Two new subscription flags on corpwalletmanager_webhooks
 *     (notify_contribution_drop, notify_unusual_recipient), defaulting
 *     to true so existing webhooks continue to receive every alert type.
 *
 *   - corpwalletmanager_anomaly_state table — one row per
 *     (corporation_id, character_id) holding the contribution-drop
 *     latch so the hourly detector fires on the crossing rather than
 *     every run while the drop persists. Cleared back to a non-latched
 *     state once recent contributions recover above 50% of prior.
 *
 * The unusual-recipient pass uses the same corpwalletmanager_settings
 * watermark scheme as large_transfer so it does not need its own
 * tracking table.
 */
class AddAnomalyAlertInfrastructure extends Migration
{
    public function up()
    {
        // Per-(corp, character) latch for contribution_drop alerts.
        if (! Schema::hasTable('corpwalletmanager_anomaly_state')) {
            Schema::create('corpwalletmanager_anomaly_state', function (Blueprint $table) {
                $table->bigInteger('corporation_id');
                $table->bigInteger('character_id');

                // True once the detector has fired a contribution_drop
                // alert for this (corp, character) pair. Cleared back to
                // false on recovery (recent_3mo_avg >= 50% of prior).
                $table->boolean('contribution_drop_latched')->default(false);

                // Audit trail: the prior/recent averages and timestamp
                // at the most recent crossing. Lets operators see what
                // the values were when the alert fired without scanning
                // logs.
                $table->decimal('contribution_drop_prior_avg', 24, 2)->default(0);
                $table->decimal('contribution_drop_recent_avg', 24, 2)->default(0);
                $table->timestamp('contribution_drop_notified_at')->nullable();

                $table->timestamps();

                $table->primary(['corporation_id', 'character_id']);
                $table->index('character_id');
            });
        }

        // Subscription flags on the webhook rows. Guarded so this is
        // safe to replay across staging / dev environments. Default
        // true keeps existing webhooks subscribed — the operator-set
        // threshold gates the feature, not silently-off checkboxes.
        if (Schema::hasTable('corpwalletmanager_webhooks')) {
            Schema::table('corpwalletmanager_webhooks', function (Blueprint $table) {
                if (! Schema::hasColumn('corpwalletmanager_webhooks', 'notify_contribution_drop')) {
                    $table->boolean('notify_contribution_drop')->default(true)->after('notify_low_balance');
                }
                if (! Schema::hasColumn('corpwalletmanager_webhooks', 'notify_unusual_recipient')) {
                    $table->boolean('notify_unusual_recipient')->default(true)->after('notify_contribution_drop');
                }
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('corpwalletmanager_webhooks')) {
            Schema::table('corpwalletmanager_webhooks', function (Blueprint $table) {
                foreach (['notify_contribution_drop', 'notify_unusual_recipient'] as $column) {
                    if (Schema::hasColumn('corpwalletmanager_webhooks', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('corpwalletmanager_anomaly_state');
    }
}
