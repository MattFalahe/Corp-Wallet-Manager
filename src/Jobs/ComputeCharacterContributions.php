<?php

namespace CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use CorpWalletManager\Services\ContributionService;

/**
 * Hourly per-character contribution cache updater.
 *
 * Incremental scan of corporation_wallet_journals by the monotonic
 * internal_id surrogate, classifying each new row into a per-character
 * bucket and atomically incrementing the cache row in
 * corpwalletmanager_character_contributions.
 *
 * First run silently adopts the current high-water mark so enabling this
 * never replays journal history. Operators wanting historical leaderboards
 * run `corpwalletmanager:backfill-contributions --months=N` once after
 * upgrading.
 */
class ComputeCharacterContributions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public $timeout = 300;

    /** @var int */
    public $tries = 1;

    /** Max journal rows handled per run; the rest roll forward to the next run. */
    private const BATCH_CAP = 10000;

    public function tags(): array
    {
        return ['corpwalletmanager', 'contributions'];
    }

    public function handle(): void
    {
        $service = app(ContributionService::class);
        $watermark = $service->getWatermark();

        // First run: adopt the current high-water mark and instruct the
        // operator to backfill if they want historical leaderboards.
        if ($watermark === null) {
            $max = (int) DB::table('corporation_wallet_journals')->max('internal_id');
            $service->setWatermark($max);
            Log::info('ComputeCharacterContributions: first run, watermark initialised', [
                'watermark' => $max,
                'note'      => 'Run `php artisan corpwalletmanager:backfill-contributions --months=N` to populate history.',
            ]);
            return;
        }

        $rows = DB::table('corporation_wallet_journals')
            ->where('internal_id', '>', $watermark)
            ->orderBy('internal_id')
            ->limit(self::BATCH_CAP + 1)
            ->get();

        $capped = $rows->count() > self::BATCH_CAP;
        $rows = $rows->take(self::BATCH_CAP);

        if ($rows->isEmpty()) {
            return;
        }

        $processed = $service->applyJournalBatch($rows);

        if ($capped) {
            // Resume right after the last row we handled so we don't skip
            // un-handled large-batch tail entries.
            $newWatermark = (int) $rows->last()->internal_id;
        } else {
            // Caught up. Jump to the newest journal row so unclassified
            // rows aren't rescanned next run.
            $newWatermark = (int) (DB::table('corporation_wallet_journals')
                ->where('internal_id', '>', $watermark)
                ->max('internal_id') ?? $watermark);
        }

        $service->setWatermark($newWatermark);

        Log::info('ComputeCharacterContributions: batch processed', [
            'scanned'       => $rows->count(),
            'classified'    => $processed,
            'capped'        => $capped,
            'new_watermark' => $newWatermark,
        ]);

        // After the cache is up to date, evaluate the three HR-Manager-
        // facing member.* edge-transition events (stall / lifetime
        // milestone / tax compliance drop) and publish on transitions.
        // Skipped on capped runs because the cache may not reflect a
        // complete picture for the trailing months yet; next un-capped
        // run picks it up. Notifier itself is class_exists-guarded for
        // standalone CWM so this is cheap even without MC installed.
        if (! $capped) {
            try {
                $summary = app(\CorpWalletManager\Services\MemberMilestoneNotifier::class)->runSweep();
                if (array_sum(array_intersect_key($summary, ['stalled'=>1,'milestone'=>1,'compliance_dropped'=>1])) > 0) {
                    Log::info('ComputeCharacterContributions: milestone sweep published events', $summary);
                }
            } catch (\Throwable $e) {
                // Don't let event-publish failures abort the contribution
                // pipeline - the cache update is the primary contract.
                Log::warning('ComputeCharacterContributions: milestone sweep failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
