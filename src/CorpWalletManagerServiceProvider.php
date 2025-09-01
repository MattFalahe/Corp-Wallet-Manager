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
        
        // Ensure permissions exist after migrations run
        $this->addPermissionsToDatabase();
    }

    public function register()
    {
        // Register package configuration
        $this->mergeConfigFrom(__DIR__.'/Config/corpwalletmanager.php', 'corpwalletmanager');
        
        // CRITICAL: Register sidebar menu
        $this->mergeConfigFrom(__DIR__ . '/Config/corpwalletmanager.sidebar.php', 'package.sidebar');

        // Register permissions configuration (for SeAT's permission system)
        $this->registerPermissions(__DIR__ . '/Config/Permissions/corpwalletmanager.permissions.php', 'other');
               
        // Register commands
        $this->commands([
            \Seat\CorpWalletManager\Console\Commands\BackfillWalletDataCommand::class,
            \Seat\CorpWalletManager\Console\Commands\SetupPermissionsCommand::class,
        ]);
    }

    /**
     * Ensure permissions exist in database
     * This handles cases where migrations might not have run
     */
    private function addPermissionsToDatabase()
    {
        // Only run this if we're not in console (to avoid issues during migrations)
        if (app()->runningInConsole()) {
            return;
        }

        try {
            $permissions = [
                'corpwalletmanager.view',
                'corpwalletmanager.director_view',
                'corpwalletmanager.member_view',
                'corpwalletmanager.settings',
            ];

            foreach ($permissions as $permission) {
                \Seat\Web\Models\Acl\Permission::firstOrCreate([
                    'title' => $permission
                ]);
            }
        } catch (\Exception $e) {
            // Silently fail if database is not ready
            // This can happen during initial setup
        }
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
