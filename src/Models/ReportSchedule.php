<?php

namespace CorpWalletManager\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Seat\Services\Models\ExtensibleModel;

/**
 * A per-corp + per-cadence report schedule row.
 *
 * Replaces the two hardcoded `corpwalletmanager:generate-report --period=weekly`
 * and `--period=monthly` ScheduleSeeder entries. The dispatcher cron
 * (`corpwalletmanager:dispatch-scheduled-reports`, every 5 minutes) reads
 * `enabled` schedules where `next_run_at <= now()` and dispatches a
 * `GenerateReport` job with the cadence-appropriate date window. The
 * Settings -> Scheduled Reports UI is the CRUD surface.
 *
 * Webhook delivery routing stays in `corpwalletmanager_webhooks` and the
 * existing per-category subscription flags. Schedules drive WHEN a report
 * runs; webhooks drive WHERE the result is delivered.
 */
class ReportSchedule extends ExtensibleModel
{
    /**
     * @var string
     */
    protected $table = 'corpwalletmanager_report_schedules';

    /**
     * Cadence keys accepted by `report_type`.
     */
    public const CADENCES = ['daily', 'weekly', 'monthly', 'quarterly', 'annual'];

    /**
     * @var array
     */
    protected $fillable = [
        'corporation_id',
        'report_type',
        'enabled',
        'minute',
        'hour',
        'day_of_week',
        'day_of_month',
        'month_of_year',
        'last_run_at',
        'next_run_at',
        'last_status',
        'last_error',
    ];

    /**
     * @var array
     */
    protected $casts = [
        'corporation_id' => 'integer',
        'enabled'        => 'boolean',
        'minute'         => 'integer',
        'hour'           => 'integer',
        'day_of_week'    => 'integer',
        'day_of_month'   => 'integer',
        'month_of_year'  => 'integer',
        'last_run_at'    => 'datetime',
        'next_run_at'    => 'datetime',
    ];

    /**
     * Only enabled schedules.
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * Schedules whose next_run_at is at or before $now (i.e. due to fire).
     * Rows with a null next_run_at are treated as due so a freshly-created
     * schedule fires on the next dispatcher pass.
     */
    public function scopeDue(Builder $query, ?Carbon $now = null): Builder
    {
        $now = $now ?: Carbon::now('UTC');

        return $query->where(function (Builder $q) use ($now) {
            $q->whereNull('next_run_at')
              ->orWhere('next_run_at', '<=', $now);
        });
    }

    /**
     * Compute the next datetime (UTC) this schedule should fire, based on
     * the cadence and day/hour/minute fields. Returns null when the
     * required day fields for the cadence are missing (defensive guard;
     * the controller validation rejects malformed combinations on write).
     *
     * Logic per cadence:
     *  - daily:     today at hour:minute, or tomorrow if past
     *  - weekly:    next occurrence of day_of_week at hour:minute
     *  - monthly:   this month on day_of_month at hour:minute, or next month if past
     *  - quarterly: next quarter boundary (Jan 1 / Apr 1 / Jul 1 / Oct 1)
     *               clamped to day_of_month within the first month at hour:minute
     *  - annual:    this year on month_of_year/day_of_month, or next year if past
     */
    public function computeNextRunAt(): ?Carbon
    {
        $now = Carbon::now('UTC');
        $hour = (int) $this->hour;
        $minute = (int) $this->minute;
        $cadence = strtolower((string) $this->report_type);

        switch ($cadence) {
            case 'daily':
                $candidate = $now->copy()->setTime($hour, $minute, 0);
                if ($candidate->lessThanOrEqualTo($now)) {
                    $candidate->addDay();
                }
                return $candidate;

            case 'weekly':
                if ($this->day_of_week === null) {
                    return null;
                }
                // Carbon: 1 = Monday .. 7 = Sunday (ISO). Our schema follows
                // the same convention so the value flows straight through.
                $targetDow = ((int) $this->day_of_week);
                $todayIso = (int) $now->isoWeekday();
                $daysAhead = ($targetDow - $todayIso + 7) % 7;
                $candidate = $now->copy()->startOfDay()->addDays($daysAhead)->setTime($hour, $minute, 0);
                // If today's slot has already passed, jump a full week.
                if ($candidate->lessThanOrEqualTo($now)) {
                    $candidate->addWeek();
                }
                return $candidate;

            case 'monthly':
                if ($this->day_of_month === null) {
                    return null;
                }
                $dom = $this->clampDayOfMonth((int) $this->day_of_month, $now->year, $now->month);
                $candidate = $now->copy()->setDate($now->year, $now->month, $dom)->setTime($hour, $minute, 0);
                if ($candidate->lessThanOrEqualTo($now)) {
                    $next = $now->copy()->addMonthNoOverflow();
                    $dom = $this->clampDayOfMonth((int) $this->day_of_month, $next->year, $next->month);
                    $candidate = $next->setDate($next->year, $next->month, $dom)->setTime($hour, $minute, 0);
                }
                return $candidate;

            case 'quarterly':
                if ($this->day_of_month === null) {
                    return null;
                }
                return $this->nextQuarterlyOccurrence($now, (int) $this->day_of_month, $hour, $minute);

            case 'annual':
                if ($this->day_of_month === null || $this->month_of_year === null) {
                    return null;
                }
                $month = max(1, min(12, (int) $this->month_of_year));
                $dom = $this->clampDayOfMonth((int) $this->day_of_month, $now->year, $month);
                $candidate = $now->copy()->setDate($now->year, $month, $dom)->setTime($hour, $minute, 0);
                if ($candidate->lessThanOrEqualTo($now)) {
                    $year = $now->year + 1;
                    $dom = $this->clampDayOfMonth((int) $this->day_of_month, $year, $month);
                    $candidate = $now->copy()->setDate($year, $month, $dom)->setTime($hour, $minute, 0);
                }
                return $candidate;
        }

        return null;
    }

