<?php

namespace CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use CorpWalletManager\Jobs\DetectWalletAlerts;

class DetectWalletAlertsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'corpwalletmanager:detect-alerts';

    /**
     * @var string
     */
    protected $description = 'Detect large wallet transactions and low corporation balances, and dispatch alerts';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        dispatch(new DetectWalletAlerts());

        $this->info('Wallet alert detection job dispatched.');

        return 0;
    }
}
