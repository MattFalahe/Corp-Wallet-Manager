<?php

namespace CorpWalletManager\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use CorpWalletManager\Models\Settings;

/**
 * Period-scoped anomaly summary for the scheduled report surfaces
 * (weekly / monthly / quarterly / annual).
 *
 * The two anomaly detectors in DetectWalletAlerts persist state with
 * differing fidelity:
 *
 *   - contribution_drop: per-(corp, character) latch in
 *     corpwalletmanager_anomaly_state with contribution_drop_notified_at
 *     timestamp. The timestamp is OVERWRITTEN on each fresh crossing
 *     after a recovery — so the table reflects the most recent drop
 *     per (corp, character) and we can filter by notified_at falling
 *     in the report period to surface "drops fired during the period".
 *
 *   - unusual_recipient: no log table. The detector advances an
 *     internal_id watermark and that's it. There is no way to query
 *     "what alerted during period X" after the fact.
 *
 * To make the report useful we treat the two halves differently:
 *
 *   - Contribution drops are pulled from the latch table by
 *     notified_at, giving operators a near-historic view (latest drop
 *     per character, in-period).
 *   - Unusual recipients are RE-DERIVED by re-walking the journal
 *     with the same first-time-recipient heuristic the detector uses
 *     (>= threshold, second_party_id with no withdrawal history older
 *     than the 7-day cold window). This produces "rows that would
 *     have alerted during the period" — equivalent to what an
 *     operator would have seen, even though the actual notification
 *     watermark may have already passed them.
 *
 * Both halves honour the operator-configured thresholds (zero =
 * detector disabled = no anomalies reported).
 */
class AnomalyReportService
{
    /** Mirrors DetectWalletAlerts::UNUSUAL_RECIPIENT_COLD_DAYS. */
    private const UNUSUAL_RECIPIENT_COLD_DAYS = 7;

