<?php

namespace Seat\CorpWalletManager;

use Seat\Services\AbstractSeatPlugin;
use Seat\CorpWalletManager\Console\Commands\UpdateHourlyWalletDataCommand;
use Seat\CorpWalletManager\Console\Commands\DailyAggregationCommand;
use Seat\CorpWalletManager\Console\Commands\ComputeDailyPredictionCommand;
use Seat\CorpWalletManager\Console\Commands\ComputeDivisionDailyPredictionCommand;
use Seat\CorpWalletManager\Console\Commands\GenerateReportCommand;
use Seat\CorpWalletManager\Console\Commands\BackfillWalletDataCommand;
use Seat\CorpWalletManager\Console\Commands\BackfillDivisionWalletDataCommand;
use Seat\CorpWalletManager\Console\Commands\IntegrityCheckCommand;
use Seat\CorpWalletManager\Database\Seeders\ScheduleSeeder;

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
            UpdateHourlyWalletDataCommand::class,
            DailyAggregationCommand::class,
            ComputeDailyPredictionCommand::class,
            ComputeDivisionDailyPredictionCommand::class,
            GenerateReportCommand::class,
            BackfillWalletDataCommand::class,
            BackfillDivisionWalletDataCommand::class,
            IntegrityCheckCommand::class,
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
