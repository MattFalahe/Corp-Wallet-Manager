<?php

namespace CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use CorpWalletManager\Services\PersonalWalletAggregator;

/**
 * Hourly precompute of the per-character personal-wallet aggregate
 * that backs the My Personal Wallet tab.
 *
 * Walks every non-deleted character in refresh_tokens and asks the
 * aggregator to refresh its current month (always) plus any prior
 * month with new journal rows past the stored watermark. The
 * aggregator handles the per-period idempotent upsert; this job
 * just owns the loop and the per-character try/catch so a single
 * bad character does not kill the batch.
 *
 * Optional backfill: when constructed with a positive
 * $backfillMonths the job rebuilds the trailing N months for every
 * character unconditionally. Used by the operator-facing artisan
 * command after an upgrade or after a long outage.
 */
class ComputePersonalWalletAggregates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int  30 minutes; the largest installs we've measured stay well inside this. */
    public $timeout = 1800;

    /** @var int */
    public $tries = 3;

    /** @var int|null  rebuild trailing N months when non-null */
    private ?int $backfillMonths;

    /** @var int|null  scope to a single character when non-null */
    private ?int $characterId;

    public function __construct(?int $backfillMonths = null, ?int $characterId = null)
    {
        $this->backfillMonths = $backfillMonths;
        $this->characterId    = $characterId;
    }

    public function tags(): array
    {
        return ['corpwalletmanager', 'personal-wallet-aggregates'];
    }

    public function handle(PersonalWalletAggregator $aggregator): void
    {
        // Single-character debug path.
        if ($this->characterId !== null && $this->characterId > 0) {
            try {
                $written = $aggregator->aggregateForCharacter(
                    $this->characterId,
                    $this->backfillMonths
                );
                Log::info('ComputePersonalWalletAggregates: single-character run', [
                    'character_id'     => $this->characterId,
                    'backfill_months'  => $this->backfillMonths,
                    'rows_written'     => $written,
                ]);
            } catch (\Throwable $e) {
                Log::warning('ComputePersonalWalletAggregates: single-character run failed', [
                    'character_id' => $this->characterId,
                    'error'        => $e->getMessage(),
                ]);
            }
            return;
        }

        $characterIds = DB::table('refresh_tokens')
            ->whereNull('deleted_at')
            ->pluck('character_id')
            ->map(fn ($id) => (int) $id)
            // Defensive NPC guard; refresh_tokens only ever holds player
            // characters by definition.
            ->filter(fn ($id) => $id >= 90000000)
            ->unique()
            ->values();

        $totalChars   = $characterIds->count();
        $totalWritten = 0;
        $errors       = 0;

        foreach ($characterIds as $characterId) {
            try {
                $totalWritten += $aggregator->aggregateForCharacter(
                    $characterId,
                    $this->backfillMonths
                );
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('ComputePersonalWalletAggregates: character failed', [
                    'character_id' => $characterId,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        Log::info('ComputePersonalWalletAggregates: batch complete', [
            'characters'      => $totalChars,
            'rows_written'    => $totalWritten,
            'errors'          => $errors,
            'backfill_months' => $this->backfillMonths,
        ]);
    }
}
