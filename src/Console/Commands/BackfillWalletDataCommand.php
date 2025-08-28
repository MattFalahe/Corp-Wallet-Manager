<?php
namespace Seat\CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use Seat\CorpWalletManager\Jobs\BackfillWalletData;

class BackfillWalletDataCommand extends Command
{
    protected $signature = 'corpwalletmanager:backfill';
    protected $description = 'Backfill predictions and monthly balances from existing SeAT wallet journals';

    public function handle()
    {
        $this->info("Dispatching backfill job...");
        BackfillWalletData::dispatch();
        $this->info("Done.");
    }
}
