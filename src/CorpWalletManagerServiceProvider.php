<?php
namespace Seat\CorpWalletManager;

use Seat\Services\AbstractSeatPlugin;
use Illuminate\Support\Facades\Log;

class CorpWalletManagerServiceProvider extends AbstractSeatPlugin
{
    public function bootPlugin()
    {
        try {
            // Load configuration
            $this->mergeConfigFrom(__DIR__.'/Config/corpwalletmanager.php', 'corpwalletmanager');
            
            // Load routes
            $this->loadRoutesFrom(__DIR__.'/Http/routes/web.php');
            
            // Load views
            $this->loadViewsFrom(__DIR__.'/resources/views', 'corpwalletmanager');
            
            // Load migrations
            $this->loadMigrationsFrom(__DIR__.'/database/migrations');
            
            // Add menu items safely
            $this->addMenuItems();
            
        } catch (\Exception $e) {
            Log::error('CorpWalletManager plugin boot error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            // Don't re-throw - we don't want to break the entire application
        }
    }

    public function registerPlugin()
    {
        try {
            // Register console commands
            $this->commands([
                \Seat\CorpWalletManager\Console\Commands\BackfillWalletDataCommand::class,
            ]);
        } catch (\Exception $e) {
            Log::error('CorpWalletManager plugin register error: ' . $e->getMessage());
        }
    }
    
    /**
     * Safely add menu items with error handling
     */
    private function addMenuItems()
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('CorpWalletManager menu items error: ' . $e->getMessage());
        }
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
