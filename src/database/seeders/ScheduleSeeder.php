<?php

namespace CorpWalletManager\Database\Seeders;

use Seat\Services\Seeding\AbstractScheduleSeeder;

class ScheduleSeeder extends AbstractScheduleSeeder
{
    /**
     * Returns the schedule definitions.
     *
     * @return array
     */
    public function getSchedules(): array
    {
        return [
            [
                'command' => 'corpwalletmanager:update-hourly',
                'expression' => '20 * * * *', // Run every hour at :20 past
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            [
                'command' => 'corpwalletmanager:daily-aggregation',
                'expression' => '0 1 * * *', // Run daily at 1:00 AM
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            [
                'command' => 'corpwalletmanager:compute-predictions',
                'expression' => '0 2 * * *', // Run daily at 2:00 AM
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            [
                'command' => 'corpwalletmanager:compute-division-predictions',
                'expression' => '30 2 * * *', // Run daily at 2:30 AM
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            [
                'command' => 'corpwalletmanager:backtest',
                // Run daily at 2:45 AM, after predictions are computed for today
                // so we can compare yesterday's forecasts against the freshly
                // aggregated actual balances.
                'expression' => '45 2 * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            [
                'command' => 'corpwalletmanager:detect-alerts',
                // Hourly at :40, after the :20 wallet aggregation and after
                // SeAT's own ESI wallet journal/balance updates.
                'expression' => '40 * * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            [
                'command' => 'corpwalletmanager:compute-contributions',
                // Hourly at :50, after detect-alerts and after SeAT's own
                // journal sync, so the per-character contribution cache
                // reflects the freshest data for the Top Contributors view
                // and the HR Manager capabilities.
                'expression' => '50 * * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            [
                'command' => 'corpwalletmanager:compute-personal-wallet-aggregates',
                // Hourly at :55, after compute-contributions and after SeAT's
                // own character_wallet_journals sync. Populates the per-
                // character per-month aggregate that backs the My Personal
                // Wallet tab so the tab open is a single small lookup
                // rather than an on-demand scan of the raw journal.
                'expression' => '55 * * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            [
                // Every 5 minutes the dispatcher reads the per-corp + per-cadence
                // schedule table and fires GenerateReport for every row whose
                // next_run_at is due. A 5-minute tick keeps a schedule set for
                // 03:00 firing within 5 minutes of 03:00 (an hourly tick would
                // let "03:00" slip to 03:59 which is wrong by half an hour for
                // the canonical "Monday 03:30" weekly slot).
                //
                // This replaces the two hardcoded report-generation entries
                // we previously kept here (one weekly, one monthly). See
                // getDeprecatedSchedules() below for the cleanup contract.
                'command' => 'corpwalletmanager:dispatch-scheduled-reports',
                'expression' => '*/5 * * * *',
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
        ];
    }

    /**
     * Returns a list of commands to remove from the schedule.
     *
     * The two hardcoded report-generation entries below were retired in v3.0
     * when the Settings -> Scheduled Reports panel went live. Listing them
     * here lets AbstractScheduleSeeder delete the rows from `schedules` on
     * the next seed pass so the operator doesn't end up with both the legacy
     * hardcoded entries AND the per-corp UI-configured schedules firing the
     * same report twice.
     *
     * @return array
     */
    public function getDeprecatedSchedules(): array
    {
        return [
            'corpwalletmanager:generate-report --period=monthly',
            'corpwalletmanager:generate-report --period=weekly',
        ];
    }
}
