<?php

namespace CorpWalletManager\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use CorpWalletManager\Models\AlertState;
use CorpWalletManager\Models\AnomalyState;
use CorpWalletManager\Models\CharacterContribution;
use CorpWalletManager\Models\Settings;
use CorpWalletManager\Services\ContributionService;
use CorpWalletManager\Services\EntityNameResolver;
use CorpWalletManager\Services\WebhookService;
use CorpWalletManager\Support\JournalFilters;

/**
 * Hourly wallet alert detector.
 *
 * Four independent passes, each gated by its own threshold setting (0 = off):
 *
 *   - Large transactions: an incremental scan of corporation_wallet_journals
 *     by the monotonic internal_id surrogate key, so every journal row is
 *     considered exactly once with no dedup table. The first run adopts the
 *     current high-water mark without alerting, so enabling the feature
 *     never replays the whole journal history.
 *
 *   - Low balance: each corporation's true balance (summed across divisions
 *     from corporation_wallet_balances) is compared against a threshold and
 *     latched per corporation in corpwalletmanager_alert_state, so it fires
 *     once on the crossing rather than every run.
 *
 *   - Contribution drop: a member whose prior 3-month contribution average
 *     collapses to <20% of the prior window's average is flagged once per
 *     crossing. Latched per-(corp, character) in corpwalletmanager_anomaly_state
 *     and cleared once recent average recovers above 50% of prior, so the
 *     next drop after a recovery alerts again.
 *
 *   - Unusual recipient: a corporation_account_withdrawal sent to a
 *     second_party_id that has never received from this corp before (older
 *     than the configured cold-history window) above a threshold. Uses the
 *     same watermark scheme as large_transfer so each journal row is
 *     considered exactly once.
 *
 * Each detection delivers to subscribed Discord webhooks (works standalone)
 * and, when Manager Core is installed, publishes to its cross-plugin event
 * bus via Topics. CWM never hard-depends on Manager Core.
 */
class DetectWalletAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var int
     */
    public $timeout = 300;

    /**
     * @var int
     */
    public $tries = 1;

    /**
     * Maximum large transactions handled in a single run. A larger burst
     * (e.g. a fresh backfill) rolls forward to subsequent runs.
     */
    private const LARGE_TX_BATCH_CAP = 100;

    /**
     * corpwalletmanager_settings key holding the last scanned internal_id.
     */
    private const WATERMARK_KEY = 'alert_large_tx_watermark';

    /**
     * Maximum unusual-recipient candidates evaluated per run. Mirrors the
     * large-transaction cap; a backfill burst rolls forward.
     */
    private const UNUSUAL_RECIPIENT_BATCH_CAP = 100;

    /**
     * corpwalletmanager_settings key holding the unusual-recipient
     * detector's last scanned internal_id.
     */
    private const UNUSUAL_RECIPIENT_WATERMARK_KEY = 'alert_unusual_recipient_watermark';

    /**
     * Drop threshold: recent_3mo_avg < prior_3mo_avg * this ratio
     * triggers a contribution_drop alert (i.e. a drop of more than 80%).
     */
    private const CONTRIBUTION_DROP_RATIO = 0.20;

    /**
     * Recovery threshold: a latched (corp, character) row only clears
     * back to "not dropped" once recent_3mo_avg climbs above
     * prior_3mo_avg * this ratio. Keeping recovery higher than the drop
     * threshold avoids flap-flap-flap alerting when a member's
     * contribution hovers right at the boundary.
     */
    private const CONTRIBUTION_RECOVERY_RATIO = 0.50;

    /**
     * A second_party_id is treated as a first-time recipient when this
     * corp has no prior corporation_account_withdrawal to that recipient
     * older than the current watermark by at least this many days. This
     * window protects against false positives during a journal backfill:
     * if the only "prior" history is a row just above the current
     * watermark, the recipient could still be new (the operator may
     * have just paid them for the first time minutes ago). Using a
     * 7-day cold window means we treat the recipient as new only when
     * they have no prior interactions at least a week old.
     */
    private const UNUSUAL_RECIPIENT_COLD_DAYS = 7;

    public function tags(): array
    {
        return ['corpwalletmanager', 'alerts'];
    }

    public function handle(): void
    {
        // Each pass is isolated so a failure in one does not abort the others.
        try {
            $this->detectLargeTransactions();
        } catch (\Throwable $e) {
            Log::error('DetectWalletAlerts: large-transaction pass failed', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->detectLowBalances();
        } catch (\Throwable $e) {
            Log::error('DetectWalletAlerts: low-balance pass failed', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->detectContributionDrops();
        } catch (\Throwable $e) {
            Log::error('DetectWalletAlerts: contribution-drop pass failed', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->detectUnusualRecipients();
        } catch (\Throwable $e) {
            Log::error('DetectWalletAlerts: unusual-recipient pass failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Large transactions
    // ------------------------------------------------------------------

    private function detectLargeTransactions(): void
    {
        $threshold = (float) Settings::getIntegerSetting('alert_large_transaction_threshold', 0);
        if ($threshold <= 0) {
            return; // Feature disabled.
        }

        $watermark = Settings::getSetting(self::WATERMARK_KEY);

        // First run: adopt the current high-water mark and alert on nothing,
        // so enabling the feature does not replay the journal history.
        if ($watermark === null || $watermark === '') {
            $max = (int) DB::table('corporation_wallet_journals')->max('internal_id');
            Settings::setSetting(self::WATERMARK_KEY, $max);
            Log::info('DetectWalletAlerts: large-transaction watermark initialised', [
                'watermark' => $max,
            ]);

            return;
        }

        $watermark = (int) $watermark;

        // One extra row tells us whether the batch cap was hit.
        // Internal transfers (corp -> corp between divisions) are excluded
        // because they aren't real outflows; they would otherwise spam
        // large-tx alerts on every division-balance rebalance.
        $rowsQuery = DB::table('corporation_wallet_journals')
            ->where('internal_id', '>', $watermark)
            ->whereRaw('ABS(amount) >= ?', [$threshold]);
        $rowsQuery = JournalFilters::excludeInternalTransfers($rowsQuery);

        $rows = $rowsQuery
            ->orderBy('internal_id')
            ->limit(self::LARGE_TX_BATCH_CAP + 1)
            ->get();

        $capped = $rows->count() > self::LARGE_TX_BATCH_CAP;
        $rows = $rows->take(self::LARGE_TX_BATCH_CAP);

        foreach ($rows as $row) {
            $this->handleLargeTransaction($row);
        }

        // Advance the watermark. When capped, resume right after the last
        // row handled; otherwise jump to the newest journal row so
        // sub-threshold rows are not rescanned next run.
        if ($capped && $rows->isNotEmpty()) {
            $newWatermark = (int) $rows->last()->internal_id;
        } else {
            $newWatermark = (int) (DB::table('corporation_wallet_journals')
                ->where('internal_id', '>', $watermark)
                ->max('internal_id') ?? $watermark);
        }

        Settings::setSetting(self::WATERMARK_KEY, $newWatermark);

        if ($rows->isNotEmpty()) {
            Log::info('DetectWalletAlerts: large transactions handled', [
                'count'         => $rows->count(),
                'capped'        => $capped,
                'new_watermark' => $newWatermark,
            ]);
        }
    }

    private function handleLargeTransaction($row): void
    {
        $corporationId = (int) $row->corporation_id;
        $corpName = $this->corporationName($corporationId);

        app(WebhookService::class)->dispatchAlert(
            $corporationId,
            'large_transfer',
            $this->largeTransactionEmbed($row, $corpName)
        );

        if (class_exists(\ManagerCore\Topics::class)) {
            \ManagerCore\Topics::publish('wallet.transaction_detected', [
                'transaction_id'  => (int) $row->id,
                'corporation_id'  => $corporationId,
                'amount'          => (float) $row->amount,
                'ref_type'        => (string) $row->ref_type,
                'division'        => (int) $row->division,
                'first_party_id'  => $row->first_party_id !== null ? (int) $row->first_party_id : null,
                'second_party_id' => $row->second_party_id !== null ? (int) $row->second_party_id : null,
                'date'            => (string) $row->date,
                'description'     => (string) $row->description,
                'role_id'         => null,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Low balance
    // ------------------------------------------------------------------

    private function detectLowBalances(): void
    {
        $threshold = (float) Settings::getIntegerSetting('alert_low_balance_threshold', 0);
        if ($threshold <= 0) {
            return; // Feature disabled.
        }

        // corporation_wallet_balances holds the ESI-reported balance per
        // division; summed per corporation it is the true total balance.
        $balances = DB::table('corporation_wallet_balances')
            ->selectRaw('corporation_id, SUM(balance) as total')
            ->groupBy('corporation_id')
            ->get();

        foreach ($balances as $balance) {
            $corporationId = (int) $balance->corporation_id;
            $total = (float) $balance->total;
            $isLow = $total < $threshold;

            $state = AlertState::firstOrNew(['corporation_id' => $corporationId]);

            if ($isLow && ! $state->balance_is_low) {
                // Crossed below the threshold.
                $this->handleLowBalance($corporationId, $total, $threshold);
                $state->balance_is_low = true;
                $state->balance_low_notified_at = now();
                $state->save();
            } elseif (! $isLow && $state->balance_is_low) {
                // Recovered above the threshold — clear the latch so a
                // future dip alerts again. No recovery event for now.
                $state->balance_is_low = false;
                $state->save();
            }
        }
    }

    private function handleLowBalance(int $corporationId, float $balance, float $threshold): void
    {
        $corpName = $this->corporationName($corporationId);

        app(WebhookService::class)->dispatchAlert(
            $corporationId,
            'low_balance',
            $this->lowBalanceEmbed($corpName, $balance, $threshold)
        );

        if (class_exists(\ManagerCore\Topics::class)) {
            \ManagerCore\Topics::publish('wallet.balance_low', [
                'corporation_id' => $corporationId,
                'balance'        => $balance,
                'threshold'      => $threshold,
                'detected_at'    => now()->toIso8601String(),
                'role_id'        => null,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Contribution drop
    // ------------------------------------------------------------------

    /**
     * Walk every (corp, character) row in the contribution cache that
     * could plausibly be alerting: the cohort is the same character_id
     * + corporation_id pairs that surface on Top Contributors (NPCs and
     * the corp's own id excluded). For each, pull a 6-month trend
     * via ContributionService and compare the trailing two 3-month
     * windows.
     *
     * The latch in corpwalletmanager_anomaly_state ensures one alert
     * per crossing. Recovery (recent_3mo_avg >= prior_3mo_avg * 50%)
     * clears the latch so a future drop alerts again.
     */
    private function detectContributionDrops(): void
    {
        $threshold = (float) Settings::getIntegerSetting('anomaly_contribution_threshold', 0);
        if ($threshold <= 0) {
            return; // Feature disabled.
        }

        // The cohort uses the same defensive NPC / self guards Top
        // Contributors applies, so we never page a "Caldari Navy
        // dropped its contribution" alert.
        $pairs = CharacterContribution::query()
            ->where('character_id', '>=', 90000000)
            ->whereColumn('character_id', '!=', 'corporation_id')
            ->select('corporation_id', 'character_id')
            ->distinct()
            ->get();

        if ($pairs->isEmpty()) {
            return;
        }

        $service = app(ContributionService::class);
        $alertsFired = 0;
        $latchesCleared = 0;

        foreach ($pairs as $pair) {
            $corporationId = (int) $pair->corporation_id;
            $characterId   = (int) $pair->character_id;

            $trend = $service->getCharacterTrend($characterId, $corporationId, 6);
            $priorAvg  = (float) ($trend['prior_3mo_avg']  ?? 0);
            $recentAvg = (float) ($trend['recent_3mo_avg'] ?? 0);

            $state = AnomalyState::firstOrNew([
                'corporation_id' => $corporationId,
                'character_id'   => $characterId,
            ]);
            $latched = (bool) $state->contribution_drop_latched;

            // Drop condition: the prior window cleared the floor AND
            // the recent window collapsed below the drop ratio.
            $dropped = $priorAvg >= $threshold
                && $recentAvg < ($priorAvg * self::CONTRIBUTION_DROP_RATIO);

            if ($dropped && ! $latched) {
                $this->handleContributionDrop($corporationId, $characterId, $priorAvg, $recentAvg);
                $state->corporation_id = $corporationId;
                $state->character_id   = $characterId;
                $state->contribution_drop_latched     = true;
                $state->contribution_drop_prior_avg   = $priorAvg;
                $state->contribution_drop_recent_avg  = $recentAvg;
                $state->contribution_drop_notified_at = now();
                $state->save();
                $alertsFired++;
                continue;
            }

            // Recovery: clear the latch once recent climbs back above
            // the recovery ratio of prior, so a fresh dip alerts again.
            if ($latched && $recentAvg >= ($priorAvg * self::CONTRIBUTION_RECOVERY_RATIO)) {
                $state->contribution_drop_latched = false;
                $state->save();
                $latchesCleared++;
            }
        }

        if ($alertsFired > 0 || $latchesCleared > 0) {
            Log::info('DetectWalletAlerts: contribution drops handled', [
                'alerts_fired'    => $alertsFired,
                'latches_cleared' => $latchesCleared,
                'cohort_size'     => $pairs->count(),
            ]);
        }
    }

    private function handleContributionDrop(int $corporationId, int $characterId, float $priorAvg, float $recentAvg): void
    {
        $names = app(EntityNameResolver::class)->resolve([$characterId], false);
        $characterName = $names[$characterId]['name'] ?? ('Character ' . $characterId);

        app(WebhookService::class)->dispatchAlert(
            $corporationId,
            'contribution_drop',
            $this->contributionDropEmbed($characterName, $characterId, $priorAvg, $recentAvg)
        );

        if (class_exists(\ManagerCore\Topics::class)) {
            \ManagerCore\Topics::publish('member.contribution.drop_detected', [
                'corporation_id' => $corporationId,
                'character_id'   => $characterId,
                'character_name' => $characterName,
                'prior_3mo_avg'  => $priorAvg,
                'recent_3mo_avg' => $recentAvg,
                'drop_ratio'     => $priorAvg > 0 ? $recentAvg / $priorAvg : 0.0,
                'detected_at'    => now()->toIso8601String(),
                'role_id'        => null,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Unusual recipient
    // ------------------------------------------------------------------

    /**
     * Scan recent corporation_account_withdrawal rows by internal_id
     * watermark and flag any whose magnitude clears the threshold AND
     * whose second_party_id has never received from this corp before
     * (older than the cold-history window).
     *
     * Like the large-transaction pass, the first run adopts the current
     * high-water mark without alerting so enabling the feature does not
     * replay journal history.
     */
    private function detectUnusualRecipients(): void
    {
        $threshold = (float) Settings::getIntegerSetting('anomaly_unusual_recipient_threshold', 0);
        if ($threshold <= 0) {
            return; // Feature disabled.
        }

        $watermark = Settings::getSetting(self::UNUSUAL_RECIPIENT_WATERMARK_KEY);

        if ($watermark === null || $watermark === '') {
            $max = (int) DB::table('corporation_wallet_journals')->max('internal_id');
            Settings::setSetting(self::UNUSUAL_RECIPIENT_WATERMARK_KEY, $max);
            Log::info('DetectWalletAlerts: unusual-recipient watermark initialised', [
                'watermark' => $max,
            ]);

            return;
        }

        $watermark = (int) $watermark;

        $rows = DB::table('corporation_wallet_journals')
            ->where('internal_id', '>', $watermark)
            ->where('ref_type', 'corporation_account_withdrawal')
            ->where('amount', '<=', -$threshold)
            ->whereNotNull('second_party_id')
            // Exclude the corp's own id (internal transfers between
            // divisions) and any second-party that resolves to the
            // corporation_id itself for this row.
            ->whereColumn('second_party_id', '!=', 'corporation_id')
            ->orderBy('internal_id')
            ->limit(self::UNUSUAL_RECIPIENT_BATCH_CAP + 1)
            ->get();

        $capped = $rows->count() > self::UNUSUAL_RECIPIENT_BATCH_CAP;
        $rows = $rows->take(self::UNUSUAL_RECIPIENT_BATCH_CAP);

        $alertsFired = 0;
        foreach ($rows as $row) {
            if ($this->isFirstTimeRecipient((int) $row->corporation_id, (int) $row->second_party_id, (string) $row->date)) {
                $this->handleUnusualRecipient($row);
                $alertsFired++;
            }
        }

        if ($capped && $rows->isNotEmpty()) {
            $newWatermark = (int) $rows->last()->internal_id;
        } else {
            $newWatermark = (int) (DB::table('corporation_wallet_journals')
                ->where('internal_id', '>', $watermark)
                ->max('internal_id') ?? $watermark);
        }

        Settings::setSetting(self::UNUSUAL_RECIPIENT_WATERMARK_KEY, $newWatermark);

        if ($alertsFired > 0 || $rows->isNotEmpty()) {
            Log::info('DetectWalletAlerts: unusual recipients handled', [
                'rows_scanned' => $rows->count(),
                'alerts_fired' => $alertsFired,
                'capped'       => $capped,
                'new_watermark' => $newWatermark,
            ]);
        }
    }

    /**
     * Is $recipientId a first-time recipient of corp outflows from this
     * corp at the time of $rowDate? We require:
     *
     *   1. No prior corporation_account_withdrawal row to this recipient
     *      from this corp older than UNUSUAL_RECIPIENT_COLD_DAYS days
     *      before $rowDate. A backfill landing a few new rows for a
     *      regular recipient should not alert; only genuinely-new
     *      recipients should.
     *
     * Player_donation is intentionally excluded from the prior-history
     * check: a member donating to the corp does not establish that the
     * corp has ever paid that character. The check is "have we paid out
     * to them before", scoped to actual outflows.
     */
    private function isFirstTimeRecipient(int $corporationId, int $recipientId, string $rowDate): bool
    {
        $coldCutoff = Carbon::parse($rowDate)
            ->subDays(self::UNUSUAL_RECIPIENT_COLD_DAYS)
            ->toDateTimeString();

        return ! DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->where('second_party_id', $recipientId)
            ->where('ref_type', 'corporation_account_withdrawal')
            ->where('date', '<', $coldCutoff)
            ->exists();
    }

    private function handleUnusualRecipient($row): void
    {
        $corporationId = (int) $row->corporation_id;
        $recipientId   = (int) $row->second_party_id;

        // useEsi=true so an external recipient (player corp, alliance,
        // or character no local table knows) still resolves on first
        // alert; the ESI fallback writes back into universe_names so
        // subsequent lookups are free.
        $names = app(EntityNameResolver::class)->resolve([$recipientId], true);
        $recipientName = $names[$recipientId]['name'] ?? ('Entity ' . $recipientId);
        $recipientType = $names[$recipientId]['type'] ?? 'unknown';

        $corpName = $this->corporationName($corporationId);

        app(WebhookService::class)->dispatchAlert(
            $corporationId,
            'unusual_recipient',
            $this->unusualRecipientEmbed($row, $corpName, $recipientName, $recipientType)
        );

        if (class_exists(\ManagerCore\Topics::class)) {
            \ManagerCore\Topics::publish('wallet.unusual_recipient_detected', [
                'transaction_id'  => (int) $row->id,
                'corporation_id'  => $corporationId,
                'recipient_id'    => $recipientId,
                'recipient_name'  => $recipientName,
                'recipient_type'  => $recipientType,
                'amount'          => (float) $row->amount,
                'division'        => (int) $row->division,
                'date'            => (string) $row->date,
                'description'     => (string) $row->description,
                'role_id'         => null,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function corporationName(int $corporationId): string
    {
        return DB::table('corporation_infos')
            ->where('corporation_id', $corporationId)
            ->value('name') ?? ('Corporation ' . $corporationId);
    }

    private function largeTransactionEmbed($row, string $corpName): array
    {
        $amount = (float) $row->amount;
        $incoming = $amount >= 0;

        return [
            'title'       => 'Large Wallet Transaction',
            'description' => 'A transaction on **' . $corpName . '** met the alert threshold.',
            'color'       => $incoming ? 3066993 : 15158332, // green / red
            'timestamp'   => now()->toIso8601String(),
            'fields'      => [
                [
                    'name'   => $incoming ? 'Amount Received' : 'Amount Spent',
                    'value'  => number_format(abs($amount), 2) . ' ISK',
                    'inline' => true,
                ],
                [
                    'name'   => 'Type',
                    'value'  => str_replace('_', ' ', (string) $row->ref_type),
                    'inline' => true,
                ],
                [
                    'name'   => 'Division',
                    'value'  => (string) $row->division,
                    'inline' => true,
                ],
                [
                    'name'   => 'Date',
                    'value'  => (string) $row->date,
                    'inline' => false,
                ],
            ],
            'footer'      => ['text' => 'Corp Wallet Manager'],
        ];
    }

    private function lowBalanceEmbed(string $corpName, float $balance, float $threshold): array
    {
        return [
            'title'       => 'Low Wallet Balance',
            'description' => '**' . $corpName . '** dropped below the low-balance threshold.',
            'color'       => 15158332, // red
            'timestamp'   => now()->toIso8601String(),
            'fields'      => [
                [
                    'name'   => 'Current Balance',
                    'value'  => number_format($balance, 2) . ' ISK',
                    'inline' => true,
                ],
                [
                    'name'   => 'Threshold',
                    'value'  => number_format($threshold, 2) . ' ISK',
                    'inline' => true,
                ],
            ],
            'footer'      => ['text' => 'Corp Wallet Manager'],
        ];
    }

    private function contributionDropEmbed(string $characterName, int $characterId, float $priorAvg, float $recentAvg): array
    {
        $dropPct = $priorAvg > 0
            ? max(0.0, (1.0 - ($recentAvg / $priorAvg)) * 100.0)
            : 0.0;

        return [
            'title'       => 'Member Contribution Drop',
            'description' => '**' . $characterName . '** (ID ' . $characterId . ') contributions dropped from '
                            . number_format($priorAvg, 2) . ' ISK to '
                            . number_format($recentAvg, 2) . ' ISK over the trailing 3 months.',
            'color'       => 16753920, // amber
            'timestamp'   => now()->toIso8601String(),
            'fields'      => [
                [
                    'name'   => 'Prior 3-Month Average',
                    'value'  => number_format($priorAvg, 2) . ' ISK',
                    'inline' => true,
                ],
                [
                    'name'   => 'Recent 3-Month Average',
                    'value'  => number_format($recentAvg, 2) . ' ISK',
                    'inline' => true,
                ],
                [
                    'name'   => 'Drop',
                    'value'  => number_format($dropPct, 1) . '%',
                    'inline' => true,
                ],
            ],
            'footer'      => ['text' => 'Corp Wallet Manager'],
        ];
    }

    private function unusualRecipientEmbed($row, string $corpName, string $recipientName, string $recipientType): array
    {
        $amount = abs((float) $row->amount);
        $typeLabel = $recipientType !== '' && $recipientType !== 'unknown'
            ? ' (' . ucfirst($recipientType) . ')'
            : '';

        return [
            'title'       => 'Unusual Recipient Detected',
            'description' => 'A large outgoing payment from **' . $corpName . '** went to a recipient with no prior history.',
            'color'       => 15158332, // red
            'timestamp'   => now()->toIso8601String(),
            'fields'      => [
                [
                    'name'   => 'Recipient',
                    'value'  => $recipientName . $typeLabel,
                    'inline' => false,
                ],
                [
                    'name'   => 'Amount',
                    'value'  => number_format($amount, 2) . ' ISK',
                    'inline' => true,
                ],
                [
                    'name'   => 'Division',
                    'value'  => (string) $row->division,
                    'inline' => true,
                ],
                [
                    'name'   => 'Date',
                    'value'  => (string) $row->date,
                    'inline' => true,
                ],
            ],
            'footer'      => ['text' => 'Corp Wallet Manager'],
        ];
    }
}
