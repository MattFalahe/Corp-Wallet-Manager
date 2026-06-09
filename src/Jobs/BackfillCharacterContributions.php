<?php

namespace CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * UI-triggered wrapper around the corpwalletmanager:backfill-contributions
 * artisan command.
 *
 * The command can take a while on busy corps (chunked scan across N
 * months of journal rows), so running it inline from a Settings page
 * controller risks the web request timing out. Dispatching this job
 * pushes the work onto the queue and returns immediately. Operators
 * can refresh Top Contributors / Alliance Tax tabs a few minutes
 * later.
 */
class BackfillCharacterContributions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Long timeout — backfill can chunk through millions of journal rows. */
    public $timeout = 1800;

    /** @var int */
    public $tries = 1;

    private int $months;

    public function __construct(int $months = 6)
    {
        $this->months = max(1, min(36, $months));
    }

    public function tags(): array
    {
        return ['corpwalletmanager', 'contributions', 'backfill'];
    }

    public function handle(): void
    {
        Log::info('BackfillCharacterContributions job started', [
            'months' => $this->months,
        ]);

        $exitCode = Artisan::call('corpwalletmanager:backfill-contributions', [
            '--months' => $this->months,
        ]);

        Log::info('BackfillCharacterContributions job complete', [
            'months'    => $this->months,
            'exit_code' => $exitCode,
            'output'    => Artisan::output(),
        ]);
    }
}
