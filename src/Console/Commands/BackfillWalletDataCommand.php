<?php

namespace CorpWalletManager\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use CorpWalletManager\Jobs\BackfillWalletData;
use CorpWalletManager\Models\MonthlyBalance;
use CorpWalletManager\Models\Prediction;
use CorpWalletManager\Models\RecalcLog;
use CorpWalletManager\Support\JournalFilters;

/**
 * Operator-facing wrapper around the monthly-balance backfill.
 *
 * Synchronous (default): runs the same aggregation the queued
 * BackfillWalletData job performs but in-process so the operator
 * sees a progress bar advancing one tick per corp + month upsert.
 * Pick this when you want immediate feedback during first install
 * or after an upgrade.
 *
 * --queue dispatches the legacy BackfillWalletData job for
 * background runs. Useful when the operator wants the artisan call
 * to return immediately and let the worker pick the job up.
 *
 * Either path is idempotent: the underlying MonthlyBalance and
 * Prediction upserts replay the same data without duplication.
 */
class BackfillWalletDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'corpwalletmanager:backfill-wallet-data
                            {year? : Specific year to backfill}
                            {month? : Specific month to backfill (1-12)}
                            {--recent : Backfill only last month}
                            {--months=1 : Number of months to backfill}
                            {--corporation= : Specific corporation ID}
                            {--all : Backfill all historical data (use with caution)}
                            {--queue : Dispatch as a queued job instead of running synchronously}';

    /**
     * Aliases keep the legacy artisan signature working so cron jobs,
     * shell scripts, and operator muscle memory all keep firing.
     *
     * @var array<string>
     */
    protected $aliases = ['corpwalletmanager:backfill'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill monthly wallet balances for specific periods';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $year          = $this->argument('year');
        $month         = $this->argument('month');
        $recent        = (bool) $this->option('recent');
        $monthsOpt     = (int) $this->option('months');
        $corporationId = $this->option('corporation');
        $all           = (bool) $this->option('all');

        // Defensive cast (settings can yield empty strings).
        $corporationId = (is_numeric($corporationId) && (int) $corporationId > 0)
            ? (int) $corporationId
            : null;

        if ($all && ! $this->option('queue')) {
            if (! $this->confirm('This will process ALL historical data. Continue?', false)) {
                return 0;
            }
        }

        if ($this->option('queue')) {
            // Legacy queued path: dispatch the job and return.
            if ($year && $month) {
                BackfillWalletData::dispatch($corporationId, null, (int) $year, (int) $month);
            } elseif ($recent) {
                BackfillWalletData::dispatch($corporationId, 1);
            } elseif ($all) {
                BackfillWalletData::dispatch($corporationId, null);
            } else {
                BackfillWalletData::dispatch($corporationId, $monthsOpt);
            }
            $this->info('Queued.');
            return 0;
        }

        // Synchronous path with a progress bar. One tick per
        // (corporation, month) upsert.
        if (! Schema::hasTable('corporation_wallet_journals')) {
            $this->error('Required SeAT table "corporation_wallet_journals" not found.');
            return 1;
        }

        $logEntry = RecalcLog::create([
            'job_type'       => 'wallet_backfill',
            'corporation_id' => $corporationId,
            'status'         => RecalcLog::STATUS_RUNNING,
            'started_at'     => now(),
        ]);

        $startTs = microtime(true);

        try {
            $query = DB::table('corporation_wallet_journals')
                ->selectRaw('
                    corporation_id,
                    DATE_FORMAT(date, "%Y-%m") as month,
                    SUM(amount) as balance
                ')
                ->whereNotNull('corporation_id');

            if ($year && $month) {
                $startDate = Carbon::create((int) $year, (int) $month, 1)->startOfMonth();
                $endDate   = $startDate->copy()->endOfMonth();
                $query->whereBetween('date', [$startDate, $endDate]);
            } elseif ($recent) {
                $query->where('date', '>=', Carbon::now()->subMonth()->startOfMonth());
            } elseif ($all) {
                // No date filter.
            } else {
                $query->where('date', '>=', Carbon::now()->subMonths(max(1, $monthsOpt))->startOfMonth());
            }

            if ($corporationId) {
                $query->where('corporation_id', $corporationId);
                $query = JournalFilters::excludeInternalTransfers($query, $corporationId);
            } else {
                $query = JournalFilters::excludeInternalTransfers($query);
            }

            $rows = $query
                ->groupBy('corporation_id', 'month')
                ->orderBy('corporation_id')
                ->orderBy('month')
                ->get();

            $total = $rows->count();

            if ($total === 0) {
                $this->info('No (corporation, month) tuples in scope. Nothing to do.');
                $logEntry->update([
                    'status'            => RecalcLog::STATUS_COMPLETED,
                    'completed_at'      => now(),
                    'records_processed' => 0,
                ]);
                return 0;
            }

            $bar = $this->output->createProgressBar($total);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %message%');
            $bar->setMessage('Starting...');
            $bar->start();

            $processed = 0;
            foreach ($rows as $row) {
                $label = (string) $row->corporation_id . ' ' . (string) $row->month;
                $bar->setMessage($label);

                try {
                    MonthlyBalance::updateOrCreate(
                        [
                            'corporation_id' => $row->corporation_id,
                            'month'          => $row->month,
                        ],
                        ['balance' => $row->balance ?? 0]
                    );
                    $processed++;
                } catch (\Illuminate\Database\QueryException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    Log::warning('BackfillWalletDataCommand: Failed to process monthly balance', [
                        'corporation_id' => $row->corporation_id,
                        'month'          => $row->month,
                        'error'          => $e->getMessage(),
                    ]);
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            // Predictions (cheap, no per-row progress).
            $this->info('Recomputing next-month predictions...');
            $threeMonthsAgo = Carbon::now()->subMonths(3)->startOfMonth();
            $recentCorporations = MonthlyBalance::where('month', '>=', $threeMonthsAgo->format('Y-m'))
                ->distinct('corporation_id')
                ->pluck('corporation_id');

            $predicted = 0;
            foreach ($recentCorporations as $corpId) {
                try {
                    $corpBalances = MonthlyBalance::where('corporation_id', $corpId)
                        ->where('month', '>=', $threeMonthsAgo->format('Y-m'))
                        ->orderBy('month')
                        ->get();

                    if ($corpBalances->count() < 2) {
                        continue;
                    }

                    $avg = $corpBalances->avg('balance');
                    if ($avg !== null && is_numeric($avg)) {
                        $nextMonth = now()->addMonth()->startOfMonth();
                        Prediction::updateOrCreate(
                            [
                                'corporation_id' => $corpId,
                                'date'           => $nextMonth->format('Y-m-d'),
                            ],
                            ['predicted_balance' => $avg]
                        );
                        $predicted++;
                    }
                } catch (\Throwable $e) {
                    Log::warning('BackfillWalletDataCommand: Failed to create prediction', [
                        'corporation_id' => $corpId,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }

            $logEntry->update([
                'status'            => RecalcLog::STATUS_COMPLETED,
                'completed_at'      => now(),
                'records_processed' => $processed,
            ]);

            $elapsed = max(1, (int) round(microtime(true) - $startTs));
            $this->info(sprintf(
                'Done. %d (corporation, month) tuple(s) updated, %d prediction(s) refreshed in %ds.',
                $processed,
                $predicted,
                $elapsed
            ));

            return 0;
        } catch (\Throwable $e) {
            $logEntry->update([
                'status'        => RecalcLog::STATUS_FAILED,
                'completed_at'  => now(),
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]);
            Log::error('BackfillWalletDataCommand failed', [
                'corporation_id' => $corporationId,
                'error'          => $e->getMessage(),
            ]);
            $this->error('Backfill failed: ' . $e->getMessage());
            return 1;
        }
    }
}
