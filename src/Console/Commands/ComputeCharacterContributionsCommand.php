<?php

namespace CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use CorpWalletManager\Jobs\ComputeCharacterContributions;

class ComputeCharacterContributionsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'corpwalletmanager:compute-contributions';

    /**
     * @var string
     */
    protected $description = 'Run the incremental per-character contribution cache updater';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        dispatch(new ComputeCharacterContributions());

        $this->info('Compute-contributions job dispatched.');

        return 0;
    }
}
