<?php

namespace CorpWalletManager\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use CorpWalletManager\Models\CharacterContribution;
use CorpWalletManager\Models\Settings;
use CorpWalletManager\Support\JournalFilters;
use CorpWalletManager\Services\EntityNameResolver;

/**
 * Per-character corp wallet contribution analytics.
 *
 * Classifies corporation_wallet_journals entries into per-character buckets
 * and maintains a precomputed cache table (corpwalletmanager_character_contributions)
 * for fast HR / leaderboard queries.
 *
 * When Mining Manager is installed, MM's TaxCode::extractCodeFromText() helper
 * is used to split player_donation entries into tax_payment vs voluntary
 * donation. When MM is absent the two collapse into a single donation bucket
 * on read. The split happens at compute time, so existing rows stay as
 * classified; future scans pick up the new MM state.
 *
 * Every Mining Manager reference is class_exists-guarded. CWM has no
 * composer dependency on MM.
 */
class ContributionService
{
    /** Cached MM-presence flag for the lifetime of this service instance. */
    private ?bool $mmInstalled = null;

    /**
     * In-memory cache for description-parsed name lookups. Keyed by
     * "name|corp_id" or "name|*"; value is the resolved character_id or
     * null. Backfill processes thousands of industry / ESS rows where
     * the same handful of names recur — caching avoids one DB hit per
     * row. Cleared whenever the service instance is rebuilt.
     *
     * @var array<string, int|null>
     */
    private array $nameLookupCache = [];

    /**
     * In-memory cache of corp wallet journal ids that Mining Manager
     * has linked to a tax invoice (via mining_taxes.transaction_id).
     * Populated lazily per-row by isMmTaxPayment, or in bulk by
     * prewarmMmTaxCache() at the start of applyJournalBatch to avoid
     * one DB hit per donation row.
     *
     * @var array<int, bool>
     */
    private array $mmTaxTransactionCache = [];

    /** corpwalletmanager_settings key holding the contribution scan watermark. */
    private const WATERMARK_KEY = 'contributions_last_internal_id';

    // ------------------------------------------------------------------
    // Classification
    // ------------------------------------------------------------------

    /**
     * Map one raw corporation_wallet_journals row to a (character_id, bucket)
     * pair, or null when the entry has no per-character attribution under
     * the bucket scheme.
     *
     * Other ref_types (market fees, contract movements, etc.) are skipped
     * for the cache; raw queries can still surface them via
     * getCharacterEntries().
     *
     * @param  object  $row  stdClass row from DB::table('corporation_wallet_journals')
     * @return array{character_id:int,bucket:string}|null
     */
    public function classify($row): ?array
    {
        // Inter-division transfers (first_party == second_party == corp_id)
        // are corp-internal money movement; they double-count if treated as
        // a contribution. Skip them across the board.
        if (JournalFilters::isInternalTransfer($row)) {
            return null;
        }

        $refType = $row->ref_type ?? null;
        if ($refType === null) {
            return null;
        }

        switch ($refType) {
            case 'bounty_prizes':
            case 'bounty_prize':
            case 'bounty_prize_corporation_tax':
                // For CORP wallets the first_party_id on a bounty entry is
                // the NPC pirate's faction (e.g. 1000125 = Caldari Navy),
                // not the ratting member. CCP wallet journal v3+ exposes
                // the actual ratter in context_id when context_id_type =
                // 'character_id'; prefer that. Fall back to first_party_id
                // only when it is plausibly a real character (>= 90M IDs).
                // bounty_prize_corporation_tax is an alternate ref_type
                // CCP uses for the corp's tax slice in some scenarios;
                // same shape, same handling.
                $ratter = $this->preferContextCharacter($row, (int) ($row->first_party_id ?? 0));
                if ($ratter === null) {
                    return null;
                }
                return ['character_id' => $ratter, 'bucket' => 'ratting'];

            case 'agent_mission_reward':
            case 'agent_mission_time_bonus_reward':
            case 'agent_mission_reward_corporation_tax':
            case 'agent_mission_time_bonus_reward_corporation_tax':
                // Same shape as bounties for corp wallets: first_party is
                // the agent NPC, the running character is in context_id.
                // The *_corporation_tax variants are the corp tax slice
                // versions of the same activity.
                $runner = $this->preferContextCharacter($row, (int) ($row->first_party_id ?? 0));
                if ($runner === null) {
                    return null;
                }
                return ['character_id' => $runner, 'bucket' => 'mission'];

            case 'ess_escrow_transfer':
                // ESS escrow pays the ratter the bulk of the bounty later;
                // the corp wallet entry is the corp's small recurring cut
                // (similar to bounty tax). CCP doesn't structure the
                // ratter on party ids or context_id for ESS; the only
                // reliable signal is the description, which is
                // "Encounter Surveillance System in {system} transferred
                // funds to {character}". Skip member-affiliation check —
                // ESS pays out from ratting in our corp's systems, so the
                // ratter is by definition associated with this corp's
                // activity (even alts of allies counted; trust the ratter
                // attribution from the description).
                $essRatter = $this->lookupCharacterByDescriptionName(
                    $row,
                    '/transferred funds to (.+)$/',
                    false
                );
                if ($essRatter === null) {
                    return null;
                }
                return ['character_id' => $essRatter, 'bucket' => 'ratting'];

            case 'industry_job_tax':
                // "Industry facility tax between {installer} and {corp}
                // (Job ID: NNNN)". The installer might be a corp member
                // (member contribution) or an outsider using corp
                // infrastructure (corp revenue but not a contribution).
                // Require member affiliation so the leaderboard stays
                // member-focused; externals still get counted by the
                // generic income / breakdown queries in GenerateReport
                // because those look at amount > 0 directly.
                $installer = $this->lookupCharacterByDescriptionName(
                    $row,
                    '/Industry facility tax between (.+?) and /',
                    true
                );
                if ($installer === null) {
                    return null;
                }
                return ['character_id' => $installer, 'bucket' => 'industry'];

            case 'player_donation':
                // first_party is the donor character; an NPC id here would
                // be CCP data corruption, but guard anyway.
                $donor = (int) ($row->first_party_id ?? 0);
                if ($donor < 90_000_000) {
                    return null;
                }
                // Split tax vs voluntary. Priority order:
                //   1. MM has linked this journal id to an invoice
                //      (mining_taxes.transaction_id) — authoritative.
                //      Members pay tax via the same player_donation
                //      ref_type as voluntary gifts; MM's own bookkeeping
                //      is the only deterministic signal that a given
                //      donation IS the tax payment.
                //   2. Description contains a recognised MM tax code —
                //      legacy fallback for invoices MM hasn't processed
                //      yet (e.g. mid-backfill race).
                //   3. Default — voluntary.
                $bucket = 'donation_voluntary';
                if ($this->isMmInstalled()) {
                    if ($this->isMmTaxPayment((int) $row->id)) {
                        $bucket = 'tax_payment';
                    } elseif ($this->extractTaxCode($row->description ?? null) !== null) {
                        $bucket = 'tax_payment';
                    }
                }
                return ['character_id' => $donor, 'bucket' => $bucket];

            case 'corporation_account_withdrawal':
                // second_party_id is the destination — may be a character,
                // a player corp, or a legitimate NPC recipient (jump
                // clones, broker fees paid out, etc). We keep NPC ids here
                // because they represent real outflows; the leaderboard
                // filters NPCs at read time for the player-only ranking.
                if (! $row->second_party_id) {
                    return null;
                }
                return ['character_id' => (int) $row->second_party_id, 'bucket' => 'withdrawal'];

            default:
                return null;
        }
    }

    /**
     * Resolve the "real" character on a journal row that may have an NPC
     * id in first_party. Prefers row.context_id when context_id_type =
     * 'character_id'; falls back to $rawFirstParty only when that id is
     * itself in the player range (>= 90M). Returns null when neither
     * source yields a player id — caller should skip the row in that case
     * rather than mis-attribute to an NPC.
     */
    private function preferContextCharacter($row, int $rawFirstParty): ?int
    {
        $contextType = $row->context_id_type ?? null;
        $contextId   = $row->context_id ?? null;

        if ($contextType === 'character_id' && $contextId) {
            return (int) $contextId;
        }
        if ($rawFirstParty >= 90_000_000) {
            return $rawFirstParty;
        }
        return null;
    }

    /**
     * Resolve a player character from a journal row whose acting
     * character is identified only in the description text. Runs $regex
     * against the description, takes capture group 1 as the character
     * name, and looks it up in character_infos.
     *
     * When $requireCorpMembership is true, restricts the match to
     * characters currently affiliated with the journal row's
     * corporation_id. This is how we keep external infrastructure users
     * (e.g. an outside player paying industry facility tax to our corp's
     * structures) off the member-contribution leaderboard while still
     * letting income/expense queries in GenerateReport count the ISK.
     *
     * Returns null when the regex misses, the captured name is empty,
     * or character_infos has no matching row.
     *
     * Cached for the lifetime of this service instance so that
     * BackfillContributions does not issue one DB lookup per journal row
     * when the same handful of names dominate a period.
     */
    private function lookupCharacterByDescriptionName($row, string $regex, bool $requireCorpMembership): ?int
    {
        $description = (string) ($row->description ?? '');
        if ($description === '' || ! preg_match($regex, $description, $m)) {
            return null;
        }

        $name = isset($m[1]) ? trim((string) $m[1]) : '';
        if ($name === '') {
            return null;
        }

        $corpId = (int) ($row->corporation_id ?? 0);
        $cacheKey = $name . '|' . ($requireCorpMembership ? $corpId : '*');

        if (array_key_exists($cacheKey, $this->nameLookupCache)) {
            return $this->nameLookupCache[$cacheKey];
        }

        // SeAT stores character names in character_infos but corp / alliance
        // affiliation in character_affiliations (separate table so the
        // affiliation can be re-fetched without rewriting character info).
        // To filter by corp we have to JOIN; character_infos has no
        // corporation_id column.
        $query = DB::table('character_infos')->where('character_infos.name', $name);
        if ($requireCorpMembership) {
            if (! Schema::hasTable('character_affiliations')) {
                // Fail-open: without the affiliations table we cannot
                // verify membership. Better to attribute than to silently
                // drop every industry-tax row.
                $characterId = $query->value('character_infos.character_id');
            } else {
                $query->join(
                    'character_affiliations',
                    'character_infos.character_id',
                    '=',
                    'character_affiliations.character_id'
                )->where('character_affiliations.corporation_id', $corpId);
                $characterId = $query->value('character_infos.character_id');
            }
        } else {
            $characterId = $query->value('character_infos.character_id');
        }

        $resolved = $characterId ? (int) $characterId : null;

        $this->nameLookupCache[$cacheKey] = $resolved;
        return $resolved;
    }

    /** Is Mining Manager installed and exposing the TaxCode helper class? */
    public function isMmInstalled(): bool
    {
        if ($this->mmInstalled === null) {
            $this->mmInstalled = class_exists(\MiningManager\Models\TaxCode::class);
        }
        return $this->mmInstalled;
    }

