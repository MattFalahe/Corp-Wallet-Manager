<?php
namespace Seat\CorpWalletManager;

use Seat\Services\AbstractSeatPlugin;

class CorpWalletManagerServiceProvider extends AbstractSeatPlugin
{
    public function bootPlugin()
    {
        $this->mergeConfigFrom(__DIR__.'/Config/corpwalletmanager.php', 'corpwalletmanager');
        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
        $this->loadViewsFrom(__DIR__.'/resources/views', 'corpwalletmanager');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Run backfill once after installation
        if (!\Seat\CorpWalletManager\Models\MonthlyBalance::exists()) {
            \Seat\CorpWalletManager\Jobs\BackfillWalletData::dispatch();
        }        

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
        $this->commands([
        \Seat\CorpWalletManager\Console\Commands\BackfillWalletDataCommand::class,
        ]);
    }
}
