<?php

namespace Seat\CorpWalletManager;

use Seat\Services\AbstractSeatPlugin;
use Illuminate\Console\Scheduling\Schedule;

class CorpWalletManagerServiceProvider extends AbstractSeatPlugin
{
    public function boot()
    {
        // Check if routes are cached before loading
        if (!$this->app->routesAreCached()) {
            include __DIR__ . '/Http/routes.php';
        }
        
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang/', 'corpwalletmanager');
        $this->loadViewsFrom(__DIR__ . '/resources/views/', 'corpwalletmanager');
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations/');

        // Add publications
        $this->add_publications();

         // Register scheduled tasks
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            
            // Hourly wallet data update
            $schedule->job(new \Seat\CorpWalletManager\Jobs\UpdateHourlyWalletData)
                ->hourly()
                ->withoutOverlapping()
                ->name('corpwallet:hourly-update')
                ->description('Update corporation wallet data for the last hour');
            
            // Compute predictions every 6 hours
            $schedule->job(new \Seat\CorpWalletManager\Jobs\ComputeDailyPrediction)
                ->everySixHours()
                ->withoutOverlapping()
                ->name('corpwallet:compute-predictions')
                ->description('Compute wallet balance predictions');
            
            // Daily aggregation at 1 AM
            $schedule->job(new \Seat\CorpWalletManager\Jobs\DailyAggregation)
                ->dailyAt('01:00')
                ->withoutOverlapping()
                ->name('corpwallet:daily-aggregation')
                ->description('Aggregate daily wallet statistics');
            
            // Weekly division calculations (Mondays at 2 AM)
            $schedule->job(new \Seat\CorpWalletManager\Jobs\ComputeDivisionDailyPrediction)
                ->weeklyOn(1, '02:00')
                ->withoutOverlapping()
                ->name('corpwallet:division-predictions')
                ->description('Compute division wallet predictions');
            
            // Monthly full backfill (1st of month at 3 AM) - for data integrity
            $schedule->job(new \Seat\CorpWalletManager\Jobs\BackfillWalletData(null, 1))
                ->monthlyOn(1, '03:00')
                ->withoutOverlapping()
                ->name('corpwallet:monthly-backfill')
                ->description('Monthly wallet data integrity check and backfill');
        });
    }

    /**
     * Add content which must be published.
     */
    private function add_publications()
    {
        $this->publishes([
            __DIR__ . '/resources/js' => public_path('corpwalletmanager/js'),
        ], ['public', 'seat']);
        
        // Also publish assets directory if it exists
        $this->publishes([
            __DIR__ . '/resources/assets' => public_path('web/corpwalletmanager'),
        ], ['public', 'seat']);
    }

    public function register()
    {
        // Register sidebar configuration
        $this->mergeConfigFrom(__DIR__ . '/Config/corpwalletmanager.sidebar.php', 'package.sidebar');
        
        // Register permissions
        $this->registerPermissions(__DIR__ . '/Config/Permissions/corpwalletmanager.permissions.php', 'corpwalletmanager');
        
        // Register config
        $this->mergeConfigFrom(__DIR__.'/Config/corpwalletmanager.php', 'corpwalletmanager');
        
        // Register commands
        $this->commands([
            \Seat\CorpWalletManager\Console\Commands\BackfillWalletDataCommand::class,
            \Seat\CorpWalletManager\Console\Commands\SetupPermissionsCommand::class,
            \Seat\CorpWalletManager\Console\Commands\BackfillInternalTransfers::class,
        ]);
    }

    public function getName(): string
    {
        return 'CorpWallet Manager';
    }

    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/MattFalahe/Corp-Wallet-Manager';
    }

    public function getPackagistPackageName(): string
    {
        return 'corp-wallet-manager';
    }

    public function getPackagistVendorName(): string
    {
        return 'mattfalahe';
    }
}
