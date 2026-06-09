<?php

namespace CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use CorpWalletManager\Jobs\BackfillCharacterContributions;
use CorpWalletManager\Jobs\BackfillDivisionWalletData;
use CorpWalletManager\Jobs\BackfillWalletData;
use CorpWalletManager\Jobs\ComputeCharacterContributions;
use CorpWalletManager\Jobs\ComputeDailyPrediction;
use CorpWalletManager\Jobs\ComputeDivisionDailyPrediction;
use CorpWalletManager\Jobs\ComputePersonalWalletAggregates;
use CorpWalletManager\Jobs\DailyAggregation;
use CorpWalletManager\Jobs\DetectWalletAlerts;

/**
 * First-install / post-upgrade orchestrator.
 *
 * Runs every CWM cache-populating command in the correct order so
 * an operator does not have to figure out the dependencies between
 * monthly balances, daily aggregates, predictions, contributions,
 * personal-wallet aggregates, milestones, and alerts.
 *
 * Idempotent: every step is an upsert against the same cache
 * tables, so running this twice is safe and just re-populates the
 * same data. The command never touches schedules, settings, or
 * webhooks; it only populates data tables.
 */
class InitializeCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'corpwalletmanager:initialize
                            {--months=12 : How many months of history to backfill}
                            {--days=180 : How many days of daily aggregation history to backfill}
                            {--skip= : Comma-separated step names to skip (wallet,division,daily,predictions,contributions,personal,milestones,alerts)}
                            {--force : Skip the are-you-sure confirmation}
                            {--queue : Dispatch each step as a queued job instead of running synchronously}';

    /**
     * @var string
     */
    protected $description = 'Populate every CWM cache table for the first time, in the correct order';

    /**
     * Valid skip keys. Unknown keys produce a warning but do not fail.
     *
     * @var array<string>
     */
    private const VALID_SKIP_KEYS = [
        'wallet',
        'division',
        'daily',
        'predictions',
        'contributions',
        'personal',
        'milestones',
        'alerts',
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $months = max(1, (int) $this->option('months'));
        $days   = max(1, (int) $this->option('days'));
        $skip   = $this->parseSkip((string) ($this->option('skip') ?? ''));
        $queued = (bool) $this->option('queue');

        // Define every step. Each entry: key, label, runner closure.
        $allSteps = $this->defineSteps($months, $days, $queued);

        // Header.
        $this->newLine();
        $this->line('CWM Initialize');
        $this->line('==============');
        $this->line('This will populate every CWM cache table for the first time. Already-populated');
        $this->line('tables get refreshed. Estimated runtime on a busy install: 10-30 minutes.');
        $this->newLine();
        $this->line('Steps that will run:');
        $included = [];
        $skipped  = [];
        $i = 0;
        foreach ($allSteps as $step) {
            $i++;
            if (in_array($step['key'], $skip, true)) {
                $skipped[] = $step;
                $this->line(sprintf('  %d. (skipped) %s', $i, $step['label']));
            } else {
                $included[] = $step;
                $this->line(sprintf('  %d. %s', $i, $step['label']));
            }
        }
        $this->newLine();

        if (empty($included)) {
            $this->info('Every step was skipped. Nothing to do.');
            return 0;
        }

        // Confirm.
        if (! $this->option('force')) {
            if (! $this->confirm('Continue?', true)) {
                $this->info('Cancelled.');
                return 0;
            }
        }

        // Execute.
        $startTs   = microtime(true);
        $totalRun  = 0;
        $failures  = [];

        foreach ($allSteps as $idx => $step) {
            $position = $idx + 1;
            $total    = count($allSteps);

            if (in_array($step['key'], $skip, true)) {
                continue;
            }

            $this->newLine();
            $header = sprintf('[%d/%d] %s', $position, $total, $step['label']);
            $this->line($header);
            $this->line(str_repeat('-', strlen($header)));

            $stepStart = microtime(true);
            try {
                ($step['runner'])();
                $elapsed = max(1, (int) round(microtime(true) - $stepStart));
                $this->info(sprintf('Done in %ds.', $elapsed));
                $totalRun++;
            } catch (\Throwable $e) {
                $elapsed = max(1, (int) round(microtime(true) - $stepStart));
                $this->error(sprintf('Step %d failed: %s (after %ds)', $position, $e->getMessage(), $elapsed));
                Log::error('CWM Initialize step failed', [
                    'step'  => $step['key'],
                    'label' => $step['label'],
                    'error' => $e->getMessage(),
                ]);
                $failures[] = ['step' => $position, 'label' => $step['label'], 'error' => $e->getMessage()];
                // Continue to the next step rather than aborting init.
            }
        }

        // Summary.
        $this->newLine(2);
        $this->line('CWM Initialize complete.');
        $this->line(sprintf('Steps run: %d', $totalRun));
        $this->line(sprintf('Steps skipped: %d', count($skipped)));

        $totalElapsed = max(1, (int) round(microtime(true) - $startTs));
        $this->line(sprintf('Total elapsed: %s', $this->formatElapsed($totalElapsed)));

        if (! empty($failures)) {
            $this->newLine();
            $this->error(sprintf('%d step(s) failed:', count($failures)));
            foreach ($failures as $f) {
                $this->error(sprintf('  Step %d (%s): %s', $f['step'], $f['label'], $f['error']));
            }
        }

        $this->newLine();
        $this->line('Next: cron will keep these tables warm hourly. Open the director view to verify the data has populated.');
        $this->newLine();

        return empty($failures) ? 0 : 1;
    }

    /**
     * Build the ordered step list. Each runner is a closure so the
     * loop in handle() stays linear and easy to read.
     *
     * @return array<int, array{key: string, label: string, runner: callable}>
     */
    private function defineSteps(int $months, int $days, bool $queued): array
    {
        return [
            [
                'key'   => 'wallet',
                'label' => sprintf('Wallet data backfill (%d months)', $months),
                'runner' => function () use ($months, $queued) {
                    if ($queued) {
                        BackfillWalletData::dispatch(null, $months);
                        $this->line('Queued: ' . BackfillWalletData::class);
                        return;
                    }
                    $exit = Artisan::call('corpwalletmanager:backfill-wallet-data', [
                        '--months' => $months,
                    ], $this->getOutput());
                    if ($exit !== 0) {
                        throw new \RuntimeException('backfill-wallet-data exited with code ' . $exit);
                    }
                },
            ],
            [
                'key'   => 'division',
                'label' => sprintf('Division wallet data backfill (%d months)', $months),
                'runner' => function () use ($months, $queued) {
                    if ($queued) {
                        BackfillDivisionWalletData::dispatch();
                        $this->line('Queued: ' . BackfillDivisionWalletData::class);
                        return;
                    }
                    $exit = Artisan::call('corpwalletmanager:backfill-division-wallet-data', [
                        '--months' => $months,
                    ], $this->getOutput());
                    if ($exit !== 0) {
                        throw new \RuntimeException('backfill-division-wallet-data exited with code ' . $exit);
                    }
                },
            ],
            [
                'key'   => 'daily',
                'label' => sprintf('Daily aggregation rebuild (%d days)', $days),
                'runner' => function () use ($queued) {
                    if ($queued) {
                        dispatch(new DailyAggregation());
                        $this->line('Queued: ' . DailyAggregation::class);
                        return;
                    }
                    // DailyAggregation is a single, terminal job. Run via
                    // Artisan so the operator-facing command surfaces any
                    // info lines it emits.
                    $exit = Artisan::call('corpwalletmanager:daily-aggregation', [], $this->getOutput());
                    if ($exit !== 0) {
                        throw new \RuntimeException('daily-aggregation exited with code ' . $exit);
                    }
                },
            ],
            [
                'key'   => 'predictions',
                'label' => 'Daily prediction computation (corp + division)',
                'runner' => function () use ($queued) {
                    if ($queued) {
                        dispatch(new ComputeDailyPrediction(null));
                        dispatch(new ComputeDivisionDailyPrediction(null));
                        $this->line('Queued: ' . ComputeDailyPrediction::class);
                        $this->line('Queued: ' . ComputeDivisionDailyPrediction::class);
                        return;
                    }
                    $a = Artisan::call('corpwalletmanager:compute-predictions', [], $this->getOutput());
                    if ($a !== 0) {
                        throw new \RuntimeException('compute-predictions exited with code ' . $a);
                    }
                    $b = Artisan::call('corpwalletmanager:compute-division-predictions', [], $this->getOutput());
                    if ($b !== 0) {
                        throw new \RuntimeException('compute-division-predictions exited with code ' . $b);
                    }
                },
            ],
            [
                'key'   => 'contributions',
                'label' => sprintf('Character contribution backfill (%d months)', $months),
                'runner' => function () use ($months, $queued) {
                    if ($queued) {
                        dispatch(new BackfillCharacterContributions($months));
                        $this->line('Queued: ' . BackfillCharacterContributions::class);
                        return;
                    }
                    $exit = Artisan::call('corpwalletmanager:backfill-contributions', [
                        '--months' => $months,
                    ], $this->getOutput());
                    if ($exit !== 0) {
                        throw new \RuntimeException('backfill-contributions exited with code ' . $exit);
                    }
                },
            ],
            [
                'key'   => 'personal',
                'label' => sprintf('Personal wallet aggregates backfill (%d months)', $months),
                'runner' => function () use ($months, $queued) {
                    if ($queued) {
                        dispatch(new ComputePersonalWalletAggregates($months));
                        $this->line('Queued: ' . ComputePersonalWalletAggregates::class);
                        return;
                    }
                    $exit = Artisan::call('corpwalletmanager:compute-personal-wallet-aggregates', [
                        '--backfill' => $months,
                    ], $this->getOutput());
                    if ($exit !== 0) {
                        throw new \RuntimeException('compute-personal-wallet-aggregates exited with code ' . $exit);
                    }
                },
            ],
            [
                'key'   => 'milestones',
                'label' => 'Milestone state recompute (via classifier sweep)',
                'runner' => function () use ($queued) {
                    // MemberMilestoneNotifier publishes edge-transition
                    // events at the end of every un-capped
                    // ComputeCharacterContributions run; firing the
                    // incremental classifier sweep is the canonical
                    // milestone re-arm path.
                    if ($queued) {
                        dispatch(new ComputeCharacterContributions());
                        $this->line('Queued: ' . ComputeCharacterContributions::class);
                        return;
                    }
                    $exit = Artisan::call('corpwalletmanager:compute-contributions', [], $this->getOutput());
                    if ($exit !== 0) {
                        throw new \RuntimeException('compute-contributions exited with code ' . $exit);
                    }
                },
            ],
            [
                'key'   => 'alerts',
                'label' => 'Wallet alert detection',
                'runner' => function () use ($queued) {
                    if ($queued) {
                        dispatch(new DetectWalletAlerts());
                        $this->line('Queued: ' . DetectWalletAlerts::class);
                        return;
                    }
                    $exit = Artisan::call('corpwalletmanager:detect-alerts', [], $this->getOutput());
                    if ($exit !== 0) {
                        throw new \RuntimeException('detect-alerts exited with code ' . $exit);
                    }
                },
            ],
        ];
    }

    /**
     * Parse the comma-separated --skip option. Case-insensitive.
     * Unknown keys produce a warning but do not fail.
     *
     * @return array<int, string>
     */
    private function parseSkip(string $raw): array
    {
        $tokens = array_filter(array_map('trim', explode(',', strtolower($raw))));
        if (empty($tokens)) {
            return [];
        }
        $kept    = [];
        $unknown = [];
        foreach ($tokens as $tok) {
            if (in_array($tok, self::VALID_SKIP_KEYS, true)) {
                $kept[] = $tok;
            } else {
                $unknown[] = $tok;
            }
        }
        if (! empty($unknown)) {
            $this->warn('Unknown skip key(s) ignored: ' . implode(', ', $unknown));
            $this->line('Valid keys: ' . implode(', ', self::VALID_SKIP_KEYS));
        }
        return array_values(array_unique($kept));
    }

    /**
     * Render an elapsed-second count as "12m 34s" / "5s".
     */
    private function formatElapsed(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        $m = intdiv($seconds, 60);
        $s = $seconds % 60;
        return sprintf('%dm %ds', $m, $s);
    }
}
