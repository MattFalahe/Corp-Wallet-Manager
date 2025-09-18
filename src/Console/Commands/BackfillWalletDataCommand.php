<?php
namespace Seat\CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use Seat\CorpWalletManager\Jobs\BackfillWalletData;

class BackfillWalletDataCommand extends Command
{
    protected $signature = 'corpwalletmanager:backfill 
                            {year? : Specific year to backfill}
                            {month? : Specific month to backfill (1-12)}
                            {--recent : Backfill only last month}
                            {--months=1 : Number of months to backfill}
                            {--corporation= : Specific corporation ID}
                            {--all : Backfill all historical data (use with caution)}';
    
    protected $description = 'Backfill wallet data for specific periods';

    public function handle()
    {
        $year = $this->argument('year');
        $month = $this->argument('month');
        $recent = $this->option('recent');
        $months = $this->option('months');
        $corporationId = $this->option('corporation');
        $all = $this->option('all');

        if ($year && $month) {
            $this->info("Backfilling data for {$year}-{$month}...");
            BackfillWalletData::dispatch($corporationId, null, $year, $month);
            
        } elseif ($recent) {
            $this->info("Backfilling last month of data...");
            BackfillWalletData::dispatch($corporationId, 1);
            
        } elseif ($all) {
            if (!$this->confirm('This will process ALL historical data. Continue?')) {
                return;
            }
            $this->info("Backfilling all historical data...");
            BackfillWalletData::dispatch($corporationId, null);
            
        } else {
            $this->info("Backfilling last {$months} month(s) of data...");
            BackfillWalletData::dispatch($corporationId, $months);
        }
        
        $this->info("Backfill job dispatched.");
    }
}
