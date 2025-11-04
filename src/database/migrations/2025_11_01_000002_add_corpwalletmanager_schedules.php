<?php

use Illuminate\Database\Migrations\Migration;
use Seat\Services\Models\Schedule;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Add scheduled hourly wallet data update - runs every hour at :20 past the hour
        // This gives SeAT time to update corporation_wallet_journals table first
        Schedule::firstOrCreate(
            ['command' => 'corpwalletmanager:update-hourly'],
            [
                'expression'        => '20 * * * *', // Every hour at :20 past
                'allow_overlap'     => false,
                'allow_maintenance' => false,
            ]
        );

        // Add scheduled daily aggregation - runs daily at 1:00 AM
        // This aggregates yesterday's transactions
        Schedule::firstOrCreate(
            ['command' => 'corpwalletmanager:daily-aggregation'],
            [
                'expression'        => '0 1 * * *', // Daily at 1:00 AM
                'allow_overlap'     => false,
                'allow_maintenance' => false,
            ]
        );

        // Add scheduled daily prediction computation - runs daily at 2:00 AM
        // This runs after daily aggregation completes
        Schedule::firstOrCreate(
            ['command' => 'corpwalletmanager:compute-predictions'],
            [
                'expression'        => '0 2 * * *', // Daily at 2:00 AM
                'allow_overlap'     => false,
                'allow_maintenance' => false,
            ]
        );

        // Add scheduled division prediction computation - runs daily at 2:30 AM
        // This runs after corporation predictions complete
        Schedule::firstOrCreate(
            ['command' => 'corpwalletmanager:compute-division-predictions'],
            [
                'expression'        => '30 2 * * *', // Daily at 2:30 AM
                'allow_overlap'     => false,
                'allow_maintenance' => false,
            ]
        );

        // Add scheduled monthly report generation - runs on the 1st of each month at 3:00 AM
        Schedule::firstOrCreate(
            ['command' => 'corpwalletmanager:generate-report --period=monthly'],
            [
                'expression'        => '0 3 1 * *', // Monthly on the 1st at 3:00 AM
                'allow_overlap'     => false,
                'allow_maintenance' => false,
            ]
        );

        // Add scheduled weekly report generation - runs every Monday at 3:30 AM
        Schedule::firstOrCreate(
            ['command' => 'corpwalletmanager:generate-report --period=weekly'],
            [
                'expression'        => '30 3 * * 1', // Weekly on Monday at 3:30 AM
                'allow_overlap'     => false,
                'allow_maintenance' => false,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schedule::where('command', 'corpwalletmanager:update-hourly')->delete();
        Schedule::where('command', 'corpwalletmanager:daily-aggregation')->delete();
        Schedule::where('command', 'corpwalletmanager:compute-predictions')->delete();
        Schedule::where('command', 'corpwalletmanager:compute-division-predictions')->delete();
        Schedule::where('command', 'corpwalletmanager:generate-report --period=monthly')->delete();
        Schedule::where('command', 'corpwalletmanager:generate-report --period=weekly')->delete();
    }
};
