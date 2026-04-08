<?php

namespace Seat\CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use Seat\CorpWalletManager\Jobs\ComputeDailyPrediction;

class ComputeDailyPredictionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'corpwalletmanager:compute-predictions 
                            {--corporation= : Specific corporation ID to compute predictions for}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute daily predictions for wallet balances';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $corporationId = $this->option('corporation');
        
        if ($corporationId) {
            $this->info("Computing predictions for corporation {$corporationId}...");
        } else {
            $this->info('Computing predictions for all corporations...');
        }
        
        // Dispatch the job
        dispatch(new ComputeDailyPrediction($corporationId));
        
        $this->info('Prediction computation job dispatched.');
        
        return 0;
    }
}
