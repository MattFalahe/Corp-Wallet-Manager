<?php

namespace Seat\CorpWalletManager\Database\Seeders;

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
                'command' => 'corpwalletmanager:generate-report --period=monthly',
                'expression' => '0 3 1 * *', // Run monthly on the 1st at 3:00 AM
                'allow_overlap' => false,
                'allow_maintenance' => false,
                'ping_before' => null,
                'ping_after' => null,
            ],
            [
                'command' => 'corpwalletmanager:generate-report --period=weekly',
                'expression' => '30 3 * * 1', // Run weekly on Monday at 3:30 AM
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
     * @return array
     */
    public function getDeprecatedSchedules(): array
    {
        return [];
    }
}