    /**
     * Has Mining Manager linked this corp wallet journal id to one of
     * its tax invoices? Returns true when mining_taxes.transaction_id
     * matches; false when MM is not installed, the table is missing,
     * or the row is genuinely a voluntary donation.
     *
     * Single-row variant — public so DiagnosticController's Donation
     * Audit can call it without going through applyJournalBatch. Reuses
     * the per-instance cache so a Donation Audit table of 500 rows
     * doesn't run 500 queries.
     */
    public function isMmTaxPayment(int $journalId): bool
    {
        if ($journalId <= 0) {
            return false;
        }
        if (array_key_exists($journalId, $this->mmTaxTransactionCache)) {
            return $this->mmTaxTransactionCache[$journalId];
        }
        if (! $this->isMmInstalled() || ! Schema::hasTable('mining_taxes')) {
            return $this->mmTaxTransactionCache[$journalId] = false;
        }

        try {
            $exists = DB::table('mining_taxes')
                ->where('transaction_id', (string) $journalId)
                ->exists();
            return $this->mmTaxTransactionCache[$journalId] = $exists;
        } catch (\Throwable $e) {
            Log::warning('[Corp Wallet Manager] ContributionService: MM transaction link check failed', [
                'journal_id' => $journalId,
                'error'      => $e->getMessage(),
            ]);
            return $this->mmTaxTransactionCache[$journalId] = false;
        }
    }

