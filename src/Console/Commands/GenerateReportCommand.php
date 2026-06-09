<?php

namespace CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use CorpWalletManager\Jobs\GenerateReport;
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
                            {--period=monthly : Report period (monthly, weekly, daily)}
                            {--from= : Start date (Y-m-d format)}
                            {--to= : End date (Y-m-d format)}
                            {--annual : Generate annual summary for the given year (defaults to current year)}
                            {--quarterly : Generate quarterly summary; requires --year and --quarter}
                            {--year= : Calendar year for --annual / --quarterly (defaults to current year)}
                            {--quarter= : Quarter number 1-4 for --quarterly}';
    
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

        // --annual / --quarterly are higher-priority shortcuts that
        // override the legacy --period switch and set the report type
        // dispatched to the GenerateReport job. Both default the year
        // to the current calendar year when unspecified; --quarterly
        // requires --quarter (1-4).
        if ($this->option('annual')) {
            $year = (int) ($this->option('year') ?: Carbon::now()->year);
            $from = Carbon::create($year, 1, 1)->startOfDay();
            $to   = Carbon::create($year, 12, 31)->endOfDay();
            $period = 'annual';
        } elseif ($this->option('quarterly')) {
            $year = (int) ($this->option('year') ?: Carbon::now()->year);
            $quarter = (int) $this->option('quarter');
            if ($quarter < 1 || $quarter > 4) {
                $this->error('--quarterly requires --quarter=1..4');
                return 1;
            }
            $startMonth = (($quarter - 1) * 3) + 1;
            $from = Carbon::create($year, $startMonth, 1)->startOfDay();
            $to   = $from->copy()->addMonths(3)->subDay()->endOfDay();
            $period = 'quarterly';
        } elseif (! $from || ! $to) {
            // Calculate date range based on period if not provided
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

        // Resolve which corporations to report on. With no --corporation we
        // fan out one job per corporation that has wallet data, so each
        // corp gets its own (non-empty) report and its own webhook routing
        // instead of a single corp-less job that would query for NULL.
        $corporationIds = $corporationId
            ? [(int) $corporationId]
            : $this->corporationsWithWalletData();

        if (empty($corporationIds)) {
            $this->warn('No corporations with wallet data found - nothing to report.');
            return 0;
        }

        $this->info("Generating {$period} report(s) for " . count($corporationIds) . ' corporation(s)...');
        $this->info("Period: {$from->toDateString()} to {$to->toDateString()}");

        // Pass Carbon instances directly — the job's dateFrom()/dateTo()
        // getters normalize either way, but Carbon avoids a string round-trip.
        // sendToDiscord = true: scheduled reports deliver to every webhook
        // subscribed to this corp + report category. The webhook rows (and
        // their is_enabled flag) decide which channels actually fire.
        foreach ($corporationIds as $cid) {
            dispatch(new GenerateReport($cid, $period, $from, $to, [], true));
            $this->info("Dispatched {$period} report for corporation {$cid}.");
        }

        return 0;
    }

    /**
     * Distinct corporation IDs that have at least one wallet journal row.
     *
     * @return array<int, int>
     */
    private function corporationsWithWalletData(): array
    {
        return DB::table('corporation_wallet_journals')
            ->select('corporation_id')
            ->distinct()
            ->pluck('corporation_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
    }
}
