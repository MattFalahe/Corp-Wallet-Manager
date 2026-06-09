<?php

namespace CorpWalletManager\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use CorpWalletManager\Jobs\GenerateReport;
use CorpWalletManager\Models\ReportSchedule;

/**
 * Reads the per-corp + per-cadence schedule table and dispatches
 * `GenerateReport` for every row whose `next_run_at` is at or past now.
 *
 * Replaces the two hardcoded ScheduleSeeder entries (
 * `corpwalletmanager:generate-report --period=weekly` and `--period=monthly`)
 * that fired for every corp with wallet data regardless of operator intent.
 *
 * Scheduled in ScheduleSeeder at `every 5 minutes` so a schedule set for
 * 03:00 actually fires within a 5-minute window of 03:00 (an hourly tick
 * would let the operator's intended 03:00 slip to 03:59, which makes
 * "Monday at 03:30" wrong by half an hour).
 *
 * Per-cadence date window for the dispatched job:
 *  - daily:     yesterday 00:00 .. yesterday 23:59:59 UTC
 *  - weekly:    previous Mon 00:00 .. previous Sun 23:59:59 UTC
 *  - monthly:   previous calendar month 1st 00:00 .. last-day 23:59:59 UTC
 *  - quarterly: previous calendar quarter (Q1 -> Q4 of prior year)
 *  - annual:    previous calendar year 01-01 00:00 .. 12-31 23:59:59 UTC
 *
 * Each row is updated on completion (or failure) with last_run_at, last_status,
 * last_error, and a recomputed next_run_at so the next dispatcher pass picks
 * the right time. Failures don't block other rows; we catch per-row.
 */
class DispatchScheduledReportsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'corpwalletmanager:dispatch-scheduled-reports
                            {--dry-run : Show which schedules would fire without dispatching}';

    /**
     * @var string
     */
    protected $description = 'Dispatch GenerateReport for every report schedule whose next_run_at is due.';

    public function handle(): int
    {
        $now = Carbon::now('UTC');
        $due = ReportSchedule::enabled()->due($now)->get();

        if ($due->isEmpty()) {
            $this->info('No schedules due at ' . $now->toIso8601String());
            return 0;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->info('Found ' . $due->count() . ' due schedule(s) at ' . $now->toIso8601String() . ($dryRun ? ' (dry run)' : ''));

        $dispatched = 0;
        $failed = 0;

        foreach ($due as $schedule) {
            try {
                [$from, $to] = $this->dateWindowFor($schedule->report_type, $now);

                if ($dryRun) {
                    $this->line(sprintf(
                        '  [dry-run] Corp %d / %s => %s .. %s',
                        (int) $schedule->corporation_id,
                        $schedule->report_type,
                        $from->toDateTimeString(),
                        $to->toDateTimeString()
                    ));
                    continue;
                }

                // sendToDiscord = true so subscribed webhooks deliver the
                // result, matching the pre-3.0 ScheduleSeeder-driven behavior.
                dispatch(new GenerateReport(
                    (int) $schedule->corporation_id,
                    $schedule->report_type,
                    $from,
                    $to,
                    [],
                    true
                ));

                $schedule->last_run_at = $now;
                $schedule->last_status = 'ok';
                $schedule->last_error  = null;
                $schedule->next_run_at = $schedule->computeNextRunAt();
                $schedule->save();

                $dispatched++;

                Log::info('[Corp Wallet Manager] Scheduled report dispatched', [
                    'schedule_id'    => $schedule->id,
                    'corporation_id' => $schedule->corporation_id,
                    'report_type'    => $schedule->report_type,
                    'from'           => $from->toIso8601String(),
                    'to'             => $to->toIso8601String(),
                    'next_run_at'    => $schedule->next_run_at ? $schedule->next_run_at->toIso8601String() : null,
                ]);
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('[Corp Wallet Manager] Scheduled report dispatch failed', [
                    'schedule_id'    => $schedule->id,
                    'corporation_id' => $schedule->corporation_id,
                    'report_type'    => $schedule->report_type,
                    'error'          => $e->getMessage(),
                ]);
                try {
                    $schedule->last_run_at = $now;
                    $schedule->last_status = 'failed';
                    $schedule->last_error  = mb_substr($e->getMessage(), 0, 1000);
                    $schedule->next_run_at = $schedule->computeNextRunAt();
                    $schedule->save();
                } catch (\Throwable $inner) {
                    // Even the bookkeeping write failed - log + move on. The
                    // next dispatcher pass will see the still-stale next_run_at
                    // and try again, which is the right behaviour.
                    Log::error('[Corp Wallet Manager] Schedule bookkeeping save failed: ' . $inner->getMessage());
                }
            }
        }

        $this->info("Dispatched: {$dispatched}, Failed: {$failed}");
        return 0;
    }

    /**
     * Compute the [from, to] reporting window for the given cadence,
     * relative to $now. Reports always cover the COMPLETED prior period
     * (i.e. "Monday's weekly report" summarises the prior Mon..Sun, not
     * Mon-so-far), matching what the pre-3.0 GenerateReportCommand did
     * for --period=monthly and --period=weekly.
     */
    private function dateWindowFor(string $cadence, Carbon $now): array
    {
        switch (strtolower($cadence)) {
            case 'daily':
                $from = $now->copy()->subDay()->startOfDay();
                $to   = $now->copy()->subDay()->endOfDay();
                return [$from, $to];

            case 'weekly':
                // ISO week: previous Mon 00:00 .. previous Sun 23:59:59. We
                // use the explicit "previous Monday" rather than subWeek so
                // a Monday-morning dispatcher pass covers the Mon..Sun that
                // just finished rather than the current incomplete week.
                $startOfThisWeek = $now->copy()->startOfWeek(Carbon::MONDAY);
                $from = $startOfThisWeek->copy()->subWeek();
                $to   = $startOfThisWeek->copy()->subSecond();
                return [$from, $to];

            case 'monthly':
                $from = $now->copy()->subMonthNoOverflow()->startOfMonth();
                $to   = $now->copy()->subMonthNoOverflow()->endOfMonth();
                return [$from, $to];

            case 'quarterly':
                // Previous calendar quarter. firstOfQuarter() rolls back to
                // the current Q1/Q2/Q3/Q4 start; subtract one second for the
                // end of the prior quarter, then snap to that quarter's start.
                $startOfThisQuarter = $now->copy()->firstOfQuarter()->startOfDay();
                $to   = $startOfThisQuarter->copy()->subSecond();
                $from = $to->copy()->firstOfQuarter()->startOfDay();
                return [$from, $to];

            case 'annual':
                $year = $now->year - 1;
                $from = Carbon::create($year, 1, 1, 0, 0, 0, 'UTC');
                $to   = Carbon::create($year, 12, 31, 23, 59, 59, 'UTC');
                return [$from, $to];
        }

        // Unknown cadence (shouldn't reach here - validation gates it). Fall
        // back to a 24h window so the dispatched job has SOMETHING coherent.
        return [$now->copy()->subDay(), $now->copy()];
    }
}