    /**
     * Bulk-warm the MM-tax-link cache for a batch of journal ids. One
     * DB query instead of N when the caller knows the ids up front
     * (BackfillContributions, Donation Audit). Idempotent — already-
     * cached ids are skipped.
     *
     * @param  array<int>  $journalIds
     */
    public function prewarmMmTaxCache(array $journalIds): void
    {
        if (! $this->isMmInstalled() || ! Schema::hasTable('mining_taxes')) {
            return;
        }

        $unseen = array_values(array_filter(
            array_map('intval', $journalIds),
            fn ($id) => $id > 0 && ! array_key_exists($id, $this->mmTaxTransactionCache)
        ));

        if (empty($unseen)) {
            return;
        }

        try {
            $linked = DB::table('mining_taxes')
                ->whereIn('transaction_id', array_map('strval', $unseen))
                ->pluck('transaction_id')
                ->map(fn ($v) => (int) $v)
                ->all();

            $linkedSet = array_flip($linked);
            foreach ($unseen as $id) {
                $this->mmTaxTransactionCache[$id] = isset($linkedSet[$id]);
            }
        } catch (\Throwable $e) {
            Log::warning('[Corp Wallet Manager] ContributionService: MM tax cache prewarm failed', [
                'count' => count($unseen),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pull an MM tax code out of an arbitrary description string by
     * delegating to MM's own extractor. Returns null when MM is absent or
     * no code is present. Centralised here so classify() stays tidy.
     */
    public function extractTaxCode(?string $description): ?string
    {
        if (! $this->isMmInstalled()) {
            return null;
        }
        try {
            return \MiningManager\Models\TaxCode::extractCodeFromText($description);
        } catch (\Throwable $e) {
            Log::warning('[Corp Wallet Manager] ContributionService: MM tax-code lookup failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    // ------------------------------------------------------------------
    // Batch application (cache writes)
    // ------------------------------------------------------------------

    /**
     * Classify and accumulate a batch of journal rows, then apply the
     * accumulated per-(corp, char, period, bucket) totals to the cache via
     * atomic increments. Returns the number of rows that actually
     * contributed to a bucket (unclassified rows are skipped and not
     * counted).
     *
     * @param  iterable  $rows  stdClass rows from corporation_wallet_journals
     */
    public function applyJournalBatch(iterable $rows): int
    {
        // Materialise the iterable so we can scan it once for journal
        // ids to pre-warm the MM tax cache, then again for the actual
        // classification pass. For player_donation rows the MM-link
        // check is the difference between attributing as tax_payment
        // vs voluntary, so we cannot defer.
        if (! is_array($rows) && ! $rows instanceof \Illuminate\Support\Collection) {
            $rows = collect($rows);
        }

        if ($this->isMmInstalled()) {
            $journalIds = [];
            foreach ($rows as $row) {
                if (($row->ref_type ?? null) === 'player_donation' && isset($row->id)) {
                    $journalIds[] = (int) $row->id;
                }
            }
            if (! empty($journalIds)) {
                $this->prewarmMmTaxCache($journalIds);
            }
        }

        $deltas = [];
        $processed = 0;

        foreach ($rows as $row) {
            $classification = $this->classify($row);
            if ($classification === null) {
                continue;
            }

            $period = substr((string) $row->date, 0, 7);
            $key = sprintf('%d|%d|%s', (int) $row->corporation_id, $classification['character_id'], $period);

            if (! isset($deltas[$key])) {
                $deltas[$key] = [
                    'corporation_id' => (int) $row->corporation_id,
                    'character_id'   => $classification['character_id'],
                    'period'         => $period,
                ];
            }

            $bucket = $classification['bucket'];
            $amount = abs((float) $row->amount);

            $deltas[$key][$bucket . '_amount'] = ($deltas[$key][$bucket . '_amount'] ?? 0) + $amount;
            $deltas[$key][$bucket . '_count']  = ($deltas[$key][$bucket . '_count']  ?? 0) + 1;

            $processed++;
        }

        foreach ($deltas as $delta) {
            $this->applyDelta($delta);
        }

        return $processed;
    }

    /**
     * Apply one (corp, char, period) delta: create the cache row if missing
     * (DB defaults all zero), then atomically increment the affected bucket
     * columns. total_contribution_amount tracks the four positive
     * contribution buckets (everything except withdrawal).
     */
    private function applyDelta(array $delta): void
    {
        $row = CharacterContribution::firstOrCreate([
            'corporation_id' => $delta['corporation_id'],
            'character_id'   => $delta['character_id'],
            'period'         => $delta['period'],
        ]);

        $updates = [];
        $totalContribution = 0.0;

        $contributionBuckets = ['ratting', 'mission', 'tax_payment', 'donation_voluntary', 'industry'];

        foreach (['ratting', 'mission', 'tax_payment', 'donation_voluntary', 'industry', 'withdrawal'] as $bucket) {
            $amountCol = $bucket . '_amount';
            $countCol  = $bucket . '_count';

            if (! empty($delta[$amountCol])) {
                $updates[$amountCol] = DB::raw($amountCol . ' + ' . $delta[$amountCol]);
                if (in_array($bucket, $contributionBuckets, true)) {
                    $totalContribution += $delta[$amountCol];
                }
            }
            if (! empty($delta[$countCol])) {
                $updates[$countCol] = DB::raw($countCol . ' + ' . $delta[$countCol]);
            }
        }

        if ($totalContribution > 0) {
            $updates['total_contribution_amount'] = DB::raw('total_contribution_amount + ' . $totalContribution);
        }

        if (! empty($updates)) {
            CharacterContribution::where('id', $row->id)->update($updates);
        }
    }

    public function getWatermark(): ?int
    {
        $value = Settings::getSetting(self::WATERMARK_KEY);
        return ($value === null || $value === '') ? null : (int) $value;
    }

    public function setWatermark(int $watermark): void
    {
        Settings::setSetting(self::WATERMARK_KEY, $watermark);
    }

    // ------------------------------------------------------------------
    // Read methods (back the PluginBridge capabilities + leaderboard)
    // ------------------------------------------------------------------

    /**
     * Per-character totals over the trailing N months.
     */
    public function getCharacterSummary(int $characterId, int $corporationId, int $months = 6): array
    {
        $periods = $this->periodsForLastMonths($months);

        $row = CharacterContribution::query()
            ->where('character_id', $characterId)
            ->where('corporation_id', $corporationId)
            ->whereIn('period', $periods)
            ->selectRaw(
                'SUM(ratting_amount) AS ratting, ' .
                'SUM(mission_amount) AS mission, ' .
                'SUM(tax_payment_amount) AS tax_payment, ' .
                'SUM(donation_voluntary_amount) AS donation_voluntary, ' .
                'SUM(industry_amount) AS industry, ' .
                'SUM(withdrawal_amount) AS withdrawal, ' .
                'SUM(total_contribution_amount) AS total_contribution, ' .
                'SUM(ratting_count + mission_count + tax_payment_count + donation_voluntary_count + industry_count) AS contribution_count, ' .
                'SUM(withdrawal_count) AS withdrawal_count, ' .
                'MIN(period) AS first_period, MAX(period) AS last_period'
            )
            ->first();

        return [
            'character_id'              => $characterId,
            'corporation_id'            => $corporationId,
            'months'                    => $months,
            'ratting_amount'            => (float) ($row->ratting ?? 0),
            'mission_amount'            => (float) ($row->mission ?? 0),
            'tax_payment_amount'        => (float) ($row->tax_payment ?? 0),
            'donation_voluntary_amount' => (float) ($row->donation_voluntary ?? 0),
            'industry_amount'           => (float) ($row->industry ?? 0),
            'withdrawal_amount'         => (float) ($row->withdrawal ?? 0),
            'total_contribution_amount' => (float) ($row->total_contribution ?? 0),
            'contribution_count'        => (int) ($row->contribution_count ?? 0),
            'withdrawal_count'          => (int) ($row->withdrawal_count ?? 0),
            'first_period'              => $row->first_period,
            'last_period'               => $row->last_period,
            'mm_available'              => $this->isMmInstalled(),
        ];
    }

    /**
     * Per-bucket per-month breakdown over the trailing N months. When MM is
     * absent on the READING side, tax_payment and donation_voluntary collapse
     * into a single 'donation' bucket so consumers see a consistent shape.
     */
    public function getCharacterByCategory(int $characterId, int $corporationId, int $months = 6): array
    {
        $periods = $this->periodsForLastMonths($months);
        $rows = CharacterContribution::query()
            ->where('character_id', $characterId)
            ->where('corporation_id', $corporationId)
            ->whereIn('period', $periods)
            ->orderBy('period')
            ->get();

        $mm = $this->isMmInstalled();
        $byPeriod = [];

        foreach ($rows as $r) {
            $entry = [
                'period'     => $r->period,
                'ratting'    => ['amount' => (float) $r->ratting_amount, 'count' => (int) $r->ratting_count],
                'mission'    => ['amount' => (float) $r->mission_amount, 'count' => (int) $r->mission_count],
                'industry'   => ['amount' => (float) $r->industry_amount, 'count' => (int) $r->industry_count],
                'withdrawal' => ['amount' => (float) $r->withdrawal_amount, 'count' => (int) $r->withdrawal_count],
            ];
            if ($mm) {
                $entry['tax_payment'] = [
                    'amount' => (float) $r->tax_payment_amount,
                    'count'  => (int) $r->tax_payment_count,
                ];
                $entry['donation_voluntary'] = [
                    'amount' => (float) $r->donation_voluntary_amount,
                    'count'  => (int) $r->donation_voluntary_count,
                ];
            } else {
                $entry['donation'] = [
                    'amount' => (float) $r->tax_payment_amount + (float) $r->donation_voluntary_amount,
                    'count'  => (int) $r->tax_payment_count + (int) $r->donation_voluntary_count,
                ];
            }
            $byPeriod[] = $entry;
        }

        return [
            'character_id'   => $characterId,
            'corporation_id' => $corporationId,
            'months'         => $months,
            'mm_available'   => $mm,
            'by_period'      => $byPeriod,
        ];
    }

    /**
     * Raw journal entries involving the character. Pulled live (not cached)
     * because this is HR's deep-dive surface and usage is rare.
     */
    public function getCharacterEntries(int $characterId, int $corporationId, int $months = 6, float $minAmount = 0): array
    {
        $from = now()->subMonths(max(1, $months));

        $rows = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->where(function ($q) use ($characterId) {
                $q->where('first_party_id', $characterId)
                  ->orWhere('second_party_id', $characterId);
            })
            ->where('date', '>=', $from)
            ->orderBy('date', 'desc')
            ->get([
                'id', 'internal_id', 'corporation_id', 'division', 'date', 'ref_type',
                'amount', 'first_party_id', 'second_party_id', 'description',
            ]);

        $entries = [];
        foreach ($rows as $r) {
            if ($minAmount > 0 && abs((float) $r->amount) < $minAmount) {
                continue;
            }
            $classification = $this->classify($r);
            $entries[] = [
                'journal_id'      => (int) $r->id,
                'internal_id'     => (int) $r->internal_id,
                'date'            => (string) $r->date,
                'ref_type'        => (string) $r->ref_type,
                'amount'          => (float) $r->amount,
                'division'        => (int) $r->division,
                'first_party_id'  => $r->first_party_id !== null ? (int) $r->first_party_id : null,
                'second_party_id' => $r->second_party_id !== null ? (int) $r->second_party_id : null,
                'description'     => (string) $r->description,
                'bucket'          => $classification['bucket'] ?? null,
            ];
        }

        return [
            'character_id'   => $characterId,
            'corporation_id' => $corporationId,
            'months'         => $months,
            'entries'        => $entries,
        ];
    }

    /**
     * Outgoing corp wallet activity split into best-effort recipient and
     * initiator views plus an unattributed remainder.
     *
     * - by_recipient: confident. Outgoing entries whose second_party_id
     *   resolves to a known character (joined against character_infos).
     *   Operator-readable answer to "who got paid by the corp?".
     * - by_initiator: best-effort. Currently empty for most installs
     *   because CCP does not structure the acting director on most
     *   outgoing journal entries. Reserved for future expansion (e.g.
     *   description parsing, ESI member-tracking cross-reference).
     * - unattributed: outgoing entries with no usable character link.
     */
    public function getCorpOutflows(int $corporationId, int $months = 3): array
    {
        $from = now()->subMonths(max(1, $months));

        $rows = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->where('amount', '<', 0)
            ->where('date', '>=', $from)
            ->get(['ref_type', 'amount', 'first_party_id', 'second_party_id']);

        // Batch the character_infos lookup to avoid per-row queries.
        $candidateRecipients = $rows->pluck('second_party_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $knownNames = empty($candidateRecipients)
            ? collect()
            : DB::table('character_infos')
                ->whereIn('character_id', $candidateRecipients)
                ->pluck('name', 'character_id');

        $byRecipient = [];
        $unattributedAmount = 0.0;
        $unattributedCount = 0;

        foreach ($rows as $r) {
            $absAmount = abs((float) $r->amount);
            $recipient = $r->second_party_id !== null ? (int) $r->second_party_id : null;

            if ($recipient && $knownNames->has($recipient)) {
                if (! isset($byRecipient[$recipient])) {
                    $byRecipient[$recipient] = [
                        'character_id'   => $recipient,
                        'character_name' => $knownNames[$recipient],
                        'amount'         => 0.0,
                        'count'          => 0,
                    ];
                }
                $byRecipient[$recipient]['amount'] += $absAmount;
                $byRecipient[$recipient]['count'] += 1;
            } else {
                $unattributedAmount += $absAmount;
                $unattributedCount += 1;
            }
        }

        $byRecipient = array_values($byRecipient);
        usort($byRecipient, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        return [
            'corporation_id'      => $corporationId,
            'months'              => $months,
            'by_recipient'        => $byRecipient,
            'by_initiator'        => [],
            'unattributed_amount' => $unattributedAmount,
            'unattributed_count'  => $unattributedCount,
            'notes'               => 'CCP wallet journals rarely structure the acting director on outgoing entries. The initiator view is reserved for future expansion (description parsing, ESI member-tracking cross-reference).',
        ];
    }

    /**
     * Leaderboard of top contributors for a corporation in a period.
     * Used by the Director-view Top Contributors tab AND the member
     * view's leaderboard (both surfaces share this method).
     *
     * Scope rule: this method returns CURRENT corporation members only.
     * Ex-members who later moved out are excluded via the
     * character_affiliations EXISTS predicate so the leaderboard
     * answers "who is contributing among people currently in the
     * corp", not "who has ever contributed historically". A separate
     * code path (personalContribution) shows the viewer's lifetime
     * contribution across all their owned chars regardless of current
     * corp - the rules deliberately diverge between the two surfaces.
     *
     * Income-only rule: leaderboard rank + the row total are derived
     * from positive contribution buckets (ratting + mission + industry
     * + tax_payment + donation_voluntary). Withdrawal totals are kept
     * on the row as an informational field (Donation Audit reads it)
     * but never count toward the leaderboard total or the rank order.
     * Pre-fix the cache classifier already wrote total_contribution_amount
     * as income-only, but the SELECT recomputes it inline so a row that
     * snuck in with a stale or hand-edited total still gets sorted
     * correctly here.
     *
     * Rows are grouped by SeAT user's main_character_id so all alts of one
     * human aggregate into a single leaderboard row (matching the suite
     * convention; Mining Manager uses the same pattern). Each main carries
     * its per-alt breakdown in `alts` so the UI can expand and show
     * individual character contributions.
     *
     * Resolution chain per character_id:
     *   refresh_tokens.character_id -> refresh_tokens.user_id
     *   -> users.id -> users.main_character_id
     *
     * Falls back to the character_id itself when any link is missing (no
     * refresh_token row, user has no main set, character not in
     * character_infos). Those rows still appear, just ungrouped.
     */
    public function getTopContributors(int $corporationId, string $period, int $limit = 20): array
    {
        $allianceRates = $this->allianceTaxRates();
        $hasAllianceTax = $this->ratesAreNonZero($allianceRates);
        $mmAvailable = $this->isMmInstalled();

        $hasAffiliations = Schema::hasTable('character_affiliations');

        $rawRows = CharacterContribution::query()
            ->where('corporation_id', $corporationId)
            ->where('period', $period)
            // Defensive filter: NPC ids (< 90M) and the corp's own id are
            // never valid leaderboard entries. Pre-fix scans wrote some of
            // these into the cache (bounty_prizes attributed to the NPC
            // pirate, internal transfers attributed to the corp itself);
            // the cleanup migration deletes them on upgrade and the
            // classifier no longer creates them, but the SQL guards keep
            // the leaderboard clean even if a stray row appears later.
            ->where('character_id', '>=', 90000000)
            ->whereColumn('character_id', '!=', 'corporation_id')
            // Exclude any character_id that is actually a corporation_id.
            // A row keyed on a corporation_id sneaks into the cache when
            // a corporation_account_withdrawal outflow names a corp as
            // recipient (second_party_id is the recipient corp, not a
            // player). Without this filter the recipient corp would
            // appear in the Top Contributors list with a 0 income total
            // and a non-zero withdrawal total.
            //
            // NOT EXISTS instead of NOT IN — MariaDB's NOT IN against a
            // large subquery materialises the whole inner set into a
            // temp table on every outer row; NOT EXISTS short-circuits
            // on the corporation_id primary-key lookup and is dramatically
            // faster on installs with thousands of corp_infos rows.
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('corporation_infos')
                    ->whereColumn('corporation_infos.corporation_id', 'corpwalletmanager_character_contributions.character_id');
            })
            // Same defensive guard for alliance_ids - an alliance bank
            // can also be the recipient of a withdrawal.
            ->when(Schema::hasTable('alliance_infos'), function ($outer) {
                $outer->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('alliance_infos')
                        ->whereColumn('alliance_infos.alliance_id', 'corpwalletmanager_character_contributions.character_id');
                });
            })
            // Current-members-only filter: drop anyone whose
            // character_affiliations row no longer points at this corp.
            // Skipped when the table is missing (defensive fail-open;
            // matches the pattern used elsewhere in this service).
            ->when($hasAffiliations, function ($outer) use ($corporationId) {
                $outer->whereExists(function ($q) use ($corporationId) {
                    $q->select(DB::raw(1))
                        ->from('character_affiliations')
                        ->whereColumn('character_affiliations.character_id', 'corpwalletmanager_character_contributions.character_id')
                        ->where('character_affiliations.corporation_id', $corporationId);
                });
            })
            // Compute income-only total inline so a stale
            // total_contribution_amount cannot drag a non-contributing
            // row up the ladder. Rows with zero positive contribution
            // drop out via a WHERE predicate (not HAVING — MariaDB strict
            // mode rejects HAVING on non-grouping columns when there's no
            // GROUP BY, and this query is row-level, not grouped).
            ->selectRaw(
                '*, (COALESCE(ratting_amount,0) ' .
                '+ COALESCE(mission_amount,0) ' .
                '+ COALESCE(industry_amount,0) ' .
                '+ COALESCE(tax_payment_amount,0) ' .
                '+ COALESCE(donation_voluntary_amount,0)) AS income_total'
            )
            ->whereRaw(
                '(COALESCE(ratting_amount,0) ' .
                '+ COALESCE(mission_amount,0) ' .
                '+ COALESCE(industry_amount,0) ' .
                '+ COALESCE(tax_payment_amount,0) ' .
                '+ COALESCE(donation_voluntary_amount,0)) > 0'
            )
            ->orderByDesc('income_total')
            ->get();

        if ($rawRows->isEmpty()) {
            return [
                'corporation_id'      => $corporationId,
                'period'              => $period,
                'limit'               => $limit,
                'mm_available'        => $mmAvailable,
                'has_alliance_tax'    => $hasAllianceTax,
                'alliance_tax_rates'  => $allianceRates,
                'contributors'        => [],
            ];
        }

        // character_id -> main_character_id (best effort; missing entries
        // mean "no main found, treat the character as its own main").
        $rawCharacterIds = $rawRows->pluck('character_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $mainMap = $this->resolveMainCharacterMap($rawCharacterIds);

        // Group by main, aggregating buckets and collecting per-alt detail.
        // total_contribution_amount on each row + each alt is the
        // income-only sum (the SELECT computed it as `income_total`;
        // the cache's persisted total_contribution_amount column is
        // already income-only by classifier policy, so the two normally
        // match - but we trust the inline sum first).
        $grouped = [];
        foreach ($rawRows as $r) {
            $charId = (int) $r->character_id;
            $mainId = $mainMap[$charId] ?? $charId;

            $ratting      = (float) $r->ratting_amount;
            $mission      = (float) $r->mission_amount;
            $industry     = (float) $r->industry_amount;
            $tax          = (float) $r->tax_payment_amount;
            $donation     = (float) $r->donation_voluntary_amount;
            $withdrawal   = (float) ($r->withdrawal_amount ?? 0);
            // income_total comes from the SELECT alias; defensive fallback
            // to the sum of the five buckets if the alias is missing
            // (e.g. a hand-crafted call that bypassed the SELECT).
            $incomeTotal = isset($r->income_total)
                ? (float) $r->income_total
                : ($ratting + $mission + $industry + $tax + $donation);

            if (! isset($grouped[$mainId])) {
                $grouped[$mainId] = [
                    'main_character_id'         => $mainId,
                    'ratting_amount'            => 0.0,
                    'mission_amount'            => 0.0,
                    'tax_payment_amount'        => 0.0,
                    'donation_voluntary_amount' => 0.0,
                    'industry_amount'           => 0.0,
                    'withdrawal_amount'         => 0.0,
                    'total_contribution_amount' => 0.0,
                    'alt_character_ids'         => [],
                    'alts'                      => [],
                ];
            }

            $grouped[$mainId]['ratting_amount']            += $ratting;
            $grouped[$mainId]['mission_amount']            += $mission;
            $grouped[$mainId]['tax_payment_amount']        += $tax;
            $grouped[$mainId]['donation_voluntary_amount'] += $donation;
            $grouped[$mainId]['industry_amount']           += $industry;
            $grouped[$mainId]['withdrawal_amount']         += $withdrawal;
            $grouped[$mainId]['total_contribution_amount'] += $incomeTotal;
            $grouped[$mainId]['alt_character_ids'][]       = $charId;
            $grouped[$mainId]['alts'][]                    = [
                'character_id'              => $charId,
                'is_main'                   => $charId === $mainId,
                'ratting_amount'            => $ratting,
                'mission_amount'            => $mission,
                'tax_payment_amount'        => $tax,
                'donation_voluntary_amount' => $donation,
                'industry_amount'           => $industry,
                'withdrawal_amount'         => $withdrawal,
                'total_contribution_amount' => $incomeTotal,
            ];
        }

        // Resolve names for mains AND alts in one query.
        $allIds = collect($grouped)
            ->flatMap(fn ($g) => array_merge([$g['main_character_id']], $g['alt_character_ids']))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        // Layered resolver: character_infos -> corporation_infos ->
        // alliance_infos -> universe_names -> ESI fallback. Catches
        // unknown player corps that land in the withdrawal bucket as
        // legitimate recipients, and any character_infos miss falls
        // through to universe_names / ESI. ESI fallback writes back
        // into universe_names so subsequent leaderboard renders are
        // free.
        $names = collect();
        if (! empty($allIds)) {
            $resolved = app(EntityNameResolver::class)->resolve($allIds, true);
            foreach ($resolved as $id => $info) {
                $name = $info['name'] === 'Unknown' ? null : $info['name'];
                if ($name !== null && in_array($info['type'], ['corporation', 'alliance'], true)) {
                    $name .= ' (' . ucfirst($info['type']) . ')';
                }
                if ($name !== null) {
                    $names[(int) $id] = $name;
                }
            }
        }

        // Look up MM tax owed/paid for the period across every character
        // appearing in the leaderboard (mains and alts). Returns an empty
        // array when MM is absent or no invoices exist.
        $mmTax = $this->getMmTaxBatch($allIds, $period);

        // Sort + limit at the grouped level (totals across alts).
        $contributors = array_values($grouped);
        usort($contributors, fn ($a, $b) => $b['total_contribution_amount'] <=> $a['total_contribution_amount']);
        $contributors = array_slice($contributors, 0, max(1, min(100, $limit)));

        foreach ($contributors as &$c) {
            $mainId = $c['main_character_id'];
            $c['character_id']   = $mainId; // alias for UI back-compat
            $c['character_name'] = $names[$mainId] ?? ('Character ' . $mainId);
            $c['alt_count']      = max(0, count(array_unique($c['alt_character_ids'])) - 1);

            // Aggregate MM tax across (main + alts) onto the main row.
            $mainMmOwed = 0.0;
            $mainMmPaid = 0.0;

            foreach ($c['alts'] as &$alt) {
                $alt['character_name'] = $names[$alt['character_id']] ?? ('Character ' . $alt['character_id']);
                $this->applyAllianceTax($alt, $allianceRates);

                $altMm = $mmTax[$alt['character_id']] ?? ['owed' => 0.0, 'paid' => 0.0];
                $alt['mm_tax_owed'] = (float) $altMm['owed'];
                $alt['mm_tax_paid'] = (float) $altMm['paid'];
                $mainMmOwed += $alt['mm_tax_owed'];
                $mainMmPaid += $alt['mm_tax_paid'];
            }
            unset($alt);

            $this->applyAllianceTax($c, $allianceRates);
            $c['mm_tax_owed'] = $mainMmOwed;
            $c['mm_tax_paid'] = $mainMmPaid;

            usort($c['alts'], fn ($a, $b) => $b['total_contribution_amount'] <=> $a['total_contribution_amount']);
        }
        unset($c);

        return [
            'corporation_id'     => $corporationId,
            'period'             => $period,
            'limit'              => $limit,
            'mm_available'       => $mmAvailable,
            'has_alliance_tax'   => $hasAllianceTax,
            'alliance_tax_rates' => $allianceRates,
            'contributors'       => $contributors,
        ];
    }

    /**
     * Composite payload for the Director "Top Contributors" tab's two
     * supporting charts (Contribution Concentration pie + Members vs
     * External Contributors stacked bar). Returns both shapes in one
     * call so the frontend issues a single round trip.
     *
     * Shares the leaderboard's eligibility predicates so the charts
     * reconcile with the table on the same screen:
     *   character_id >= 90_000_000 (player range, never an NPC pirate)
     *   NOT IN corporation_infos.corporation_id (drops corp self-rows
     *     that sneak in via withdrawal recipient = corp)
     *   NOT IN alliance_infos.alliance_id (same defensive guard for
     *     alliance banks; skipped when alliance_infos is absent)
     *   income_total > 0 (ratting + mission + industry + tax_payment
     *     + donation_voluntary; withdrawal_amount intentionally
     *     excluded — withdrawal is outflow, not contribution)
     *
     * `concentration` buckets the current-period eligible rows into
     * Top 1 / Top 2-5 / Top 6-10 / Everyone else ordered by
     * income_total desc. The pie answers "is income concentrated in a
     * handful of mains, or spread across the corp?". The four buckets
     * mirror the standard Pareto split a director scans at a glance.
     *
     * `member_vs_external` runs the same eligibility query for the
     * current AND the immediately prior calendar month, then splits
     * each row's income into a "member" vs "external" half based on
     * whether the character_affiliations row still points at this
     * corp. Members are characters currently in the corp; external
     * contributors are characters whose contribution-cache row
     * survives a backfill but whose affiliation has since moved out
     * (or who were never in the corp but paid industry tax / dropped
     * a donation while running infrastructure here). Two adjacent
     * months on the bar chart make the "who is carrying us this
     * period" story readable at a glance — a rising external bar
     * relative to members is a recruiting signal worth surfacing.
     *
     * Defensive fail-open: when character_affiliations is missing
     * EVERY eligible row is treated as a member (matches the
     * leaderboard's whereExists guard, which skips its current-member
     * filter on the same table-missing condition). When alliance_infos
     * is missing the NOT-IN guard for alliance ids is skipped.
     *
     * Return shape:
     *   corporation_id, period,
     *   concentration: {
     *     total: float,
     *     buckets: [
     *       {label: string, amount: float, pct: float, count: int}, ...
     *     ]                                    // 4 buckets, always
     *   },
     *   member_vs_external: {
     *     current: {period, members_total, members_count,
     *               external_total, external_count},
     *     prior:   {period, members_total, members_count,
     *               external_total, external_count}
     *   }
     */
    public function getContributorMix(int $corpId, string $period): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = now()->format('Y-m');
        }

        $priorPeriod = $this->priorPeriod($period);

        // Pull the same view of the cache the leaderboard uses, for the
        // CURRENT period. Used both for the concentration buckets and
        // for the current-side of the member-vs-external split.
        $currentRows = $this->eligibleRowsForPeriod($corpId, $period);
        $priorRows   = $this->eligibleRowsForPeriod($corpId, $priorPeriod);

        return [
            'corporation_id'     => $corpId,
            'period'             => $period,
            'concentration'      => $this->buildConcentration($currentRows),
            'member_vs_external' => [
                'current' => $this->splitMemberVsExternal($corpId, $period, $currentRows),
                'prior'   => $this->splitMemberVsExternal($corpId, $priorPeriod, $priorRows),
            ],
        ];
    }

    /**
     * Cache-table rows for a (corp, period) that pass every leaderboard
     * predicate. Returned as a Collection of stdClass with one extra
     * computed field: `income_total` = ratting + mission + industry
     * + tax_payment + donation_voluntary. Ordered by income_total desc
     * so the concentration buckets can take()/skip() into Top 1 /
     * Top 2-5 / Top 6-10 / rest without an additional sort.
     *
     * Withdrawal intentionally excluded from income_total — it is
     * outflow, not contribution. This matches the leaderboard's
     * income-only WHERE predicate.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function eligibleRowsForPeriod(int $corpId, string $period)
    {
        $hasAlliance = Schema::hasTable('alliance_infos');
        $hasAffil    = Schema::hasTable('character_affiliations');

        $incomeExpr = '(COALESCE(ratting_amount,0) '
            . '+ COALESCE(mission_amount,0) '
            . '+ COALESCE(industry_amount,0) '
            . '+ COALESCE(tax_payment_amount,0) '
            . '+ COALESCE(donation_voluntary_amount,0))';

        return CharacterContribution::query()
            ->where('corporation_id', $corpId)
            ->where('period', $period)
            ->where('character_id', '>=', 90000000)
            ->whereColumn('character_id', '!=', 'corporation_id')
            // NPC + corp-self guard: never let a withdrawal recipient
            // that happens to be a corporation_id show up. NOT EXISTS
            // is faster than NOT IN on a large subquery (same reason
            // as getTopContributors).
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('corporation_infos')
                    ->whereColumn('corporation_infos.corporation_id', 'corpwalletmanager_character_contributions.character_id');
            })
            // Same guard for alliance ids when alliance_infos is
            // present. Skipped when the table is missing so a clean
            // install without the SDE alliance table doesn't crash.
            ->when($hasAlliance, function ($outer) {
                $outer->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('alliance_infos')
                        ->whereColumn('alliance_infos.alliance_id', 'corpwalletmanager_character_contributions.character_id');
                });
            })
            // Note: the leaderboard also applies a current-corp-member
            // whereExists here. We deliberately omit it on this read
            // because member_vs_external NEEDS the external rows for
            // its split — the leaderboard discards them. The
            // splitMemberVsExternal() helper rebuilds the same
            // current-member set in a single batch query and tags each
            // row at the PHP level.
            ->selectRaw('*, ' . $incomeExpr . ' AS income_total')
            ->whereRaw($incomeExpr . ' > 0')
            ->orderByDesc('income_total')
            ->get();
    }

    /**
     * Group the eligible-row collection into Top 1 / Top 2-5 /
     * Top 6-10 / Everyone else and emit per-bucket sum / count / pct
     * of the corp-wide grand total.
     *
     * The four buckets are always present even when empty — the UI
     * relies on the fixed shape to render four pie slices regardless
     * of the corp size. An empty bucket renders as a zero-value slice
     * (the frontend filters those out for the chart but keeps them in
     * the Story line).
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function buildConcentration($rows): array
    {
        $total = 0.0;
        foreach ($rows as $r) {
            $total += (float) ($r->income_total ?? 0);
        }

        // Pre-allocate the four buckets so the shape is fixed.
        $buckets = [
            ['label' => 'Top 1',         'amount' => 0.0, 'pct' => 0.0, 'count' => 0],
            ['label' => 'Top 2-5',       'amount' => 0.0, 'pct' => 0.0, 'count' => 0],
            ['label' => 'Top 6-10',      'amount' => 0.0, 'pct' => 0.0, 'count' => 0],
            ['label' => 'Everyone else', 'amount' => 0.0, 'pct' => 0.0, 'count' => 0],
        ];

        foreach ($rows->values() as $idx => $row) {
            $amount = (float) ($row->income_total ?? 0);
            $bucketIdx = ($idx === 0) ? 0
                : (($idx >= 1 && $idx <= 4) ? 1
                : (($idx >= 5 && $idx <= 9) ? 2 : 3));

            $buckets[$bucketIdx]['amount'] += $amount;
            $buckets[$bucketIdx]['count']  += 1;
        }

        if ($total > 0.0) {
            foreach ($buckets as &$b) {
                $b['pct'] = round(($b['amount'] / $total) * 100.0, 1);
            }
            unset($b);
        }

        return [
            'total'   => $total,
            'buckets' => $buckets,
        ];
    }

    /**
     * Split the eligible-row collection into members (currently in the
     * corp per character_affiliations) and externals (no row, or row
     * pointing elsewhere). Returns per-side sum and headcount.
     *
     * One batch query against character_affiliations builds the set
     * of currently-in-corp character_ids; the rows are then tagged
     * at the PHP level. Fail-open: when character_affiliations is
     * missing every row is treated as a member (matches the
     * leaderboard's whereExists fail-open behaviour).
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function splitMemberVsExternal(int $corpId, string $period, $rows): array
    {
        $hasAffil = Schema::hasTable('character_affiliations');

        $memberSet = [];
        if ($hasAffil && $rows->isNotEmpty()) {
            $characterIds = $rows->pluck('character_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $memberIds = DB::table('character_affiliations')
                ->whereIn('character_id', $characterIds)
                ->where('corporation_id', $corpId)
                ->pluck('character_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $memberSet = array_flip($memberIds);
        }

        $membersTotal  = 0.0;
        $membersCount  = 0;
        $externalTotal = 0.0;
        $externalCount = 0;

        foreach ($rows as $r) {
            $charId = (int) $r->character_id;
            $amount = (float) ($r->income_total ?? 0);

            // Fail-open: when the affiliations table is missing
            // $memberSet is empty AND $hasAffil is false, so treat
            // everyone as a member rather than as external (matches
            // the leaderboard's behaviour and avoids surprising the
            // operator with a giant external bar that only means
            // "table missing").
            $isMember = ! $hasAffil || isset($memberSet[$charId]);

            if ($isMember) {
                $membersTotal += $amount;
                $membersCount += 1;
            } else {
                $externalTotal += $amount;
                $externalCount += 1;
            }
        }

        return [
            'period'         => $period,
            'members_total'  => $membersTotal,
            'members_count'  => $membersCount,
            'external_total' => $externalTotal,
            'external_count' => $externalCount,
        ];
    }

    /**
     * Per-activity-bucket aggregate for the Director "Profit Attribution"
     * tab. Answers "where did the corp's profit come from this period?"
     * by ranking activity buckets (ratting / mission / industry /
     * tax_payment / donation_voluntary), not members.
     *
     * Returns the same defensive-guarded view of the cache that
     * getTopContributors uses (character_id >= 90M, != corp_id) so the
     * two tabs stay reconcilable. Also pulls the prior calendar month
     * via the same aggregation so each row carries a trend_vs_prior_pct.
     *
     * MM-conditional shape mirrors getCharacterByCategory: when MM is
     * installed tax_payment and donation_voluntary are surfaced
     * separately; when MM is absent they are merged into a single
     * 'donation' bucket on the reading side.
     *
     * Return shape:
     *   corporation_id, period, mm_available,
     *   total_contribution, prior_total_contribution,
     *   by_activity: [
     *     {activity, total, member_count, avg_per_member,
     *      pct_of_total, trend_vs_prior_pct}, ...
     *   ]
     * by_activity is sorted by total descending. trend_vs_prior_pct is
     * null when the prior period had zero amount in that bucket; capped
     * to ±1000% otherwise to mirror getCharacterTrend's blow-up guard.
     */
    public function getProfitAttribution(int $corporationId, string $period): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = now()->format('Y-m');
        }

        $mmAvailable = $this->isMmInstalled();
        $priorPeriod = $this->priorPeriod($period);

        $current = $this->aggregatePerActivity($corporationId, $period, $mmAvailable);
        $prior   = $this->aggregatePerActivity($corporationId, $priorPeriod, $mmAvailable);

        $totalContribution = 0.0;
        foreach ($current as $row) {
            $totalContribution += $row['total'];
        }
        $priorTotalContribution = 0.0;
        foreach ($prior as $row) {
            $priorTotalContribution += $row['total'];
        }

        $byActivity = [];
        foreach ($current as $activity => $row) {
            $total       = (float) $row['total'];
            $memberCount = (int)   $row['member_count'];
            $avgPerMember = $memberCount > 0 ? $total / $memberCount : 0.0;
            $pctOfTotal  = $totalContribution > 0.0
                ? ($total / $totalContribution) * 100.0
                : 0.0;

            $priorTotal = (float) ($prior[$activity]['total'] ?? 0.0);
            $trendPct   = null;
            if ($priorTotal > 0.0) {
                $pct = (($total - $priorTotal) / $priorTotal) * 100.0;
                // Cap to ±1000% (matches getCharacterTrend's blow-up
                // guard for tiny-but-non-zero prior windows).
                $trendPct = max(-1000.0, min(1000.0, $pct));
            }

            $byActivity[] = [
                'activity'           => $activity,
                'total'              => $total,
                'member_count'       => $memberCount,
                'avg_per_member'     => (float) $avgPerMember,
                'pct_of_total'       => (float) $pctOfTotal,
                'trend_vs_prior_pct' => $trendPct,
            ];
        }

        // Sort by total descending so the pie + table render in
        // largest-slice-first order.
        usort($byActivity, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'corporation_id'           => $corporationId,
            'period'                   => $period,
            'mm_available'             => $mmAvailable,
            'total_contribution'       => (float) $totalContribution,
            'prior_total_contribution' => (float) $priorTotalContribution,
            'by_activity'              => $byActivity,
        ];
    }

    /**
     * Trailing-N-months profit-attribution trend for the stacked-bar
     * chart underneath the per-period Profit Attribution pie + table.
     * Counterpart to ExpenseAttributionService::getTrend so the two
     * tabs share an identical hybrid (snapshot + trend) shape.
     *
     * For each of the trailing N periods, runs the same per-activity
     * aggregation as getProfitAttribution (sans the trend_vs_prior_pct
     * derivation), then pivots so each activity bucket has a flat
     * array of N totals matching the periods array order (oldest
     * first). Sorts buckets by trailing-window total descending so
     * the largest bucket sits at the bottom of the stack.
     *
     * MM-conditional shape matches getProfitAttribution: tax_payment
     * + donation_voluntary surface separately when MM is installed,
     * merged into a single 'donation' bucket otherwise. Defensive
     * guards (character_id >= 90M, != corporation_id) carry through
     * from aggregatePerActivity.
     *
     * Return shape:
     *   corporation_id, months, mm_available,
     *   periods: ['YYYY-MM', ...],   // oldest first
     *   categories: [
     *     {category, label, series: [float, ...], total},
     *     ...                         // sorted by trailing-window total desc
     *   ]
     */
    public function getProfitAttributionTrend(int $corporationId, int $months = 12): array
    {
        $months  = max(1, min(24, $months));
        $periods = $this->trendPeriodsOldestFirst($months);

        $mmAvailable = $this->isMmInstalled();

        // Canonical labels per bucket - mirrors the JS CWM_PA_LABELS
        // map so the trend chart reads consistently with the pie.
        $labels = [
            'ratting'            => 'Ratting',
            'mission'            => 'Mission',
            'industry'           => 'Industry',
            'tax_payment'        => 'Mining Tax',
            'donation_voluntary' => 'Voluntary Donations',
            'donation'           => 'Donations',
        ];

        // Bucket key set we report. Drives the [bucket => [period => total]]
        // map allocation and the iteration order.
        $bucketKeys = $mmAvailable
            ? ['ratting', 'mission', 'industry', 'tax_payment', 'donation_voluntary']
            : ['ratting', 'mission', 'industry', 'donation'];

        $byBucketPeriod = [];
        foreach ($bucketKeys as $bucket) {
            $byBucketPeriod[$bucket] = array_fill_keys($periods, 0.0);
        }

        foreach ($periods as $period) {
            $aggregate = $this->aggregatePerActivity($corporationId, $period, $mmAvailable);
            foreach ($bucketKeys as $bucket) {
                $byBucketPeriod[$bucket][$period] = (float) ($aggregate[$bucket]['total'] ?? 0.0);
            }
        }

        $categories = [];
        foreach ($bucketKeys as $bucket) {
            $series = [];
            $total  = 0.0;
            foreach ($periods as $p) {
                $value = (float) ($byBucketPeriod[$bucket][$p] ?? 0.0);
                $series[] = $value;
                $total   += $value;
            }
            $categories[] = [
                'category' => $bucket,
                'label'    => $labels[$bucket] ?? $bucket,
                'series'   => $series,
                'total'    => $total,
            ];
        }

        usort($categories, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'corporation_id' => $corporationId,
            'months'         => $months,
            'mm_available'   => $mmAvailable,
            'periods'        => $periods,
            'categories'     => $categories,
        ];
    }

    /**
     * Trailing N period strings, OLDEST FIRST so the trend chart
     * reads left-to-right naturally. Matches AllianceTaxService and
     * ExpenseAttributionService conventions; differs from
     * periodsForLastMonths() (newest-first) used by the HR analytics
     * methods.
     *
     * @return array<int,string>
     */
    private function trendPeriodsOldestFirst(int $months): array
    {
        $months = max(1, $months);
        $now = now();
        $periods = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $periods[] = $now->copy()->subMonths($i)->format('Y-m');
        }
        return $periods;
    }

    /**
     * Per-activity totals + distinct-member counts for one (corp, period).
     * Returns an associative array keyed by activity name; values are
     * ['total' => float, 'member_count' => int].
     *
     * Applies the same defensive guards as getTopContributors so the two
     * views agree. When MM is installed, tax_payment + donation_voluntary
     * are reported separately; when MM is absent they are merged into a
     * single 'donation' bucket (matching the convention in
     * getCharacterByCategory).
     */
    private function aggregatePerActivity(int $corporationId, string $period, bool $mmAvailable): array
    {
        $row = CharacterContribution::query()
            ->where('corporation_id', $corporationId)
            ->where('period', $period)
            ->where('character_id', '>=', 90000000)
            ->whereColumn('character_id', '!=', 'corporation_id')
            ->selectRaw(
                'SUM(ratting_amount) AS ratting_total, '
                . 'COUNT(DISTINCT CASE WHEN ratting_amount > 0 THEN character_id END) AS ratting_members, '
                . 'SUM(mission_amount) AS mission_total, '
                . 'COUNT(DISTINCT CASE WHEN mission_amount > 0 THEN character_id END) AS mission_members, '
                . 'SUM(industry_amount) AS industry_total, '
                . 'COUNT(DISTINCT CASE WHEN industry_amount > 0 THEN character_id END) AS industry_members, '
                . 'SUM(tax_payment_amount) AS tax_total, '
                . 'COUNT(DISTINCT CASE WHEN tax_payment_amount > 0 THEN character_id END) AS tax_members, '
                . 'SUM(donation_voluntary_amount) AS donation_total, '
                . 'COUNT(DISTINCT CASE WHEN donation_voluntary_amount > 0 THEN character_id END) AS donation_members'
            )
            ->first();

        $result = [
            'ratting' => [
                'total'        => (float) ($row->ratting_total ?? 0),
                'member_count' => (int)   ($row->ratting_members ?? 0),
            ],
            'mission' => [
                'total'        => (float) ($row->mission_total ?? 0),
                'member_count' => (int)   ($row->mission_members ?? 0),
            ],
            'industry' => [
                'total'        => (float) ($row->industry_total ?? 0),
                'member_count' => (int)   ($row->industry_members ?? 0),
            ],
        ];

        if ($mmAvailable) {
            $result['tax_payment'] = [
                'total'        => (float) ($row->tax_total ?? 0),
                'member_count' => (int)   ($row->tax_members ?? 0),
            ];
            $result['donation_voluntary'] = [
                'total'        => (float) ($row->donation_total ?? 0),
                'member_count' => (int)   ($row->donation_members ?? 0),
            ];
        } else {
            // Member count must dedupe characters who contributed to
            // either bucket, not naively sum the two counts. Pull the
            // distinct id list when MM is absent so the merged bucket's
            // member_count is correct.
            $mergedMembers = CharacterContribution::query()
                ->where('corporation_id', $corporationId)
                ->where('period', $period)
                ->where('character_id', '>=', 90000000)
                ->whereColumn('character_id', '!=', 'corporation_id')
                ->where(function ($q) {
                    $q->where('tax_payment_amount', '>', 0)
                      ->orWhere('donation_voluntary_amount', '>', 0);
                })
                ->distinct()
                ->count('character_id');

            $result['donation'] = [
                'total'        => (float) (($row->tax_total ?? 0) + ($row->donation_total ?? 0)),
                'member_count' => (int) $mergedMembers,
            ];
        }

        return $result;
    }

    /**
     * "YYYY-MM" of the calendar month immediately before $period. Used
     * by getProfitAttribution to derive trend_vs_prior_pct.
     */
    private function priorPeriod(string $period): string
    {
        // Period validated upstream; safe to parse.
        [$y, $m] = array_map('intval', explode('-', $period));
        $first = \Carbon\Carbon::createFromDate($y, $m, 1);
        return $first->copy()->subMonth()->format('Y-m');
    }

    /**
     * Read the four alliance-tax percentage settings. Each is a float in
     * [0, 100] (0 means "do not apply tax for this bucket"). Stored as
     * string in corpwalletmanager_settings, parsed once per request.
     */
    private function allianceTaxRates(): array
    {
        return [
            'ratting'            => max(0.0, min(100.0, Settings::getFloatSetting('alliance_tax_ratting_pct', 0.0))),
            'mission'            => max(0.0, min(100.0, Settings::getFloatSetting('alliance_tax_mission_pct', 0.0))),
            'tax_payment'        => max(0.0, min(100.0, Settings::getFloatSetting('alliance_tax_tax_payment_pct', 0.0))),
            'donation_voluntary' => max(0.0, min(100.0, Settings::getFloatSetting('alliance_tax_donation_voluntary_pct', 0.0))),
            'industry'           => max(0.0, min(100.0, Settings::getFloatSetting('alliance_tax_industry_pct', 0.0))),
        ];
    }

    /** Any rate > 0? Used to decide whether to render the after-tax columns. */
    private function ratesAreNonZero(array $rates): bool
    {
        foreach ($rates as $r) {
            if ((float) $r > 0.0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Compute alliance_tax_amount + net_to_corp_amount for one row
     * (in place). Applies the per-bucket rates to the per-bucket gross
     * amounts, sums to get the total tax cut, and subtracts that from the
     * gross total to get the net the corp keeps from this contributor.
     */
    private function applyAllianceTax(array &$item, array $rates): void
    {
        $tax = ((float) $item['ratting_amount']            * (float) $rates['ratting']            / 100.0)
             + ((float) $item['mission_amount']            * (float) $rates['mission']            / 100.0)
             + ((float) $item['tax_payment_amount']        * (float) $rates['tax_payment']        / 100.0)
             + ((float) $item['donation_voluntary_amount'] * (float) $rates['donation_voluntary'] / 100.0)
             + ((float) ($item['industry_amount'] ?? 0)    * (float) $rates['industry']           / 100.0);

        $item['alliance_tax_amount'] = $tax;
        $item['net_to_corp_amount']  = (float) $item['total_contribution_amount'] - $tax;
    }

    /**
     * Look up Mining Manager tax billing per character for the given
     * monthly period (CWM's "YYYY-MM" string).
     *
     * Returns [character_id => ['owed' => float, 'paid' => float]] for
     * characters with MM invoices whose period_start falls inside the
     * calendar month. Returns an empty array when MM is absent or no
     * invoices exist.
     *
     * Edge case: weekly-billing periods crossing a month boundary are
     * attributed to the month their period_start is in; a week
     * starting Apr 27 ending May 3 lands in April, not May. That is
     * acceptable noise for a compliance-at-a-glance display, and
     * matches how the rest of the suite groups by period_start.
     *
     * @param  array<int,int>  $characterIds
     */
    public function getMmTaxBatch(array $characterIds, string $period): array
    {
        if (! $this->isMmInstalled() || empty($characterIds)) {
            return [];
        }

        try {
            $monthStart = $period . '-01';
            $monthEnd   = date('Y-m-t', strtotime($monthStart));

            $rows = DB::table('mining_taxes')
                ->whereIn('character_id', $characterIds)
                ->whereBetween('period_start', [$monthStart, $monthEnd])
                ->selectRaw('character_id, SUM(amount_owed) AS owed, SUM(amount_paid) AS paid')
                ->groupBy('character_id')
                ->get();

            $result = [];
            foreach ($rows as $r) {
                $result[(int) $r->character_id] = [
                    'owed' => (float) ($r->owed ?? 0),
                    'paid' => (float) ($r->paid ?? 0),
                ];
            }
            return $result;
        } catch (\Throwable $e) {
            Log::warning('[Corp Wallet Manager] ContributionService: MM tax-batch lookup failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Build a [character_id => main_character_id] map for a batch of IDs
     * using the canonical SeAT chain: refresh_tokens -> users.
     *
     * Characters with no refresh-token link or whose user has no
     * main_character_id set are omitted from the map; callers should fall
     * back to using the character_id as its own main.
     *
     * @param  array<int,int>  $characterIds
     * @return array<int,int>
     */
    private function resolveMainCharacterMap(array $characterIds): array
    {
        if (empty($characterIds)) {
            return [];
        }

        $charToUser = DB::table('refresh_tokens')
            ->whereIn('character_id', $characterIds)
            ->whereNull('deleted_at')
            ->pluck('user_id', 'character_id');

        if ($charToUser->isEmpty()) {
            return [];
        }

        $userToMain = DB::table('users')
            ->whereIn('id', $charToUser->values()->unique()->all())
            ->whereNotNull('main_character_id')
            ->pluck('main_character_id', 'id');

        $result = [];
        foreach ($charToUser as $characterId => $userId) {
            if (isset($userToMain[$userId])) {
                $result[(int) $characterId] = (int) $userToMain[$userId];
            }
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // HR Manager analytics methods
    // ------------------------------------------------------------------
    //
    // These six methods power HR Manager's per-character analytics tabs.
    // Each is a pure read aggregation over the precomputed
    // corpwalletmanager_character_contributions cache (plus, for the tax
    // compliance method, Mining Manager's mining_taxes table). None of
    // them write state; none of them require MM except where explicitly
    // noted. Methods share the YYYY-MM period vocabulary used elsewhere
    // in this service.

    /**
     * Monthly contribution trend metrics for a character over the
     * trailing N months.
     *
     * Builds a dense series (zero-filled for months with no cache row),
     * then computes velocity (mean), linear-regression slope across the
     * series, a recent-vs-prior 3-month comparison, and the latest /
     * oldest amounts. Slope uses the closed-form OLS formula
     *   slope = (n*Σxy - Σx*Σy) / (n*Σx² - (Σx)²)
     * with x as the time index 0..n-1 in chronological order (oldest =
     * 0). recent_vs_prior_pct is capped to ±1000% to avoid blow-ups when
     * the prior 3-month window is tiny but non-zero; it is null when the
     * prior window is exactly zero.
     *
     * Return shape:
     *   character_id, corporation_id, months, avg_velocity, slope,
     *   recent_3mo_avg, prior_3mo_avg, recent_vs_prior_pct,
     *   latest_amount, oldest_amount,
     *   series: [{period, amount}, ...] newest-first
     */
    public function getCharacterTrend(int $characterId, int $corporationId, int $months = 6): array
    {
        $periods = $this->periodsForLastMonths($months);

        $rows = CharacterContribution::query()
            ->where('character_id', $characterId)
            ->where('corporation_id', $corporationId)
            ->whereIn('period', $periods)
            ->get(['period', 'total_contribution_amount']);

        $byPeriod = [];
        foreach ($rows as $r) {
            $byPeriod[$r->period] = (float) $r->total_contribution_amount;
        }

        // $periods is newest-first; build the series in that order for
        // the public return shape, but reverse a copy for the regression
        // so x=0 is the oldest sample (the natural "time index" reading).
        $series = [];
        foreach ($periods as $p) {
            $series[] = [
                'period' => $p,
                'amount' => $byPeriod[$p] ?? 0.0,
            ];
        }

        $amountsOldestFirst = array_reverse(array_column($series, 'amount'));
        $n = count($amountsOldestFirst);

        $sum = array_sum($amountsOldestFirst);
        $avgVelocity = $n > 0 ? $sum / $n : 0.0;

        $slope = 0.0;
        if ($n >= 2) {
            $sumX = 0.0;
            $sumY = 0.0;
            $sumXY = 0.0;
            $sumXX = 0.0;
            foreach ($amountsOldestFirst as $i => $y) {
                $x = (float) $i;
                $sumX  += $x;
                $sumY  += $y;
                $sumXY += $x * $y;
                $sumXX += $x * $x;
            }
            $denom = ($n * $sumXX) - ($sumX * $sumX);
            if ($denom != 0.0) {
                $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denom;
            }
        }

        // Series is newest-first: the first 3 entries are the most
        // recent months, the next 3 are the prior window.
        $recentSlice = array_slice($series, 0, 3);
        $priorSlice  = array_slice($series, 3, 3);

        $recent3moAvg = ! empty($recentSlice)
            ? array_sum(array_column($recentSlice, 'amount')) / count($recentSlice)
            : 0.0;
        $prior3moAvg = ! empty($priorSlice)
            ? array_sum(array_column($priorSlice, 'amount')) / count($priorSlice)
            : 0.0;

        $recentVsPriorPct = null;
        if ($prior3moAvg != 0.0) {
            $pct = ($recent3moAvg / $prior3moAvg) - 1.0;
            // Cap to ±10 (±1000%) to keep the metric usable when the
            // prior window is tiny but non-zero.
            $recentVsPriorPct = max(-10.0, min(10.0, $pct));
        }

        $latestAmount = $series[0]['amount'] ?? 0.0;
        $oldestAmount = $series[count($series) - 1]['amount'] ?? 0.0;

        return [
            'character_id'        => $characterId,
            'corporation_id'      => $corporationId,
            'months'              => $months,
            'avg_velocity'        => (float) $avgVelocity,
            'slope'               => (float) $slope,
            'recent_3mo_avg'      => (float) $recent3moAvg,
            'prior_3mo_avg'       => (float) $prior3moAvg,
            'recent_vs_prior_pct' => $recentVsPriorPct,
            'latest_amount'       => (float) $latestAmount,
            'oldest_amount'       => (float) $oldestAmount,
            'series'              => $series,
        ];
    }

    /**
     * Months in which the character had zero contribution.
     *
     * Walks the trailing N period list and marks each "active" (cache
     * row exists with total_contribution_amount > 0) or "gap" (no row,
     * or a row whose total is zero). Computes the longest consecutive
     * run of gaps and the first / last active period for sparkline-
     * style HR summaries.
     *
     * Return shape:
     *   character_id, corporation_id, months_analyzed,
     *   gap_count, longest_gap_months, months_with_activity,
     *   last_active_period, first_active_period,
     *   gaps: [{period, active}, ...] newest-first
     */
    public function getActivityGaps(int $characterId, int $corporationId, int $months = 12): array
    {
        $periods = $this->periodsForLastMonths($months);

        $rows = CharacterContribution::query()
            ->where('character_id', $characterId)
            ->where('corporation_id', $corporationId)
            ->whereIn('period', $periods)
            ->where('total_contribution_amount', '>', 0)
            ->pluck('total_contribution_amount', 'period');

        $gaps = [];
        $gapCount = 0;
        $monthsWithActivity = 0;
        $currentRun = 0;
        $longestRun = 0;
        $activePeriods = [];

        foreach ($periods as $p) {
            $active = isset($rows[$p]);
            $gaps[] = [
                'period' => $p,
                'active' => $active,
            ];

            if ($active) {
                $monthsWithActivity++;
                $activePeriods[] = $p;
                $currentRun = 0;
            } else {
                $gapCount++;
                $currentRun++;
                if ($currentRun > $longestRun) {
                    $longestRun = $currentRun;
                }
            }
        }

        // Active periods were collected in newest-first order (matching
        // $periods); sort lexicographically to derive first/last.
        sort($activePeriods);
        $firstActive = ! empty($activePeriods) ? $activePeriods[0] : null;
        $lastActive  = ! empty($activePeriods) ? $activePeriods[count($activePeriods) - 1] : null;

        return [
            'character_id'         => $characterId,
            'corporation_id'       => $corporationId,
            'months_analyzed'      => count($periods),
            'gap_count'            => $gapCount,
            'longest_gap_months'   => $longestRun,
            'months_with_activity' => $monthsWithActivity,
            'last_active_period'   => $lastActive,
            'first_active_period'  => $firstActive,
            'gaps'                 => $gaps,
        ];
    }

    /**
     * Net position over the trailing N months: total contributed minus
     * total withdrawn for the character + corp.
     *
     * Useful for the HR "is this member net-positive?" question without
     * pulling the full bucket breakdown. Ratio is null when contribution
     * is zero (avoids divide-by-zero noise in the JSON shape).
     *
     * Return shape:
     *   character_id, corporation_id, months,
     *   total_contributed, total_withdrawn, net_position, is_net_positive,
     *   withdrawal_to_contribution_ratio
     */
    public function getNetPosition(int $characterId, int $corporationId, int $months = 6): array
    {
        $periods = $this->periodsForLastMonths($months);

        $row = CharacterContribution::query()
            ->where('character_id', $characterId)
            ->where('corporation_id', $corporationId)
            ->whereIn('period', $periods)
            ->selectRaw(
                'SUM(total_contribution_amount) AS contributed, ' .
                'SUM(withdrawal_amount) AS withdrawn'
            )
            ->first();

        $contributed = (float) ($row->contributed ?? 0);
        $withdrawn   = (float) ($row->withdrawn ?? 0);
        $net         = $contributed - $withdrawn;
        $ratio       = $contributed != 0.0 ? $withdrawn / $contributed : null;

        return [
            'character_id'                     => $characterId,
            'corporation_id'                   => $corporationId,
            'months'                           => $months,
            'total_contributed'                => $contributed,
            'total_withdrawn'                  => $withdrawn,
            'net_position'                     => $net,
            'is_net_positive'                  => $net >= 0.0,
            'withdrawal_to_contribution_ratio' => $ratio,
        ];
    }

    /**
     * Corp-wide per-member financial roll-up. One grouped query over the
     * contribution cache returns EVERY member with wallet activity in the
     * corp (registered or not) — the all-member counterpart to the
     * per-character getNetPosition / getLifetimeSummary calls HR Manager
     * already consumes. months=0 aggregates all-time; >0 windows by period.
     *
     * Each member is optionally enriched with mining-tax compliance from MM's
     * mining_taxes (best-effort: null when MM is absent). Semantics match the
     * per-character calls: net = contributed - withdrawn; compliance = paid /
     * owed.
     *
     * Return: ['available' => bool, 'corporation_id' => int, 'members' => [
     *   ['character_id', 'lifetime_contribution', 'ratting_income',
     *    'tax_paid', 'withdrawn', 'net_position', 'active_months',
     *    'tax_compliance_pct'], ... ]]
     */
    public function getCorpMemberSummary(int $corporationId, int $months = 0): array
    {
        if (! Schema::hasTable('corpwalletmanager_character_contributions')) {
            return ['available' => false, 'corporation_id' => $corporationId, 'members' => []];
        }

        $query = CharacterContribution::query()
            ->where('corporation_id', $corporationId)
            ->selectRaw(
                'character_id, ' .
                'SUM(total_contribution_amount) AS lifetime_contribution, ' .
                'SUM(ratting_amount) AS ratting, ' .
                'SUM(mission_amount) AS mission, ' .
                'SUM(tax_payment_amount) AS tax_paid, ' .
                'SUM(withdrawal_amount) AS withdrawn, ' .
                'COUNT(DISTINCT period) AS active_months'
            )
            ->groupBy('character_id');

        if ($months > 0) {
            $query->whereIn('period', $this->periodsForLastMonths($months));
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            return ['available' => true, 'corporation_id' => $corporationId, 'members' => []];
        }

        $compliance = $this->corpMemberComplianceMap(
            $rows->pluck('character_id')->map(fn ($id) => (int) $id)->all(),
            $months > 0 ? $months : 6
        );

        $members = $rows->map(function ($r) use ($compliance) {
            $cid = (int) $r->character_id;
            $contributed = (float) $r->lifetime_contribution;
            $withdrawn = (float) $r->withdrawn;
            return [
                'character_id'          => $cid,
                'lifetime_contribution' => $contributed,
                'ratting_income'        => (float) $r->ratting + (float) $r->mission,
                'tax_paid'              => (float) $r->tax_paid,
                'withdrawn'             => $withdrawn,
                'net_position'          => $contributed - $withdrawn,
                'active_months'         => (int) $r->active_months,
                'tax_compliance_pct'    => $compliance[$cid] ?? null,
            ];
        })->values()->all();

        return ['available' => true, 'corporation_id' => $corporationId, 'members' => $members];
    }

    /**
     * Bulk mining-tax compliance per character from MM's mining_taxes
     * (owed vs paid over the trailing window). Computes the same
     * overall_compliance_pct getCharacterTaxCompliance returns, for many
     * characters in one query. Empty map when MM is absent.
     *
     * @param  array<int>  $characterIds
     * @return array<int, float>  character_id => compliance %
     */
    private function corpMemberComplianceMap(array $characterIds, int $months): array
    {
        if (empty($characterIds) || ! $this->isMmInstalled() || ! Schema::hasTable('mining_taxes')) {
            return [];
        }

        $periods = $this->periodsForLastMonths($months);
        if (empty($periods)) {
            return [];
        }
        // periods are 'Y-m' strings; the lexicographic min is the earliest.
        $earliest = min($periods) . '-01';

        $rows = DB::table('mining_taxes')
            ->whereIn('character_id', $characterIds)
            ->where('period_start', '>=', $earliest)
            ->selectRaw('character_id, SUM(amount_owed) AS owed, SUM(amount_paid) AS paid')
            ->groupBy('character_id')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $owed = (float) $r->owed;
            $paid = (float) $r->paid;
            $map[(int) $r->character_id] = $owed > 0.0 ? min(100.0, ($paid / $owed) * 100.0) : 100.0;
        }
        return $map;
    }

    /**
     * All-time aggregation across every period the cache holds for this
     * character + corp. No month filter — HR uses this for the "tenure"
     * panel on a member's profile.
     *
     * first/last_contribution_period are taken from rows with positive
     * totals; months_in_cache includes the zero-total rows the
     * incremental compute might have stamped (e.g. a month with only
     * withdrawals). first/last are null when the character has no
     * positive-contribution months at all.
     *
     * Return shape:
     *   character_id, corporation_id,
     *   lifetime_total_contributed, lifetime_total_withdrawn,
     *   months_active, months_in_cache,
     *   first_contribution_period, last_contribution_period
     */
    public function getLifetimeSummary(int $characterId, int $corporationId): array
    {
        $row = CharacterContribution::query()
            ->where('character_id', $characterId)
            ->where('corporation_id', $corporationId)
            ->selectRaw(
                'SUM(total_contribution_amount) AS contributed, ' .
                'SUM(withdrawal_amount) AS withdrawn, ' .
                'SUM(CASE WHEN total_contribution_amount > 0 THEN 1 ELSE 0 END) AS months_active, ' .
                'COUNT(*) AS months_in_cache, ' .
                'MIN(CASE WHEN total_contribution_amount > 0 THEN period ELSE NULL END) AS first_period, ' .
                'MAX(CASE WHEN total_contribution_amount > 0 THEN period ELSE NULL END) AS last_period'
            )
            ->first();

        return [
            'character_id'               => $characterId,
            'corporation_id'             => $corporationId,
            'lifetime_total_contributed' => (float) ($row->contributed ?? 0),
            'lifetime_total_withdrawn'   => (float) ($row->withdrawn ?? 0),
            'months_active'              => (int) ($row->months_active ?? 0),
            'months_in_cache'            => (int) ($row->months_in_cache ?? 0),
            'first_contribution_period'  => $row->first_period,
            'last_contribution_period'   => $row->last_period,
        ];
    }

    /**
     * Where the character ranks vs every other contributor in the corp
     * for a single period.
     *
     * Applies the same NPC / self-attribution guards getTopContributors
     * uses (character_id >= 90M, character_id != corporation_id). The
     * cohort is "positive-contribution rows for the corp in the period".
     * percentile is "what fraction of the cohort this character beat"
     * scaled to 0-100; the top contributor is at 100, the median at 50.
     * When the character has no row in the cohort, character_amount = 0
     * and percentile = 0 (i.e. ranks below everyone with a positive
     * contribution).
     *
     * Return shape:
     *   character_id, corporation_id, period,
     *   character_amount, percentile,
     *   corp_median, corp_p25, corp_p75,
     *   total_contributors
     */
    public function getCharacterPercentile(int $characterId, int $corporationId, string $period): array
    {
        $emptyShape = [
            'character_id'       => $characterId,
            'corporation_id'     => $corporationId,
            'period'             => $period,
            'character_amount'   => 0.0,
            'percentile'         => 0.0,
            'corp_median'        => 0.0,
            'corp_p25'           => 0.0,
            'corp_p75'           => 0.0,
            'total_contributors' => 0,
        ];

        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            return $emptyShape;
        }

        $amounts = CharacterContribution::query()
            ->where('corporation_id', $corporationId)
            ->where('period', $period)
            ->where('total_contribution_amount', '>', 0)
            ->where('character_id', '>=', 90000000)
            ->whereColumn('character_id', '!=', 'corporation_id')
            ->pluck('total_contribution_amount', 'character_id');

        if ($amounts->isEmpty()) {
            return $emptyShape;
        }

        // Pull this character's amount before sorting destroys the
        // character_id keys.
        $characterAmount = (float) ($amounts[$characterId] ?? 0.0);

        $sorted = $amounts->map(fn ($v) => (float) $v)->values()->sort()->values()->all();
        $total  = count($sorted);

        // Percentile: count entries strictly below this character's
        // amount, divide by total, scale to 0-100. Top contributor is at
        // 100 (everyone else is strictly below), median at 50.
        $below = 0;
        foreach ($sorted as $v) {
            if ($v < $characterAmount) {
                $below++;
            }
        }
        $percentile = $total > 0 ? ($below / $total) * 100.0 : 0.0;

        return [
            'character_id'       => $characterId,
            'corporation_id'     => $corporationId,
            'period'             => $period,
            'character_amount'   => $characterAmount,
            'percentile'         => (float) $percentile,
            'corp_median'        => $this->quantile($sorted, 0.5),
            'corp_p25'           => $this->quantile($sorted, 0.25),
            'corp_p75'           => $this->quantile($sorted, 0.75),
            'total_contributors' => $total,
        ];
    }

    /**
     * Per-character Mining Manager tax compliance over trailing N
     * months. Returns null when MM is absent or the mining_taxes table
     * is missing — callers should treat null as "not available" and
     * skip rendering, not as zero compliance.
     *
     * Iterates the trailing periods, looks up MM tax rows per period
     * (period_start within the calendar month, matching getMmTaxBatch's
     * semantics), and reports per-period compliance. consecutive_overdue
     * counts how many trailing periods (starting from the most recent)
     * have amount_owed > 0 AND amount_paid < amount_owed, stopping at
     * the first fully-paid or zero-owed period. overall_compliance_pct
     * is paid/owed*100 across the whole window (100 when owed = 0).
     *
     * Return shape:
     *   character_id, corporation_id, months,
     *   total_owed, total_paid,
     *   overall_compliance_pct, consecutive_overdue,
     *   by_period: [{period, owed, paid, compliance_pct}, ...] newest-first
     */
    public function getCharacterTaxCompliance(int $characterId, int $corporationId, int $months = 6): ?array
    {
        if (! $this->isMmInstalled()) {
            return null;
        }
        if (! Schema::hasTable('mining_taxes')) {
            return null;
        }

        $periods = $this->periodsForLastMonths($months);

        try {
            $byPeriod = [];
            $totalOwed = 0.0;
            $totalPaid = 0.0;

            foreach ($periods as $p) {
                $monthStart = $p . '-01';
                $monthEnd   = date('Y-m-t', strtotime($monthStart));

                $row = DB::table('mining_taxes')
                    ->where('character_id', $characterId)
                    ->whereBetween('period_start', [$monthStart, $monthEnd])
                    ->selectRaw('SUM(amount_owed) AS owed, SUM(amount_paid) AS paid')
                    ->first();

                $owed = (float) ($row->owed ?? 0);
                $paid = (float) ($row->paid ?? 0);

                $totalOwed += $owed;
                $totalPaid += $paid;

                $compliancePct = $owed > 0.0
                    ? min(100.0, ($paid / $owed) * 100.0)
                    : 100.0;

                $byPeriod[] = [
                    'period'         => $p,
                    'owed'           => $owed,
                    'paid'           => $paid,
                    'compliance_pct' => (float) $compliancePct,
                ];
            }

            // Count the run of trailing-most periods with owed > 0 and
            // paid < owed. Stop at the first fully-paid or zero-owed
            // period. $byPeriod is newest-first, so we walk forward.
            $consecutiveOverdue = 0;
            foreach ($byPeriod as $entry) {
                if ($entry['owed'] > 0.0 && $entry['paid'] < $entry['owed']) {
                    $consecutiveOverdue++;
                } else {
                    break;
                }
            }

            $overallCompliance = $totalOwed > 0.0
                ? min(100.0, ($totalPaid / $totalOwed) * 100.0)
                : 100.0;

            return [
                'character_id'           => $characterId,
                'corporation_id'         => $corporationId,
                'months'                 => $months,
                'total_owed'             => $totalOwed,
                'total_paid'             => $totalPaid,
                'overall_compliance_pct' => (float) $overallCompliance,
                'consecutive_overdue'    => $consecutiveOverdue,
                'by_period'              => $byPeriod,
            ];
        } catch (\Throwable $e) {
            Log::warning('[Corp Wallet Manager] ContributionService: MM tax compliance lookup failed', [
                'character_id' => $characterId,
                'error'        => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Aggregated corp-wide Mining Manager tax compliance over an
     * arbitrary date range. Used by the scheduled report surfaces
     * (weekly / monthly / quarterly / annual) so the report can show
     * the corp's compliance posture for the report period as a single
     * line ("paid 4.2B of 5.0B owed = 84%, 12 of 14 members compliant").
     *
     * Returns null when MM is absent or the mining_taxes table is
     * missing — callers should treat null as "not available" and skip
     * rendering, NOT zero. A member is considered "compliant" when
     * their paid amount is >= their owed amount within the range.
     *
     * Implementation walks mining_taxes by period_start within
     * [$from, $to] (matching getMmTaxBatch and getCharacterTaxCompliance
     * conventions). $from / $to accept Carbon, DateTimeInterface, or
     * 'Y-m-d H:i:s' strings.
     *
     * Return shape:
     *   corporation_id, from, to,
     *   owed, paid, compliance_pct,
     *   members_with_owed, members_compliant
     */
    public function getMmCorpComplianceForRange(int $corpId, $from, $to): ?array
    {
        if (! $this->isMmInstalled()) {
            return null;
        }
        if (! Schema::hasTable('mining_taxes')) {
            return null;
        }

        try {
            $fromStr = is_string($from) ? $from : (string) $from;
            $toStr   = is_string($to)   ? $to   : (string) $to;

            // mining_taxes has no corporation_id column, so we scope
            // the cohort by deriving the set of characters that belong
            // to this corp from character_affiliations (canonical SeAT
            // signal). Falls back to the contribution cache when the
            // affiliations table is missing for any reason — same
            // defensive pattern as the rest of this service.
            if (Schema::hasTable('character_affiliations')) {
                $characterIds = DB::table('character_affiliations')
                    ->where('corporation_id', $corpId)
                    ->where('character_id', '>=', 90000000)
                    ->whereColumn('character_id', '!=', 'corporation_id')
                    ->pluck('character_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            } else {
                $characterIds = DB::table('corpwalletmanager_character_contributions')
                    ->where('corporation_id', $corpId)
                    ->where('character_id', '>=', 90000000)
                    ->whereColumn('character_id', '!=', 'corporation_id')
                    ->distinct()
                    ->pluck('character_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            if (empty($characterIds)) {
                return [
                    'corporation_id'    => $corpId,
                    'from'              => $fromStr,
                    'to'                => $toStr,
                    'owed'              => 0.0,
                    'paid'              => 0.0,
                    'compliance_pct'    => 100.0,
                    'members_with_owed' => 0,
                    'members_compliant' => 0,
                ];
            }

            // Per-character totals so we can derive compliant-member
            // count alongside the corp-wide sums. period_start fits
            // inside the range (matches the weekly-billing edge case
            // noted on getMmTaxBatch — periods crossing a range boundary
            // are attributed to whichever side period_start lands on).
            $rows = DB::table('mining_taxes')
                ->whereIn('character_id', $characterIds)
                ->whereBetween('period_start', [$fromStr, $toStr])
                ->selectRaw('character_id, SUM(amount_owed) AS owed, SUM(amount_paid) AS paid')
                ->groupBy('character_id')
                ->get();

            $totalOwed = 0.0;
            $totalPaid = 0.0;
            $membersWithOwed   = 0;
            $membersCompliant  = 0;
            foreach ($rows as $r) {
                $owed = (float) ($r->owed ?? 0);
                $paid = (float) ($r->paid ?? 0);
                $totalOwed += $owed;
                $totalPaid += $paid;
                if ($owed > 0) {
                    $membersWithOwed++;
                    if ($paid >= $owed) {
                        $membersCompliant++;
                    }
                }
            }

            $compliancePct = $totalOwed > 0
                ? min(100.0, ($totalPaid / $totalOwed) * 100.0)
                : 100.0;

            return [
                'corporation_id'    => $corpId,
                'from'              => $fromStr,
                'to'                => $toStr,
                'owed'              => $totalOwed,
                'paid'              => $totalPaid,
                'compliance_pct'    => (float) $compliancePct,
                'members_with_owed' => $membersWithOwed,
                'members_compliant' => $membersCompliant,
            ];
        } catch (\Throwable $e) {
            Log::warning('[Corp Wallet Manager] ContributionService: MM corp compliance lookup failed', [
                'corporation_id' => $corpId,
                'error'          => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Linear interpolation quantile over a pre-sorted ascending array
     * of floats. Returns 0.0 for an empty array. Used by
     * getCharacterPercentile to derive corp_median / corp_p25 /
     * corp_p75. Local helper because PHP's stats extensions are not
     * available in SeAT's base image.
     *
     * @param  array<int,float>  $sorted  ascending
     */
    private function quantile(array $sorted, float $q): float
    {
        $n = count($sorted);
        if ($n === 0) {
            return 0.0;
        }
        if ($n === 1) {
            return (float) $sorted[0];
        }
        $q = max(0.0, min(1.0, $q));
        $pos = $q * ($n - 1);
        $lo = (int) floor($pos);
        $hi = (int) ceil($pos);
        if ($lo === $hi) {
            return (float) $sorted[$lo];
        }
        $frac = $pos - $lo;
        return (float) ($sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * $frac);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** ['2026-05', '2026-04', ...] for the trailing N months including the current one. */
    private function periodsForLastMonths(int $months): array
    {
        $months = max(1, $months);
        $now = now();
        $periods = [];
        for ($i = 0; $i < $months; $i++) {
            $periods[] = $now->copy()->subMonths($i)->format('Y-m');
        }
        return $periods;
    }
}
