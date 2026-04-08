<?php

namespace Seat\CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use Seat\CorpWalletManager\Jobs\ComputeDivisionDailyPrediction;

class ComputeDivisionDailyPredictionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'corpwalletmanager:compute-division-predictions 
                            {--corporation= : Specific corporation ID to compute predictions for}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute daily predictions for division wallet balances';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $corporationId = $this->option('corporation');
        
        if ($corporationId) {
            $this->info("Computing division predictions for corporation {$corporationId}...");
        } else {
            $this->info('Computing division predictions for all corporations...');
        }
        
        // Dispatch the job
        dispatch(new ComputeDivisionDailyPrediction($corporationId));
        
        $this->info('Division prediction computation job dispatched.');
        
        return 0;
    }
}
