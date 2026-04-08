<?php

namespace Seat\CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use Seat\CorpWalletManager\Jobs\BackfillDivisionWalletData;

class BackfillDivisionWalletDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'corpwalletmanager:backfill-divisions 
                            {year? : Specific year to backfill}
                            {month? : Specific month to backfill (1-12)}
                            {--recent : Backfill only last month}
                            {--months=1 : Number of months to backfill}
                            {--corporation= : Specific corporation ID}
                            {--all : Backfill all historical data (use with caution)}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill division wallet data for specific periods';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $year = $this->argument('year');
        $month = $this->argument('month');
        $recent = $this->option('recent');
        $months = $this->option('months');
        $corporationId = $this->option('corporation');
        $all = $this->option('all');

        if ($year && $month) {
            $this->info("Backfilling division data for {$year}-{$month}...");
            BackfillDivisionWalletData::dispatch($corporationId, null, $year, $month);
            
        } elseif ($recent) {
            $this->info("Backfilling last month of division data...");
            BackfillDivisionWalletData::dispatch($corporationId, 1);
            
        } elseif ($all) {
            if (!$this->confirm('This will process ALL historical division data. Continue?')) {
                return 0;
            }
            $this->info("Backfilling all historical division data...");
            BackfillDivisionWalletData::dispatch($corporationId, null);
            
        } else {
            $this->info("Backfilling last {$months} month(s) of division data...");
            BackfillDivisionWalletData::dispatch($corporationId, $months);
        }
        
        $this->info("Division backfill job dispatched.");
        
        return 0;
    }
}
