<?php
namespace Seat\CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Seat\CorpWalletManager\Models\MonthlyBalance;
use Seat\CorpWalletManager\Models\Prediction;
use Seat\CorpWalletManager\Models\RecalcLog;

class BackfillWalletData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $corporationId;

    public function __construct($corporationId = null)
    {
        $this->corporationId = $corporationId;
    }

    public function handle()
    {
        $logEntry = RecalcLog::create([
            'job_type' => 'wallet_backfill',
            'corporation_id' => $this->corporationId,
            'status' => RecalcLog::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        try {
            $processed = 0;

            // Build query to group journal entries by corporation and month
            $query = DB::table('corporation_wallet_journals')
                ->selectRaw('
                    corporation_id,
                    DATE_FORMAT(date, "%Y-%m") as month, 
                    SUM(amount) as balance
                ')
                ->groupBy('corporation_id', 'month')
                ->orderBy('corporation_id')
                ->orderBy('month');

            // Filter by corporation if specified
            if ($this->corporationId) {
                $query->where('corporation_id', $this->corporationId);
            }

            $monthly = $query->get();

            // Process each monthly balance record
            foreach ($monthly as $row) {
                MonthlyBalance::updateOrCreate(
                    [
                        'corporation_id' => $row->corporation_id,
                        'month' => $row->month
                    ],
                    ['balance' => $row->balance]
                );
                $processed++;
            }

            // Create basic predictions based on monthly averages per corporation
            $corporationIds = $monthly->pluck('corporation_id')->unique();
            
            foreach ($corporationIds as $corpId) {
                $corpBalances = $monthly->where('corporation_id', $corpId);
                $avg = $corpBalances->avg('balance');
                
                if ($avg !== null) {
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
            }

            $logEntry->update([
                'status' => RecalcLog::STATUS_COMPLETED,
                'completed_at' => now(),
                'records_processed' => $processed,
            ]);

        } catch (\Exception $e) {
            $logEntry->update([
                'status' => RecalcLog::STATUS_FAILED,
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
