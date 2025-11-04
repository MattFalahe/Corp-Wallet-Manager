<?php

namespace Seat\CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use Seat\CorpWalletManager\Jobs\GenerateReport;
use Carbon\Carbon;

class GenerateReportCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'corpwalletmanager:generate-report 
                            {--corporation= : Specific corporation ID to generate report for}
                            {--period=monthly : Report period (monthly, weekly)}
                            {--from= : Start date (Y-m-d format)}
                            {--to= : End date (Y-m-d format)}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate periodic wallet reports';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $corporationId = $this->option('corporation');
        $period = $this->option('period');
        $from = $this->option('from');
        $to = $this->option('to');

        // Calculate date range based on period if not provided
        if (!$from || !$to) {
            switch ($period) {
                case 'weekly':
                    $to = Carbon::now();
                    $from = Carbon::now()->subWeek();
                    break;
                case 'daily':
                    $to = Carbon::now();
                    $from = Carbon::yesterday();
                    break;
                case 'monthly':
                default:
                    $to = Carbon::now();
                    $from = Carbon::now()->subMonth();
                    break;
            }
        } else {
            $from = Carbon::parse($from);
            $to = Carbon::parse($to);
        }

        if ($corporationId) {
            $this->info("Generating {$period} report for corporation {$corporationId}...");
            $this->info("Period: {$from->toDateString()} to {$to->toDateString()}");
        } else {
            $this->info("Generating {$period} reports for all corporations...");
            $this->info("Period: {$from->toDateString()} to {$to->toDateString()}");
        }
        
        // Dispatch the job with proper parameters
        dispatch(new GenerateReport(
            $corporationId,
            $period,
            $from->toDateTimeString(),
            $to->toDateTimeString()
        ));
        
        $this->info('Report generation job dispatched.');
        
        return 0;
    }
}
