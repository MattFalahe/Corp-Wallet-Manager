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
use Seat\CorpWalletManager\Models\MonthlyBalance;
use Seat\CorpWalletManager\Models\Prediction;
use Seat\CorpWalletManager\Models\RecalcLog;

class BackfillWalletData implements ShouldQueue
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
                'job_type' => 'wallet_backfill',
                'corporation_id' => $this->corporationId,
                'status' => RecalcLog::STATUS_RUNNING,
                'started_at' => now(),
            ]);

            // SAFETY CHECK: Verify SeAT tables exist
            if (!Schema::hasTable('corporation_wallet_journals')) {
                throw new \Exception('Required SeAT table "corporation_wallet_journals" not found. Ensure SeAT is properly installed and migrated.');
            }

            $processed = 0;

            // Build query to group journal entries by corporation and month
            $query = DB::table('corporation_wallet_journals')
                ->selectRaw('
                    corporation_id,
                    DATE_FORMAT(date, "%Y-%m") as month, 
                    SUM(amount) as balance
                ')
                ->whereNotNull('corporation_id')
                ->groupBy('corporation_id', 'month')
                ->orderBy('corporation_id')
                ->orderBy('month');

            // Filter by corporation if specified
            if ($this->corporationId) {
                $query->where('corporation_id', $this->corporationId);
            }

            $monthly = $query->get();

            // Check if we have any data
            if ($monthly->isEmpty()) {
                $logEntry->update([
                    'status' => RecalcLog::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'records_processed' => 0,
                    'error_message' => 'No wallet journal data found to process.',
                ]);
                
                Log::info('CorpWalletManager BackfillWalletData: No data found to process', [
                    'corporation_id' => $this->corporationId
                ]);
                
                return;
            }

            // Process each monthly balance record
            foreach ($monthly as $row) {
                try {
                    MonthlyBalance::updateOrCreate(
                        [
                            'corporation_id' => $row->corporation_id,
                            'month' => $row->month
                        ],
                        ['balance' => $row->balance ?? 0]
                    );
                    $processed++;
                } catch (\Exception $e) {
                    Log::warning('CorpWalletManager BackfillWalletData: Failed to process monthly balance', [
                        'corporation_id' => $row->corporation_id,
                        'month' => $row->month,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Create basic predictions based on monthly averages per corporation
            $corporationIds = $monthly->pluck('corporation_id')->unique()->filter();
            
            foreach ($corporationIds as $corpId) {
                try {
                    $corpBalances = $monthly->where('corporation_id', $corpId);
                    if ($corpBalances->isEmpty()) continue;
                    
                    $avg = $corpBalances->avg('balance');
                    
                    if ($avg !== null && is_numeric($avg)) {
                        $nextMonth = now()->addMonth()->startOfMonth();
                        
                        // Create prediction for next month
                        Prediction::updateOrCreate(
                            [
                                'corporation_id' => $corpId,
                                'date' => $nextMonth->format('Y-m-d')
                            ],
                            ['predicted_balance' => $avg]
                        );
                        $processed++;
                    }
                } catch (\Exception $e) {
                    Log::warning('CorpWalletManager BackfillWalletData: Failed to create prediction', [
                        'corporation_id' => $corpId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $logEntry->update([
                'status' => RecalcLog::STATUS_COMPLETED,
                'completed_at' => now(),
                'records_processed' => $processed,
            ]);
            
            Log::info('CorpWalletManager BackfillWalletData completed successfully', [
                'corporation_id' => $this->corporationId,
                'records_processed' => $processed
            ]);

        } catch (\Exception $e) {
            if ($logEntry) {
                $logEntry->update([
                    'status' => RecalcLog::STATUS_FAILED,
                    'completed_at' => now(),
                    'error_message' => substr($e->getMessage(), 0, 1000), // Limit error message length
                ]);
            }
            
            Log::error('CorpWalletManager BackfillWalletData failed', [
                'corporation_id' => $this->corporationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Re-throw to mark job as failed
            throw $e;
        }
    }

    /**
     * The job failed to process.
     */
    public function failed(\Exception $exception)
    {
        Log::error('CorpWalletManager BackfillWalletData job permanently failed', [
            'corporation_id' => $this->corporationId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
