<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * State table for the HR-Manager-facing member.* milestone events.
 *
 * Without this, every hourly ComputeCharacterContributions run would
 * re-publish "member X stalled" for the same member every hour they
 * stay stalled, defeating the purpose of an event (subscribers want
 * the edge transition, not the continuous condition). The columns
 * track the last-published state per (corp, character) so the
 * notifier service can detect crossings vs continuous holds.
 *
 * Three events are checked per run; this table holds one row per
 * (corp, character) covering all three with separate state columns
 * to avoid join complexity in the notifier loop.
 */
class CreateMemberMilestoneState extends Migration
{
    public function up()
    {
        if (Schema::hasTable('corpwalletmanager_member_milestone_state')) {
            return;
        }

        Schema::create('corpwalletmanager_member_milestone_state', function (Blueprint $table) {
            $table->bigInteger('corporation_id');
            $table->bigInteger('character_id');

            // For member.contribution.stalled — the period (YYYY-MM) at
            // which we last published a stalled event. Null when never
            // published. Cleared back to null when the member becomes
            // active again so the next stall after a recovery emits
            // properly.
            $table->string('last_stalled_period', 7)->nullable();

            // For member.contribution.milestone — the highest lifetime
            // ISK threshold we've already published a milestone crossing
            // for. Stored as the raw threshold value (e.g. 1000000000,
            // 5000000000). Notifier compares lifetime total against the
            // configured ladder and publishes for any new rung.
            $table->decimal('highest_milestone_isk', 24, 2)->default(0);

            // For member.tax.compliance_dropped — last period at which
            // compliance was below the configured floor. Notifier only
            // republishes when compliance has recovered above the floor
            // since this period, then drops back below.
            $table->string('last_compliance_drop_period', 7)->nullable();

            $table->timestamps();

            $table->primary(['corporation_id', 'character_id']);
            $table->index('character_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('corpwalletmanager_member_milestone_state');
    }
}
