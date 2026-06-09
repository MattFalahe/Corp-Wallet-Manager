<?php

namespace CorpWalletManager\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use CorpWalletManager\Jobs\ComputePersonalWalletAggregates;
use CorpWalletManager\Services\PersonalWalletAggregator;

/**
 * Operator-facing wrapper around ComputePersonalWalletAggregates.
 *
 * Sync mode (default) runs the aggregator in-process so the artisan
 * call returns when the work is finished and any errors are visible
 * in the operator's terminal. A progress bar advances one tick per
 * (character, period) tuple so a backfill across hundreds of
 * characters and twelve months shows steady forward motion.
 *
 * --queue dispatches the job to the normal queue instead. The bar
 * is skipped on the queued path because the artisan call returns
 * immediately and the work runs out-of-process.
 *
 * --backfill=N is the one-shot the operator runs after upgrading
 * (or after an extended outage) to populate the aggregate table
 * with the trailing N months of history. With --backfill omitted
 * the command behaves identically to the hourly cron: refresh the
 * current month plus any prior month with new journal rows.
 *
 * --character=X scopes the run to a single character; used for
 * debugging a specific tab open that is rendering unexpectedly.
 */
class ComputePersonalWalletAggregatesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'corpwalletmanager:compute-personal-wallet-aggregates
                            {--character= : Limit to a single character_id (debug)}
                            {--backfill= : Rebuild the trailing N months unconditionally}
                            {--queue : Dispatch as a queued job instead of running synchronously}';

    /**
     * @var string
     */
    protected $description = 'Build the per-character personal-wallet aggregate that backs the My Personal Wallet tab';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $characterOpt = $this->option('character');
        $backfillOpt  = $this->option('backfill');

        $characterId    = ($characterOpt === null || $characterOpt === '') ? null : (int) $characterOpt;
        $backfillMonths = ($backfillOpt === null || $backfillOpt === '') ? null : max(1, (int) $backfillOpt);

        // Queued path: dispatch and bail. No progress bar (the work is
        // out-of-process; the operator can watch Horizon / queue logs).
        if ($this->option('queue')) {
            dispatch(new ComputePersonalWalletAggregates($backfillMonths, $characterId));
            $this->info('Queued.');
            return 0;
        }

        $aggregator = app(PersonalWalletAggregator::class);
        $startTs    = microtime(true);

        // Single-character debug path. Cheap; small inline progress.
        if ($characterId !== null && $characterId > 0) {
            $this->info("Aggregating personal wallet for character {$characterId}" .
                ($backfillMonths !== null ? " (backfill={$backfillMonths} months)" : ' (current + dirty prior periods)') . '.');

            $periods = $this->periodsForCharacter($aggregator, $characterId, $backfillMonths);
            $total   = count($periods);

            if ($total === 0) {
                $this->info('Nothing to recompute.');
                return 0;
            }

            $bar = $this->output->createProgressBar($total);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%');
            $bar->setMessage('Starting...');
            $bar->start();

            $written = 0;
            foreach ($periods as $period) {
                $bar->setMessage(sprintf('char %d %s', $characterId, $period));
                try {
                    if ($aggregator->aggregateOnePeriod($characterId, $period)) {
                        $written++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('ComputePersonalWalletAggregatesCommand: character-period failed', [
                        'character_id' => $characterId,
                        'period'       => $period,
                        'error'        => $e->getMessage(),
                    ]);
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $elapsed = max(1, (int) round(microtime(true) - $startTs));
            $this->info(sprintf('Done. Wrote %d aggregate row(s) in %ds.', $written, $elapsed));
            return 0;
        }

        // All-characters path. Resolve the character set first so the
        // bar has a real total; one tick per (character, period) tuple.
        $characterIds = DB::table('refresh_tokens')
            ->whereNull('deleted_at')
            ->pluck('character_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id >= 90000000)
            ->unique()
            ->values();

        $totalChars = $characterIds->count();
        if ($totalChars === 0) {
            $this->info('No characters with active refresh tokens. Nothing to aggregate.');
            return 0;
        }

        // For an explicit backfill the period set is identical per
        // character (the trailing N months), so the total is exact.
        // For the steady-state path we still walk every character but
        // the per-character period count varies; use the upper bound
        // (current month + every prior recorded period) so the bar
        // never overflows. Practical operator UX: backfills get a
        // tight bar, steady-state gets an "advancing fast" bar.
        if ($backfillMonths !== null) {
            $totalTuples = $totalChars * $backfillMonths;
        } else {
            // Worst-case: current month per character. Steady-state
            // typically only recomputes the current month plus a
            // handful of dirty priors per character, so this is a
            // tight upper bound.
            $totalTuples = $totalChars;
        }

        $this->info('Aggregating personal wallet for every character with a refresh token' .
            ($backfillMonths !== null ? " (backfill={$backfillMonths} months)" : ' (current + dirty prior periods)') . '.');

        $bar = $this->output->createProgressBar(max(1, $totalTuples));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        $totalWritten = 0;
        $errors       = 0;
        $advanced     = 0;

        foreach ($characterIds as $characterId) {
            $periods = $this->periodsForCharacter($aggregator, $characterId, $backfillMonths);

            foreach ($periods as $period) {
                $bar->setMessage(sprintf('char %d %s', $characterId, $period));
                try {
                    if ($aggregator->aggregateOnePeriod($characterId, $period)) {
                        $totalWritten++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    Log::warning('ComputePersonalWalletAggregatesCommand: character-period failed', [
                        'character_id' => $characterId,
                        'period'       => $period,
                        'error'        => $e->getMessage(),
                    ]);
                }
                $bar->advance();
                $advanced++;
            }

            // If the steady-state branch's tight upper bound left
            // remaining ticks (e.g. we touched extra dirty priors),
            // catch up so the bar still finishes cleanly.
            if ($advanced > $totalTuples) {
                $bar->setMaxSteps($advanced);
            }
        }

        // Ensure the bar closes at 100% even when steady-state advanced
        // fewer or more times than the upper-bound estimate.
        if ($advanced < $totalTuples) {
            $bar->setMaxSteps($advanced);
        }

        $bar->finish();
        $this->newLine(2);

        $elapsed = max(1, (int) round(microtime(true) - $startTs));
        $this->info(sprintf(
            'Done. Processed %d character(s), wrote %d aggregate row(s), %d error(s) in %ds.',
            $totalChars,
            $totalWritten,
            $errors,
            $elapsed
        ));

        return 0;
    }

    /**
     * Resolve the period set to recompute for one character.
     *
     * For an explicit --backfill=N we replay the trailing N months
     * unconditionally. For the steady-state path we ask the
     * aggregator which periods are dirty (current month always,
     * plus any prior period whose watermark is behind the journal's
     * current MAX(id) for that period).
     *
     * @return array<int,string>
     */
    private function periodsForCharacter(PersonalWalletAggregator $aggregator, int $characterId, ?int $backfillMonths): array
    {
        if ($backfillMonths !== null && $backfillMonths > 0) {
            $periods = [];
            for ($i = 0; $i < $backfillMonths; $i++) {
                $periods[] = Carbon::now()->subMonthsNoOverflow($i)->format('Y-m');
            }
            return $periods;
        }

        return $aggregator->periodsToRecompute($characterId);
    }
}
