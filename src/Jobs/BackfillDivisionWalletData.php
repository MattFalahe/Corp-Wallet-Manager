<?php
namespace Seat\CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Seat\CorpWalletManager\Models\DivisionBalance;
use Seat\CorpWalletManager\Models\RecalcLog;

class BackfillDivisionWalletData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $corporationId;
    public $timeout = 300; // 5 minutes
    public $tries = 3;

    public function __construct($corporationId = null)
    {
        $this->corporationId = $corporationId;
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
                    wallet_division as division_id,
                    DATE_FORMAT(date, "%Y-%m") as month, 
                    SUM(amount) as balance
                ')
                ->whereNotNull('corporation_id')
                ->whereNotNull('wallet_division')
                ->groupBy('corporation_id', 'wallet_division', 'month')
                ->orderBy('corporation_id')
                ->orderBy('wallet_division')
                ->orderBy('month');

            if ($this->corporationId) {
                $query->where('corporation_id', $this->corporationId);
            }

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
                } catch (\Exception $e) {
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
