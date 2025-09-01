<?php
namespace Seat\CorpWalletManager;

use Seat\Services\AbstractSeatPlugin;

class CorpWalletManagerServiceProvider extends AbstractSeatPlugin
{
    public function boot()
    {
        $this->add_routes();
        $this->add_views();
        $this->add_translations();
        $this->add_migrations();
    }

    public function register()
    {
        // Register package configuration
        $this->mergeConfigFrom(__DIR__.'/Config/corpwalletmanager.php', 'corpwalletmanager');
        
        // IMPORTANT: Register sidebar menu instead of corporation menu
        $this->mergeConfigFrom(__DIR__ . '/Config/Menu/package.sidebar.php', 'package.sidebar');
        
        // Don't register corporation menu anymore - comment out or remove
        // $this->mergeConfigFrom(__DIR__ . '/Config/Menu/corporation.php', 'package.corporation.menu');

        // Register permissions as 'other' type for separate section
        $this->registerPermissions(__DIR__ . '/Config/Permissions/corpwalletmanager.permissions.php', 'other');
               
        // Register commands
        $this->commands([
            \Seat\CorpWalletManager\Console\Commands\BackfillWalletDataCommand::class,
        ]);
    }

    private function add_routes()
    {
        $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
    }

    private function add_views()
    {
        $this->loadViewsFrom(__DIR__ . '/resources/views', 'corpwalletmanager');
    }

    private function add_translations()
    {
        $this->loadTranslationsFrom(__DIR__ . '/resources/lang', 'corpwalletmanager');
    }

    private function add_migrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations/');
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
        return 'mattfalahe/corp-wallet-manager';
    }

    public function getPackagistVendorName(): string
    {
        return 'mattfalahe';
    }
}
