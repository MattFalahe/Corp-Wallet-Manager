<?php
// BackfillDivisionWalletData.php
namespace Seat\CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Seat\CorpWalletManager\Models\DivisionBalance;
use Seat\CorpWalletManager\Models\RecalcLog;

class BackfillDivisionWalletData implements ShouldQueue
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
            'job_type' => 'division_backfill',
            'corporation_id' => $this->corporationId,
            'status' => RecalcLog::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        try {
            $query = DB::table('corporation_wallet_journals')
                ->selectRaw('
                    corporation_id,
                    wallet_division as division_id,
                    DATE_FORMAT(date, "%Y-%m") as month, 
                    SUM(amount) as balance
                ')
                ->groupBy('corporation_id', 'wallet_division', 'month')
                ->orderBy('corporation_id')
                ->orderBy('wallet_division')
                ->orderBy('month');

            if ($this->corporationId) {
                $query->where('corporation_id', $this->corporationId);
            }

            $results = $query->get();
            $processed = 0;

            foreach ($results as $row) {
                DivisionBalance::updateOrCreate(
                    [
                        'corporation_id' => $row->corporation_id,
                        'division_id' => $row->division_id,
                        'month' => $row->month
                    ],
                    ['balance' => $row->balance]
                );
                $processed++;
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
