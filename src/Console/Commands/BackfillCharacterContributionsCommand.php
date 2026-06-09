<?php

namespace CorpWalletManager\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use CorpWalletManager\Models\CharacterContribution;
use CorpWalletManager\Services\ContributionService;
use CorpWalletManager\Support\JournalFilters;

/**
 * Rebuild the per-character contribution cache for the trailing N months.
 *
 * Wipes existing rows for the affected months and replays
 * corporation_wallet_journals through ContributionService::applyJournalBatch
 * per (corporation, period) tuple. Operator sees a progress bar that
 * advances one tick per corp-period so a long replay on a busy install
 * shows steady forward motion instead of going dark for minutes.
 *
 * Use after first install (the hourly job sets a watermark and does not
 * backfill on its own) or after a change to Mining Manager's tax-code
 * configuration that would re-classify historical donations.
 */
class BackfillCharacterContributionsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'corpwalletmanager:backfill-contributions
                            {--months=6 : How many trailing months (including current) to rebuild}';

    /**
     * @var string
     */
    protected $description = 'Rebuild the per-character contribution cache from corporation_wallet_journals';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $months = max(1, (int) $this->option('months'));
        $from   = Carbon::now()->subMonths($months)->startOfMonth();

        // Periods covered by the backfill window (newest first for display).
        $periods = [];
        for ($i = 0; $i < $months; $i++) {
            $periods[] = Carbon::now()->subMonths($i)->format('Y-m');
        }
        // Ascending for the progress sweep.
        $periodsAsc = array_reverse($periods);

        $this->info("Rebuilding character contributions for {$months} month(s): " . implode(', ', $periods));

        $deleted = CharacterContribution::whereIn('period', $periods)->delete();
        $this->info("Cleared {$deleted} existing cache rows for those periods.");

        // Enumerate corporations that have journal rows inside the window.
        // Replay happens per (corp, period); the progress bar ticks once
        // per tuple so an operator sees concrete forward motion.
        $corpIds = DB::table('corporation_wallet_journals')
            ->where('date', '>=', $from)
            ->whereNotNull('corporation_id')
            ->distinct()
            ->pluck('corporation_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        $totalCorps = $corpIds->count();
        if ($totalCorps === 0) {
            $this->info('No corporations with journal rows in the window. Nothing to replay.');
            return 0;
        }

        $totalTuples = $totalCorps * count($periodsAsc);
        $startTs     = microtime(true);

        $bar = $this->output->createProgressBar($totalTuples);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        $service        = app(ContributionService::class);
        $totalScanned   = 0;
        $totalClassified = 0;

        foreach ($corpIds as $corpId) {
            foreach ($periodsAsc as $period) {
                [$yearStr, $monthStr] = explode('-', $period);
                $start = Carbon::createFromDate((int) $yearStr, (int) $monthStr, 1)->startOfMonth();
                $end   = $start->copy()->endOfMonth();

                $bar->setMessage(sprintf('corp %d %s', $corpId, $period));

                $query = DB::table('corporation_wallet_journals')
                    ->where('corporation_id', $corpId)
                    ->whereBetween('date', [$start, $end])
                    ->orderBy('internal_id');
                $query = JournalFilters::excludeInternalTransfers($query, $corpId);

                $query->chunkById(5000, function ($rows) use ($service, &$totalScanned, &$totalClassified) {
                    $totalScanned    += $rows->count();
                    $totalClassified += $service->applyJournalBatch($rows);
                }, 'internal_id');

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Advance the watermark so the hourly compute job picks up from the
        // newest row we've now classified.
        $maxId = (int) DB::table('corporation_wallet_journals')->max('internal_id');
        $service->setWatermark($maxId);

        $elapsed = max(1, (int) round(microtime(true) - $startTs));
        $this->info(sprintf(
            'Backfill complete. Scanned %d journal rows, classified %d into per-character buckets in %ds.',
            $totalScanned,
            $totalClassified,
            $elapsed
        ));
        $this->info("Watermark advanced to internal_id {$maxId}.");

        return 0;
    }
}
