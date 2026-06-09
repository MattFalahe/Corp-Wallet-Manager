<?php
namespace CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use CorpWalletManager\Models\DivisionBalance;
use CorpWalletManager\Models\RecalcLog;
use CorpWalletManager\Support\JournalFilters;

class BackfillDivisionWalletData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $corporationId;
    public $timeout = 300; // 5 minutes
    public $tries = 3;

    public function __construct($corporationId = null)
    {
        // Defensive: an empty string / 0 / non-numeric value (typical when
        // Settings::getSetting('selected_corporation_id') returns '') would
        // otherwise reach RecalcLog::create() and trip MySQL's BIGINT type
        // check with "Incorrect integer value: '' for column corporation_id".
        $this->corporationId = (is_numeric($corporationId) && (int) $corporationId > 0)
            ? (int) $corporationId
            : null;
    }

    public function tags(): array
    {
        return [
            'corpwalletmanager',
            'backfill',
            'divisions',
            'corp:' . ($this->corporationId ?? 'all'),
        ];
    }

    public function handle()
    {
        $logEntry = null;
        
        try {
            $logEntry = RecalcLog::create([
                'job_type' => 'division_backfill',
                'corporation_id' => $this->corporationId,
                'status' => RecalcLog::STATUS_RUNNING,
                'started_at' => now(),
            ]);

            // SAFETY CHECK: Verify SeAT tables exist
            if (!Schema::hasTable('corporation_wallet_journals')) {
                throw new \Exception('Required SeAT table "corporation_wallet_journals" not found.');
            }

            $query = DB::table('corporation_wallet_journals')
                ->selectRaw('
                    corporation_id,
                    division as division_id,
                    DATE_FORMAT(date, "%Y-%m") as month,
                    SUM(amount) as balance
                ')
                ->whereNotNull('corporation_id')
                ->whereNotNull('division');

            if ($this->corporationId) {
                $query->where('corporation_id', $this->corporationId);
                $query = JournalFilters::excludeInternalTransfers($query, $this->corporationId);
            } else {
                $query = JournalFilters::excludeInternalTransfers($query);
            }

            $query->groupBy('corporation_id', 'division', 'month')
                ->orderBy('corporation_id')
                ->orderBy('division')
                ->orderBy('month');

            $results = $query->get();
            
            if ($results->isEmpty()) {
                $logEntry->update([
                    'status' => RecalcLog::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'records_processed' => 0,
                    'error_message' => 'No division wallet data found to process.',
                ]);
                return;
            }

            $processed = 0;

            foreach ($results as $row) {
                try {
                    DivisionBalance::updateOrCreate(
                        [
                            'corporation_id' => $row->corporation_id,
                            'division_id' => $row->division_id,
                            'month' => $row->month
                        ],
                        ['balance' => (float)($row->balance ?? 0)]
                    );
                    $processed++;
                } catch (\Illuminate\Database\QueryException $e) {
                    // Database-layer failure — fail the job rather than silently
                    // logging against a broken DB.
                    throw $e;
                } catch (\Throwable $e) {
                    Log::warning('BackfillDivisionWalletData: Failed to process record', [
                        'corporation_id' => $row->corporation_id,
                        'division_id' => $row->division_id,
                        'month' => $row->month,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $logEntry->update([
                'status' => RecalcLog::STATUS_COMPLETED,
                'completed_at' => now(),
                'records_processed' => $processed,
            ]);

            Log::info('BackfillDivisionWalletData completed successfully', [
                'corporation_id' => $this->corporationId,
                'records_processed' => $processed
            ]);

        } catch (\Exception $e) {
            if ($logEntry) {
                $logEntry->update([
                    'status' => RecalcLog::STATUS_FAILED,
                    'completed_at' => now(),
                    'error_message' => substr($e->getMessage(), 0, 1000),
                ]);
            }
            
            Log::error('BackfillDivisionWalletData failed', [
                'corporation_id' => $this->corporationId,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    public function failed(\Exception $exception)
    {
        Log::error('BackfillDivisionWalletData job permanently failed', [
            'corporation_id' => $this->corporationId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
