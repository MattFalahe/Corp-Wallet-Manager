<?php

namespace Seat\CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use Seat\CorpWalletManager\Jobs\UpdateHourlyWalletData;

class UpdateHourlyWalletDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'corpwalletmanager:update-hourly';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update wallet data hourly for recent transactions';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting hourly wallet data update...');
        
        // Dispatch the job
        dispatch(new UpdateHourlyWalletData());
        
        $this->info('Hourly update job dispatched.');
        
        return 0;
    }
}
