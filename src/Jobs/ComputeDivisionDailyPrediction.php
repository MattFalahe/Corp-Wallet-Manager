<?php
// ComputeDivisionDailyPrediction.php
namespace Seat\CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use Seat\CorpWalletManager\Models\DivisionBalance;
use Seat\CorpWalletManager\Models\DivisionPrediction;
use Seat\CorpWalletManager\Models\RecalcLog;

class ComputeDivisionDailyPrediction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $corporationId;
    protected $divisionId;

    public function __construct($corporationId = null, $divisionId = null)
    {
        $this->corporationId = $corporationId;
        $this->divisionId = $divisionId;
    }

    public function handle()
    {
        $logEntry = RecalcLog::create([
            'job_type' => 'division_prediction',
            'corporation_id' => $this->corporationId,
            'status' => RecalcLog::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        try {
            $processed = 0;

            if ($this->corporationId && $this->divisionId) {
                $processed = $this->computePredictionsForDivision($this->corporationId, $this->divisionId);
            } else {
                // Compute for all corporation/division combinations
                $divisions = DivisionBalance::select('corporation_id', 'division_id')
                    ->distinct()
                    ->get();

                foreach ($divisions as $division) {
                    if (!$this->corporationId || $division->corporation_id == $this->corporationId) {
                        $processed += $this->computePredictionsForDivision(
                            $division->corporation_id, 
                            $division->division_id
                        );
                    }
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

    private function computePredictionsForDivision($corporationId, $divisionId)
    {
        $sixMonthsAgo = Carbon::now()->subMonths(6)->startOfMonth();
        
        $balances = DivisionBalance::where('corporation_id', $corporationId)
            ->where('division_id', $divisionId)
            ->where('month', '>=', $sixMonthsAgo->format('Y-m'))
            ->orderBy('month')
            ->get();

        if ($balances->count() < 2) {
            return 0;
        }

        // Simple prediction based on average and trend
        $values = $balances->pluck('balance')->toArray();
        $avg = array_sum($values) / count($values);
        
        $trend = 0;
        if (count($values) > 1) {
            $recent = array_slice($values, -2);
            $older = array_slice($values, 0, 2);
            $recentAvg = array_sum($recent) / count($recent);
            $olderAvg = array_sum($older) / count($older);
            $trend = ($recentAvg - $olderAvg) / (count($values) - 1);
        }

        // Predict for next 30 days
        $predictions = [];
        $today = Carbon::today();
        
        for ($i = 1; $i <= 30; $i++) {
            $futureDate = $today->copy()->addDays($i);
            $monthsAhead = $today->diffInMonths($futureDate, false);
            
            $predictedBalance = $avg + ($trend * $monthsAhead);
            
            $predictions[] = [
                'corporation_id' => $corporationId,
                'division_id' => $divisionId,
                'date' => $futureDate->format('Y-m-d'),
                'predicted_balance' => max(0, $predictedBalance),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Clear old predictions
        DivisionPrediction::where('corporation_id', $corporationId)
            ->where('division_id', $divisionId)
            ->where('date', '>=', $today->format('Y-m-d'))
            ->delete();

        // Insert new predictions
        DivisionPrediction::insert($predictions);

        return count($predictions);
    }
}
