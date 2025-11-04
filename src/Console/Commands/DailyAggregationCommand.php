<?php

namespace Seat\CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use Seat\CorpWalletManager\Jobs\DailyAggregation;

class DailyAggregationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'corpwalletmanager:daily-aggregation';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run daily aggregation of wallet transactions';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting daily aggregation...');
        
        // Dispatch the job
        dispatch(new DailyAggregation());
        
        $this->info('Daily aggregation job dispatched.');
        
        return 0;
    }
}