    /**
     * Summarise anomalies raised for the corp over the given range.
     *
     * Always returns an array (never null). When neither anomaly
     * detector is enabled OR both pieces of state are missing, returns
     * an empty-shape array with total_anomalies = 0 so the report can
     * still render a "no anomalies" line.
     *
     * Return shape:
     *   from, to,
     *   contribution_drops: [
     *     {character_id, character_name, prior_avg, recent_avg, raised_at},
     *     ...
     *   ],
     *   unusual_recipients: [
     *     {transaction_id, recipient_id, recipient_name, amount, division,
     *      date, description},
     *     ...
     *   ],
     *   total_anomalies: int,
     *   note: string|null   // operator-facing caveat about provenance
     *
     * @param  Carbon|\DateTimeInterface|string  $from
     * @param  Carbon|\DateTimeInterface|string  $to
     */
    public function getAnomalySummaryForRange(int $corpId, $from, $to): array
    {
        $fromStr = is_string($from) ? $from : (string) $from;
        $toStr   = is_string($to)   ? $to   : (string) $to;

        $drops      = $this->fetchContributionDrops($corpId, $from, $to);
        $recipients = $this->fetchUnusualRecipients($corpId, $from, $to);

        $total = count($drops) + count($recipients);

        // The report-side text the template renders under the header.
        // Documents the asymmetry between the two data sources so
        // operators understand "why does this not match my Discord
        // alert log exactly?".
        $note = 'Contribution drops are the latest crossing per member with notified_at in this period. '
              . 'Unusual recipients are re-derived from the journal using the live detector rule, so the '
              . 'list matches what the detector would surface for this period independent of the live '
              . 'watermark.';

        return [
            'from'                => $fromStr,
            'to'                  => $toStr,
            'contribution_drops'  => $drops,
            'unusual_recipients'  => $recipients,
            'total_anomalies'     => $total,
            'note'                => $note,
        ];
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Pull contribution-drop crossings whose notified_at falls inside
     * [$from, $to] from the latch table. Returns an empty array when
     * the table is missing (older installs) or the detector is
     * disabled (threshold = 0) so we don't surface stale latch state.
     *
     * @return list<array{character_id:int, character_name:string, prior_avg:float, recent_avg:float, raised_at:string}>
     */
    private function fetchContributionDrops(int $corpId, $from, $to): array
    {
        if (! Schema::hasTable('corpwalletmanager_anomaly_state')) {
            return [];
        }

        $threshold = (float) Settings::getIntegerSetting('anomaly_contribution_threshold', 0);
        if ($threshold <= 0) {
            return [];
        }

        try {
            $rows = DB::table('corpwalletmanager_anomaly_state')
                ->where('corporation_id', $corpId)
                ->whereBetween('contribution_drop_notified_at', [$from, $to])
                ->where('character_id', '>=', 90000000)
                ->whereColumn('character_id', '!=', 'corporation_id')
                ->orderByDesc('contribution_drop_notified_at')
                ->get([
                    'character_id',
                    'contribution_drop_prior_avg',
                    'contribution_drop_recent_avg',
                    'contribution_drop_notified_at',
                ]);
        } catch (\Throwable $e) {
            Log::warning('[CWM] AnomalyReportService: contribution-drop lookup failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }

        if ($rows->isEmpty()) {
            return [];
        }

        $charIds = $rows->pluck('character_id')->map(fn ($v) => (int) $v)->unique()->all();
        $names   = $this->resolveNames($charIds);

        $out = [];
        foreach ($rows as $r) {
            $charId = (int) $r->character_id;
            $out[] = [
                'character_id'   => $charId,
                'character_name' => $names[$charId] ?? ('Character ' . $charId),
                'prior_avg'      => (float) ($r->contribution_drop_prior_avg ?? 0),
                'recent_avg'     => (float) ($r->contribution_drop_recent_avg ?? 0),
                'raised_at'      => (string) ($r->contribution_drop_notified_at ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Re-derive unusual-recipient candidates for the period directly
     * from the journal. Same rule the live detector uses: a
     * corporation_account_withdrawal where |amount| >= threshold and
     * the second_party_id had no withdrawal history older than the
     * 7-day cold window relative to the row date.
     *
     * Returns an empty array when the detector is disabled
     * (threshold = 0).
     *
     * @return list<array{transaction_id:int, recipient_id:int, recipient_name:string, amount:float, division:int, date:string, description:string}>
     */
    private function fetchUnusualRecipients(int $corpId, $from, $to): array
    {
        $threshold = (float) Settings::getIntegerSetting('anomaly_unusual_recipient_threshold', 0);
        if ($threshold <= 0) {
            return [];
        }

        try {
            $rows = DB::table('corporation_wallet_journals')
                ->where('corporation_id', $corpId)
                ->whereBetween('date', [$from, $to])
                ->where('ref_type', 'corporation_account_withdrawal')
                ->where('amount', '<=', -$threshold)
                ->whereNotNull('second_party_id')
                ->whereColumn('second_party_id', '!=', 'corporation_id')
                ->orderBy('date')
                ->get(['id', 'date', 'amount', 'division', 'second_party_id', 'description']);
        } catch (\Throwable $e) {
            Log::warning('[CWM] AnomalyReportService: unusual-recipient candidate query failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }

        if ($rows->isEmpty()) {
            return [];
        }

        // Filter via the same first-time-recipient rule the detector
        // uses. Done in PHP after the candidate set is bounded to the
        // period so we don't run the cold-history subquery N times for
        // every above-threshold withdrawal in the whole journal.
        $accepted = [];
        foreach ($rows as $r) {
            $recipientId = (int) $r->second_party_id;
            $rowDate     = (string) $r->date;
            if ($this->isFirstTimeRecipient($corpId, $recipientId, $rowDate)) {
                $accepted[] = $r;
            }
        }

        if (empty($accepted)) {
            return [];
        }

        $recipientIds = [];
        foreach ($accepted as $r) {
            $recipientIds[] = (int) $r->second_party_id;
        }
        $names = $this->resolveNames(array_values(array_unique($recipientIds)));

        $out = [];
        foreach ($accepted as $r) {
            $recipientId = (int) $r->second_party_id;
            $out[] = [
                'transaction_id'  => (int) $r->id,
                'recipient_id'    => $recipientId,
                'recipient_name'  => $names[$recipientId] ?? ('Entity ' . $recipientId),
                'amount'          => (float) $r->amount,
                'division'        => (int) ($r->division ?? 0),
                'date'            => (string) ($r->date ?? ''),
                'description'     => (string) ($r->description ?? ''),
            ];
        }
        return $out;
    }

    /**
     * Mirror of DetectWalletAlerts::isFirstTimeRecipient. Same rule:
     * the recipient has no corporation_account_withdrawal row from
     * this corp dated more than COLD_DAYS before $rowDate.
     */
    private function isFirstTimeRecipient(int $corporationId, int $recipientId, string $rowDate): bool
    {
        try {
            $coldCutoff = Carbon::parse($rowDate)
                ->subDays(self::UNUSUAL_RECIPIENT_COLD_DAYS)
                ->toDateTimeString();
        } catch (\Throwable $e) {
            return false;
        }

        return ! DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->where('second_party_id', $recipientId)
            ->where('ref_type', 'corporation_account_withdrawal')
            ->where('date', '<', $coldCutoff)
            ->exists();
    }

    /**
     * Batch name resolution via EntityNameResolver with useEsi = false
     * (the report job runs in queue and we don't want to fan out N ESI
     * requests per retrospective). Names that don't resolve locally
     * fall back to the raw id at the call site.
     *
     * @param  array<int,int>  $ids
     * @return array<int,string>
     */
    private function resolveNames(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        try {
            $resolved = app(EntityNameResolver::class)->resolve(array_unique($ids), false);
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($resolved as $id => $info) {
            $name = $info['name'] ?? 'Unknown';
            if ($name !== 'Unknown' && $name !== '') {
                $out[(int) $id] = (string) $name;
            }
        }
        return $out;
    }
}
