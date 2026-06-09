<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Per-corp + per-cadence report schedule table.
 *
 * Replaces the hardcoded `corpwalletmanager:generate-report --period=weekly`
 * and `--period=monthly` ScheduleSeeder entries with operator-configurable
 * schedules. The dispatcher cron (`corpwalletmanager:dispatch-scheduled-reports`,
 * runs every 5 minutes) reads this table; the Settings -> Scheduled Reports
 * UI is the CRUD surface.
 *
 * Backwards-compat seeding: every corp with rows in `corporation_wallet_journals`
 * gets default weekly + monthly schedules matching the pre-3.0 hardcoded times,
 * so existing operators keep their Mondays-at-03:30 weekly + 1st-at-03:00 monthly
 * deliveries without re-configuring anything.
 */
class CreateCorpwalletmanagerReportSchedules extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Guard re-run: tolerate the table already existing from a previous
        // attempt that failed during the seed step.
        if (! Schema::hasTable('corpwalletmanager_report_schedules')) {
            Schema::create('corpwalletmanager_report_schedules', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('corporation_id');
                // Cadence string: daily / weekly / monthly / quarterly / annual.
                $table->string('report_type', 32);
                $table->boolean('enabled')->default(true);
                // Time of day in UTC the schedule fires.
                $table->unsignedTinyInteger('minute')->default(0);   // 0-59
                $table->unsignedTinyInteger('hour')->default(3);     // 0-23
                // Day axis is cadence-specific:
                // weekly => day_of_week 1-7 (1=Mon), day_of_month + month_of_year null
                // monthly / quarterly => day_of_month 1-28 (clamped for Feb)
                // annual => month_of_year 1-12 + day_of_month 1-28
                // daily => all three null
                $table->unsignedTinyInteger('day_of_week')->nullable();
                $table->unsignedTinyInteger('day_of_month')->nullable();
                $table->unsignedTinyInteger('month_of_year')->nullable();
                // Bookkeeping populated by the dispatcher.
                $table->timestamp('last_run_at')->nullable();
                $table->timestamp('next_run_at')->nullable();
                // 'ok' | 'failed' (lowercase, matches RecalcLog convention).
                $table->string('last_status', 16)->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
                $table->unique(['corporation_id', 'report_type'], 'uk_corp_type');
                $table->index(['enabled', 'next_run_at'], 'idx_enabled_next');
            });
        }

        // Backwards-compat seeding. Wrapped so a journal-table lookup failure
        // can never block the schema creation itself. Idempotent via the
        // (corporation_id, report_type) unique index.
        try {
            $this->seedDefaultsForExistingCorps();
        } catch (\Throwable $e) {
            Log::warning('[Corp Wallet Manager] Default schedule seeding skipped: ' . $e->getMessage());
        }
    }

    /**
     * For every distinct corporation_id in `corporation_wallet_journals`,
     * insert weekly + monthly schedules that mirror the pre-3.0 hardcoded
     * ScheduleSeeder times. Existing rows (e.g. on a re-run) are left alone.
     */
    private function seedDefaultsForExistingCorps(): void
    {
        if (! Schema::hasTable('corporation_wallet_journals')) {
            return;
        }
        if (! Schema::hasTable('corpwalletmanager_report_schedules')) {
            return;
        }

        $corpIds = DB::table('corporation_wallet_journals')
            ->select('corporation_id')
            ->distinct()
            ->pluck('corporation_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($corpIds)) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($corpIds as $corpId) {
            // Weekly: Monday 03:30 UTC (matches the v2 hardcoded entry).
            $rows[] = [
                'corporation_id' => $corpId,
                'report_type'    => 'weekly',
                'enabled'        => true,
                'minute'         => 30,
                'hour'           => 3,
                'day_of_week'    => 1,
                'day_of_month'   => null,
                'month_of_year'  => null,
                'last_run_at'    => null,
                'next_run_at'    => null,
                'last_status'    => null,
                'last_error'     => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
            // Monthly: 1st of the month at 03:00 UTC (matches the v2 hardcoded entry).
            $rows[] = [
                'corporation_id' => $corpId,
                'report_type'    => 'monthly',
                'enabled'        => true,
                'minute'         => 0,
                'hour'           => 3,
                'day_of_week'    => null,
                'day_of_month'   => 1,
                'month_of_year'  => null,
                'last_run_at'    => null,
                'next_run_at'    => null,
                'last_status'    => null,
                'last_error'     => null,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        // insertOrIgnore so a re-run on an already-seeded install is a no-op
        // (the uk_corp_type unique index does the dedup work).
        DB::table('corpwalletmanager_report_schedules')->insertOrIgnore($rows);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('corpwalletmanager_report_schedules');
    }
}
