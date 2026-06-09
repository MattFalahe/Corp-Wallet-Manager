<?php

namespace CorpWalletManager\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use CorpWalletManager\Jobs\BackfillDivisionWalletData;
use CorpWalletManager\Models\DivisionBalance;
use CorpWalletManager\Models\RecalcLog;
use CorpWalletManager\Support\JournalFilters;

/**
 * Operator-facing wrapper around the per-division monthly backfill.
 *
 * Synchronous (default): runs the same aggregation the queued
 * BackfillDivisionWalletData job performs but in-process, with a
 * progress bar that advances one tick per (corporation, division,
 * month) upsert. Pick this when you want immediate feedback after
 * enabling division tracking on a corp or after an upgrade.
 *
 * --queue dispatches the legacy BackfillDivisionWalletData job so
 * the artisan call returns immediately and a worker picks it up.
 */
class BackfillDivisionWalletDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'corpwalletmanager:backfill-division-wallet-data
                            {year? : Specific year to backfill}
                            {month? : Specific month to backfill (1-12)}
                            {--recent : Backfill only last month}
                            {--months=1 : Number of months to backfill}
                            {--corporation= : Specific corporation ID}
                            {--all : Backfill all historical data (use with caution)}
                            {--queue : Dispatch as a queued job instead of running synchronously}';

    /**
     * Aliases keep the legacy artisan signature working.
     *
     * @var array<string>
     */
    protected $aliases = ['corpwalletmanager:backfill-divisions'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill per-division monthly wallet balances for specific periods';

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

        $corporationId = (is_numeric($corporationId) && (int) $corporationId > 0)
            ? (int) $corporationId
            : null;

        if ($all && ! $this->option('queue')) {
            if (! $this->confirm('This will process ALL historical division data. Continue?', false)) {
                return 0;
            }
        }

        if ($this->option('queue')) {
            BackfillDivisionWalletData::dispatch($corporationId);
            $this->info('Queued.');
            return 0;
        }

        if (! Schema::hasTable('corporation_wallet_journals')) {
            $this->error('Required SeAT table "corporation_wallet_journals" not found.');
            return 1;
        }

        $logEntry = RecalcLog::create([
            'job_type'       => 'division_backfill',
            'corporation_id' => $corporationId,
            'status'         => RecalcLog::STATUS_RUNNING,
            'started_at'     => now(),
        ]);

        $startTs = microtime(true);

        try {
            $query = DB::table('corporation_wallet_journals')
                ->selectRaw('
                    corporation_id,
                    division as division_id,
                    DATE_FORMAT(date, "%Y-%m") as month,
                    SUM(amount) as balance
                ')
                ->whereNotNull('corporation_id')
                ->whereNotNull('division');

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
                ->groupBy('corporation_id', 'division', 'month')
                ->orderBy('corporation_id')
                ->orderBy('division')
                ->orderBy('month')
                ->get();

            $total = $rows->count();

            if ($total === 0) {
                $this->info('No (corporation, division, month) tuples in scope. Nothing to do.');
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
                $label = sprintf('corp %d div %d %s', $row->corporation_id, $row->division_id, $row->month);
                $bar->setMessage($label);

                try {
                    DivisionBalance::updateOrCreate(
                        [
                            'corporation_id' => $row->corporation_id,
                            'division_id'    => $row->division_id,
                            'month'          => $row->month,
                        ],
                        ['balance' => (float) ($row->balance ?? 0)]
                    );
                    $processed++;
                } catch (\Illuminate\Database\QueryException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    Log::warning('BackfillDivisionWalletDataCommand: Failed to process record', [
                        'corporation_id' => $row->corporation_id,
                        'division_id'    => $row->division_id,
                        'month'          => $row->month,
                        'error'          => $e->getMessage(),
                    ]);
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            $logEntry->update([
                'status'            => RecalcLog::STATUS_COMPLETED,
                'completed_at'      => now(),
                'records_processed' => $processed,
            ]);

            $elapsed = max(1, (int) round(microtime(true) - $startTs));
            $this->info(sprintf(
                'Done. %d (corporation, division, month) tuple(s) updated in %ds.',
                $processed,
                $elapsed
            ));

            return 0;
        } catch (\Throwable $e) {
            $logEntry->update([
                'status'        => RecalcLog::STATUS_FAILED,
                'completed_at'  => now(),
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]);
            Log::error('BackfillDivisionWalletDataCommand failed', [
                'corporation_id' => $corporationId,
                'error'          => $e->getMessage(),
            ]);
            $this->error('Division backfill failed: ' . $e->getMessage());
            return 1;
        }
    }
}