    /**
     * Next quarter boundary (Jan / Apr / Jul / Oct) at day_of_month + hour:minute.
     * If we're currently inside the first month of a quarter and the slot
     * within that month is still in the future, fire then; otherwise jump
     * to the next quarter's first month.
     */
    private function nextQuarterlyOccurrence(Carbon $now, int $dayOfMonth, int $hour, int $minute): Carbon
    {
        $quarterStartMonths = [1, 4, 7, 10];
        $year = $now->year;

        foreach ($quarterStartMonths as $startMonth) {
            $dom = $this->clampDayOfMonth($dayOfMonth, $year, $startMonth);
            $candidate = $now->copy()->setDate($year, $startMonth, $dom)->setTime($hour, $minute, 0);
            if ($candidate->greaterThan($now)) {
                return $candidate;
            }
        }
        // Every quarter boundary this year has passed - roll to Jan next year.
        $dom = $this->clampDayOfMonth($dayOfMonth, $year + 1, 1);
        return $now->copy()->setDate($year + 1, 1, $dom)->setTime($hour, $minute, 0);
    }

    /**
     * Clamp the operator-configured day_of_month against the actual length
     * of the target month so Feb / 30 / 31 doesn't blow up. We cap at 28 on
     * the UI side too, but defensive here in case a raw API call writes a
     * higher value or a leap-year edge case hits.
     */
    private function clampDayOfMonth(int $dom, int $year, int $month): int
    {
        $daysInMonth = (int) Carbon::create($year, $month, 1)->daysInMonth;
        return max(1, min($dom, $daysInMonth));
    }

    /**
     * Human-readable cadence summary (e.g. "Monday at 03:30 UTC",
     * "Day 1 of every month at 03:00 UTC"). Used by the Settings UI for
     * the day/time column.
     */
    public function getHumanCadenceAttribute(): string
    {
        $time = sprintf('%02d:%02d UTC', (int) $this->hour, (int) $this->minute);
        $cadence = strtolower((string) $this->report_type);

        switch ($cadence) {
            case 'daily':
                return "Every day at {$time}";

            case 'weekly':
                $dowNames = [
                    1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
                    4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday',
                ];
                $name = $dowNames[(int) $this->day_of_week] ?? '???';
                return "Every {$name} at {$time}";

            case 'monthly':
                return "Day {$this->day_of_month} of every month at {$time}";

            case 'quarterly':
                return "Day {$this->day_of_month} of Jan / Apr / Jul / Oct at {$time}";

            case 'annual':
                $monthNames = [
                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                ];
                $month = $monthNames[(int) $this->month_of_year] ?? '???';
                return "{$month} {$this->day_of_month} every year at {$time}";
        }

        return $time;
    }
}
