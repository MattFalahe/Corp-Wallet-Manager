<?php
namespace CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use CorpWalletManager\Models\RecalcLog;
use CorpWalletManager\Services\BacktestService;

class BacktestPredictions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected ?int $corporationId;

    public $timeout = 300;
    public $tries = 3;

    public function __construct(?int $corporationId = null)
    {
        $this->corporationId = $corporationId;
    }

    public function tags(): array
    {
        return [
            'corpwalletmanager',
            'backtest',
            'corp:' . ($this->corporationId ?? 'all'),
        ];
    }

    public function handle(BacktestService $backtest): void
    {
        $logEntry = null;

        try {
            $logEntry = RecalcLog::create([
                'job_type' => 'backtest',
                'corporation_id' => $this->corporationId,
                'status' => RecalcLog::STATUS_RUNNING,
                'started_at' => now(),
            ]);

            $corpIds = $this->corporationId !== null
                ? [$this->corporationId]
                : DB::table('corpwalletmanager_predictions')->distinct()->pluck('corporation_id')->all();

            $processed = 0;
            foreach ($corpIds as $corpId) {
                try {
                    $result = $backtest->runForCorporation((int) $corpId);
                    if ($result !== null) {
                        $processed++;
                    }
                } catch (\Illuminate\Database\QueryException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    Log::warning('BacktestPredictions: failed for corporation', [
                        'corporation_id' => $corpId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $logEntry->update([
                'status' => RecalcLog::STATUS_COMPLETED,
                'completed_at' => now(),
                'records_processed' => $processed,
            ]);
        } catch (\Throwable $e) {
            if ($logEntry) {
                $logEntry->update([
                    'status' => RecalcLog::STATUS_FAILED,
                    'completed_at' => now(),
                    'error_message' => substr($e->getMessage(), 0, 1000),
                ]);
            }
            Log::error('BacktestPredictions failed', [
                'corporation_id' => $this->corporationId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
