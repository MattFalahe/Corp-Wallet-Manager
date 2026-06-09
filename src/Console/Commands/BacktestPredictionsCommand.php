<?php

namespace CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use CorpWalletManager\Jobs\BacktestPredictions;

class BacktestPredictionsCommand extends Command
{
    protected $signature = 'corpwalletmanager:backtest {--corporation= : Specific corporation ID to backtest}';
    protected $description = 'Backtest stored predictions against actual balances; stores MAPE/bias into prediction_metrics';

    public function handle(): int
    {
        $corporationId = $this->option('corporation');
        $corporationId = $corporationId !== null ? (int) $corporationId : null;

        $this->info($corporationId
            ? "Dispatching backtest for corporation {$corporationId}..."
            : 'Dispatching backtest for all corporations with predictions...');

        dispatch(new BacktestPredictions($corporationId));

        $this->info('Backtest job dispatched.');
        return 0;
    }
}
