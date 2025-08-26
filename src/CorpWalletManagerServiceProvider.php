<?php
namespace Seat\CorpWalletManager;

use Seat\Services\AbstractSeatPlugin;

class CorpWalletManagerServiceProvider extends AbstractSeatPlugin
{
    public function bootPlugin()
    {
        // Load configuration
        $this->mergeConfigFrom(__DIR__.'/Config/corpwalletmanager.php', 'corpwalletmanager');
        
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/Http/routes/web.php');
        
        // Load views
        $this->loadViewsFrom(__DIR__.'/resources/views', 'corpwalletmanager');
        
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        
        // Add menu items - these should be added in the boot method
        $this->addCorporationMenuItem('CorpWallet Manager (Directors)', [
            'route' => 'corpwalletmanager.director',
            'permission' => 'corporation.wallet.view',
            'icon' => 'fa fa-line-chart'
        ]);
        
        $this->addCorporationMenuItem('CorpWallet Manager (Members)', [
            'route' => 'corpwalletmanager.member',
            'permission' => 'corporation.member.view',
            'icon' => 'fa fa-area-chart'
        ]);
        
        $this->addCorporationMenuItem('CorpWallet Manager Settings', [
            'route' => 'corpwalletmanager.settings',
            'permission' => 'corporation.wallet.view',
            'icon' => 'fa fa-cog'
        ]);
    }

    public function registerPlugin()
    {
        // Register console commands
        $this->commands([
            \Seat\CorpWalletManager\Console\Commands\BackfillWalletDataCommand::class,
        ]);
    }
    
    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            'corpwalletmanager',
        ];
    }
}
