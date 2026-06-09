<?php

namespace CorpWalletManager\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use CorpWalletManager\Models\Settings;

/**
 * Alliance tax reconciliation for the Director view's Alliance Tax tab.
 *
 * What this tab is for:
 *
 *   The corp's actual monthly alliance tax payment hits the wallet
 *   journal as one (or a handful) of outgoing lump sums. From the row
 *   alone CWM cannot tell that an outgoing payment IS the alliance
 *   tax remit — it just looks like a corporation_account_withdrawal
 *   or player_donation to some recipient. Operators configure the
 *   recipient party id(s) in Settings; this service then identifies
 *   those outflows, sums them per month, and compares against the
 *   per-bucket alliance tax CWM expected based on configured rates.
 *
 *   The expected side comes from
 *   corpwalletmanager_character_contributions:
 *     Σ over buckets of (bucket income × bucket alliance rate)
 *   summed across every character in the corp for the period.
 *
 *   The actual side comes from corporation_wallet_journals:
 *     Σ of ABS(amount) where ref_type in
 *     (corporation_account_withdrawal, player_donation) AND
 *     second_party_id in (configured recipients) AND amount < 0.
 *
 *   The difference is signed (actual − expected):
 *     positive = corp paid more than our calc predicts (likely
 *     uncovered income like mining tax variance, or rates set lower
 *     than what alliance actually charges).
 *     negative = corp paid less than calc predicts (under-remitted,
 *     or rates set higher than reality).
 *     zero / near-zero = rates and remittance are aligned.
 *
 * Single source of truth: the corpwalletmanager_settings rows for the
 * per-bucket rates and the recipient id list. No new tables; the
 * cache is enough.
 */
class AllianceTaxService
{
    /** Cached per-instance to avoid re-parsing on every method call. */
    private ?array $recipientIds = null;

    /** Cached per-instance. */
    private ?array $descriptionKeywords = null;

    /**
     * Build a per-period reconciliation array for the given corp over
     * the trailing N months (default 6, including the current month).
     *
     * Return shape:
     *   [
     *     'corporation_id'        => int,
     *     'months'                => int,
     *     'recipient_ids'         => int[],         // parsed from settings
     *     'description_keywords'  => string[],      // parsed from settings
     *     'has_recipients'        => bool,
     *     'has_keywords'          => bool,
     *     'has_match_rules'       => bool,          // false = no actual-paid comparison
     *     'rates'                 => [bucket => float], // %
     *     'periods'               => [
     *       [
     *         'period'     => 'YYYY-MM',
     *         'income'     => [bucket => float], // ISK per bucket this period
     *         'expected'   => [
     *           'per_bucket' => [bucket => float], // ISK alliance tax per bucket
     *           'total'      => float,
     *         ],
     *         'actual'     => float,             // ISK matched by recipients OR keywords
     *         'actual_payments' => int,          // count of matched journal rows
     *         'difference' => float,             // actual - expected.total
     *       ],
     *       ...
     *     ],
     *   ]
     */
    public function getReconciliation(int $corporationId, int $months = 6): array
    {
        $months = max(1, min(36, $months));
        $rates = $this->allianceTaxRates();
        $recipientIds = $this->getRecipientIds();
        $keywords = $this->getDescriptionKeywords();
        $hasRecipients = ! empty($recipientIds);
        $hasKeywords = ! empty($keywords);
        $hasMatchRules = $hasRecipients || $hasKeywords;

        $periods = $this->periodsForLastMonths($months);

        $income = $this->incomePerPeriod($corporationId, $periods);
        $actual = $hasMatchRules
            ? $this->actualPaidPerPeriod($corporationId, $periods, $recipientIds, $keywords)
            : [];

        $periodRows = [];
        foreach ($periods as $period) {
            $periodIncome = $income[$period] ?? $this->zeroBuckets();

            $perBucket = [
                'ratting'            => $periodIncome['ratting']            * ($rates['ratting']            / 100.0),
                'mission'            => $periodIncome['mission']            * ($rates['mission']            / 100.0),
                'tax_payment'        => $periodIncome['tax_payment']        * ($rates['tax_payment']        / 100.0),
                'donation_voluntary' => $periodIncome['donation_voluntary'] * ($rates['donation_voluntary'] / 100.0),
                'industry'           => $periodIncome['industry']           * ($rates['industry']           / 100.0),
            ];
            $expectedTotal = array_sum($perBucket);

            $actualEntry = $actual[$period] ?? ['amount' => 0.0, 'count' => 0];

            $periodRows[] = [
                'period'          => $period,
                'income'          => $periodIncome,
                'expected'        => [
                    'per_bucket' => $perBucket,
                    'total'      => $expectedTotal,
                ],
                'actual'          => (float) $actualEntry['amount'],
                'actual_payments' => (int) $actualEntry['count'],
                'difference'      => $hasMatchRules ? ((float) $actualEntry['amount'] - $expectedTotal) : 0.0,
            ];
        }

        // Oldest first so the chart reads left-to-right naturally.
        usort($periodRows, fn ($a, $b) => strcmp($a['period'], $b['period']));

        return [
            'corporation_id'       => $corporationId,
            'months'               => $months,
            'recipient_ids'        => $recipientIds,
            'description_keywords' => $keywords,
            'has_recipients'       => $hasRecipients,
            'has_keywords'         => $hasKeywords,
            'has_match_rules'      => $hasMatchRules,
            'rates'                => $rates,
            'periods'              => $periodRows,
        ];
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Per-bucket per-period income summed across every character in
     * the corp. Reads from the precomputed contribution cache, so this
     * is cheap regardless of how many journal rows the period contains.
     *
     * @return array<string, array<string, float>>  period => [bucket => amount]
     */
    private function incomePerPeriod(int $corporationId, array $periods): array
    {
        if (empty($periods)) {
            return [];
        }

        $rows = DB::table('corpwalletmanager_character_contributions')
            ->where('corporation_id', $corporationId)
            ->whereIn('period', $periods)
            // Same defensive guards as the leaderboard: keep NPCs and
            // self-attribution out of corp-wide totals.
            ->where('character_id', '>=', 90000000)
            ->whereColumn('character_id', '!=', 'corporation_id')
            ->groupBy('period')
            ->selectRaw(
                'period, ' .
                'SUM(ratting_amount) AS ratting, ' .
                'SUM(mission_amount) AS mission, ' .
                'SUM(tax_payment_amount) AS tax_payment, ' .
                'SUM(donation_voluntary_amount) AS donation_voluntary, ' .
                'SUM(industry_amount) AS industry'
            )
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $result[(string) $r->period] = [
                'ratting'            => (float) $r->ratting,
                'mission'            => (float) $r->mission,
                'tax_payment'        => (float) $r->tax_payment,
                'donation_voluntary' => (float) $r->donation_voluntary,
                'industry'           => (float) $r->industry,
            ];
        }
        return $result;
    }

    /**
     * Range-scoped income aggregate (same five buckets incomePerPeriod
     * returns, summed across every YYYY-MM period that intersects the
     * range). Used by getExpectedAllianceTaxForRange to compute report
     * expected totals without the caller having to enumerate periods.
     *
     * Walks the months between $from and $to and reuses incomePerPeriod
     * so the bucket math stays in one place. Capped at 24 months as a
     * safety against a caller passing a huge range.
     *
     * @param  Carbon|\DateTimeInterface|string  $from
     * @param  Carbon|\DateTimeInterface|string  $to
     * @return array{ratting:float, mission:float, tax_payment:float, donation_voluntary:float, industry:float}
     */
    private function incomePerRange(int $corporationId, $from, $to): array
    {
        $cursor = Carbon::parse($from)->copy()->startOfMonth();
        $end    = Carbon::parse($to)->copy()->startOfMonth();

        $periods = [];
        $iterations = 0;
        while ($cursor->lte($end) && $iterations < 24) {
            $periods[] = $cursor->format('Y-m');
            $cursor->addMonth();
            $iterations++;
        }

        if (empty($periods)) {
            return $this->zeroBuckets();
        }

        $perPeriod = $this->incomePerPeriod($corporationId, $periods);
        $totals    = $this->zeroBuckets();
        foreach ($perPeriod as $bucketRow) {
            foreach ($totals as $bucket => $_) {
                $totals[$bucket] += (float) ($bucketRow[$bucket] ?? 0);
            }
        }
        return $totals;
    }

    /**
     * Per-period sum of outgoing payments matched by recipient id OR
     * description keyword. Both match rules are OR-combined; an
     * outgoing payment matching either is counted exactly once
     * (SUM DISTINCT not needed because journal rows are unique by id).
     *
     * @return array<string, array{amount: float, count: int}>
     */
    private function actualPaidPerPeriod(int $corporationId, array $periods, array $recipientIds, array $keywords): array
    {
        if (empty($periods) || (empty($recipientIds) && empty($keywords))) {
            return [];
        }

        // Build a [start, end] tuple covering the full range so the
        // query can use the date index on corporation_wallet_journals.
        sort($periods);
        $firstStart = $periods[0] . '-01';
        $lastEnd    = date('Y-m-t 23:59:59', strtotime(end($periods) . '-01'));

        $query = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->whereBetween('date', [$firstStart, $lastEnd]);
        $this->applyAllianceTaxMatchClause($query, $recipientIds, $keywords);

        $rows = $query
            ->groupByRaw("DATE_FORMAT(date, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') AS period, SUM(ABS(amount)) AS amount, COUNT(*) AS cnt")
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $result[(string) $r->period] = [
                'amount' => (float) $r->amount,
                'count'  => (int) $r->cnt,
            ];
        }
        return $result;
    }

    /**
     * Per-bucket × per-rate alliance tax EXPECTED for an arbitrary date
     * range. Mirrors the math the Alliance Tax tab does for each month
     * (incomePerPeriod × rate / 100) collapsed across every month that
     * intersects the range. Intended for the scheduled report
     * surfaces (weekly / monthly / quarterly / annual) so the report can
     * show "expected: X" alongside the existing alliance_tax_remit
     * actual.
     *
     * The income side reads the same precomputed contribution cache the
     * reconciliation tab does, so this is cheap regardless of how many
     * journal rows the range contains.
     *
     * Return shape:
     *   from, to,
     *   rates: {bucket => float, ...},
     *   by_bucket: [
     *     {bucket, rate_pct, bucket_total, expected_alliance_tax},
     *     ...   // every bucket emitted, even when rate=0 or income=0
     *   ],
     *   total_expected: float
     *
     * @param  Carbon|\DateTimeInterface|string  $from
     * @param  Carbon|\DateTimeInterface|string  $to
     */
    public function getExpectedAllianceTaxForRange(int $corporationId, $from, $to): array
    {
        $rates  = $this->allianceTaxRates();
        $income = $this->incomePerRange($corporationId, $from, $to);

        $byBucket = [];
        $totalExpected = 0.0;
        foreach (['ratting', 'mission', 'tax_payment', 'donation_voluntary', 'industry'] as $bucket) {
            $bucketTotal = (float) ($income[$bucket] ?? 0);
            $ratePct     = (float) ($rates[$bucket] ?? 0);
            $expected    = $bucketTotal * ($ratePct / 100.0);

            $totalExpected += $expected;
            $byBucket[] = [
                'bucket'                => $bucket,
                'rate_pct'              => $ratePct,
                'bucket_total'          => $bucketTotal,
                'expected_alliance_tax' => $expected,
            ];
        }

        return [
            'from'           => is_string($from) ? $from : (string) $from,
            'to'             => is_string($to)   ? $to   : (string) $to,
            'rates'          => $rates,
            'by_bucket'      => $byBucket,
            'total_expected' => (float) $totalExpected,
        ];
    }

    /**
     * For an arbitrary date range, return the alliance-tax breakdown
     * split by the original ref_type (so callers can subtract the
     * alliance-tax portion from each ref_type bucket on a transaction
     * breakdown chart).
     *
     * Used by the expense breakdown surfaces to split
     * "corporation_account_withdrawal" into the alliance-tax fraction
     * (which is what the corp actually paid the alliance) and the
     * remainder (genuine other withdrawals — payroll, contracts,
     * structure fuel buys, etc).
     *
     * Returns ['corporation_account_withdrawal' => ['amount' => N, 'count' => N], ...]
     * keyed by source ref_type. Empty array when no match rules are
     * configured.
     *
     * @return array<string, array{amount: float, count: int}>
     */
    public function getAllianceTaxByRefType(int $corporationId, $from, $to): array
    {
        $recipientIds = $this->getRecipientIds();
        $keywords     = $this->getDescriptionKeywords();

        if (empty($recipientIds) && empty($keywords)) {
            return [];
        }

        $query = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->whereBetween('date', [$from, $to]);
        $this->applyAllianceTaxMatchClause($query, $recipientIds, $keywords);

        $rows = $query
            ->groupBy('ref_type')
            ->selectRaw('ref_type, SUM(ABS(amount)) AS amount, COUNT(*) AS cnt')
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $result[(string) $r->ref_type] = [
                'amount' => (float) $r->amount,
                'count'  => (int) $r->cnt,
            ];
        }
        return $result;
    }

    /**
     * Post-process a ref_type-keyed transaction breakdown to split out
     * the alliance_tax portion. Mutates rows in $breakdown in-place
     * (reducing each source ref_type's amount + count by the alliance-
     * tax fraction that came from it) and appends an aggregated
     * 'alliance_tax' row.
     *
     * No-op when no alliance-tax rows match — call sites can invoke
     * unconditionally and the breakdown stays exactly as it was when
     * the corp has no alliance-tax config.
     *
     * Expects the breakdown to be a collection of stdClass with at
     * minimum (ref_type, count, total_amount) — matches the shape of
     * the GenerateReport / WalletController breakdown queries. The
     * sign convention is preserved: expense ref_types arrive with
     * negative total_amount, the inserted alliance_tax row is
     * negative too.
     *
     * @return \Illuminate\Support\Collection|array  Same shape as input
     */
    public function applyAllianceTaxBreakdown($breakdown, int $corporationId, $from, $to)
    {
        $byRefType = $this->getAllianceTaxByRefType($corporationId, $from, $to);
        if (empty($byRefType)) {
            return $breakdown;
        }

        $totalAmount = 0.0;
        $totalCount  = 0;

        // Subtract alliance-tax portion from each source ref_type row,
        // accumulating the per-ref aggregate as we go.
        $items = collect($breakdown)->map(function ($row) use ($byRefType, &$totalAmount, &$totalCount) {
            $rt = $row->ref_type ?? null;
            if ($rt !== null && isset($byRefType[$rt])) {
                $share = $byRefType[$rt];
                $sign = ($row->total_amount ?? 0) < 0 ? -1 : 1;
                $row->total_amount = (float) ($row->total_amount ?? 0) - ($sign * $share['amount']);
                $row->count = max(0, (int) ($row->count ?? 0) - (int) $share['count']);
                if (isset($row->avg_amount) && $row->count > 0) {
                    $row->avg_amount = (float) $row->total_amount / (int) $row->count;
                } elseif (isset($row->avg_amount)) {
                    $row->avg_amount = 0;
                }
                $totalAmount += $share['amount'];
                $totalCount  += $share['count'];
            }
            return $row;
        })->filter(function ($row) {
            // Drop ref_type rows whose count went to zero after the
            // alliance-tax split — they were 100% alliance tax.
            return ((int) ($row->count ?? 0)) > 0;
        })->values();

        if ($totalAmount > 0 || $totalCount > 0) {
            $items->push((object) [
                'ref_type'     => 'alliance_tax',
                'count'        => $totalCount,
                'total_amount' => -1 * $totalAmount, // expense sign
                'avg_amount'   => $totalCount > 0 ? -1 * ($totalAmount / $totalCount) : 0,
            ]);
        }

        // Preserve the original sort: top expense first (most negative
        // first). Re-sort by total_amount asc so the alliance_tax row
        // slots in by magnitude.
        return $items->sortBy('total_amount')->values();
    }

    /**
     * Apply the canonical alliance-tax match WHERE clauses to a query
     * builder. Shared by every caller that asks "is this journal row
     * an alliance tax payment?":
     *
     *   - actualPaidPerPeriod()       (Alliance Tax reconciliation chart
     *                                  and table)
     *   - getAllianceTaxByRefType()   (expense breakdown reclassification
     *                                  on the wallet view and scheduled
     *                                  reports)
     *
     * The match rule is:
     *
     *   ref_type IN ('corporation_account_withdrawal', 'player_donation')
     *     AND amount < 0
     *     AND (
     *           second_party_id IN (configured recipient ids)
     *        OR description LIKE %keyword1%
     *        OR description LIKE %keyword2%
     *        ...
     *     )
     *
     * Single source of truth so the reconciliation chart and the
     * expense breakdown can't drift apart when the rule changes.
     * Mutates the builder in place and returns it for chaining.
     *
     * Wildcard escaping (addcslashes for %, _, \) so a literal "%" in
     * an operator tag does not become a LIKE wildcard. MySQL's default
     * LIKE escape character is backslash.
     *
     * Seed-false `1 = 0` opens the OR chain so the bracketed expression
     * only matches rows that hit at least one configured rule.
     */
    private function applyAllianceTaxMatchClause($query, array $recipientIds, array $keywords)
    {
        return $query
            ->whereIn('ref_type', ['corporation_account_withdrawal', 'player_donation'])
            ->where('amount', '<', 0)
            ->where(function ($q) use ($recipientIds, $keywords) {
                $q->whereRaw('1 = 0');
                if (! empty($recipientIds)) {
                    $q->orWhereIn('second_party_id', $recipientIds);
                }
                foreach ($keywords as $kw) {
                    $escaped = addcslashes($kw, '%_\\');
                    $q->orWhere('description', 'LIKE', '%' . $escaped . '%');
                }
            });
    }

    /**
     * Parse the comma-separated recipient id list from settings into a
     * deduplicated int[] of valid party ids. Anything non-numeric or
     * <= 0 is silently dropped — invalid ids just disable matching
     * rather than throw.
     *
     * @return int[]
     */
    private function getRecipientIds(): array
    {
        if ($this->recipientIds !== null) {
            return $this->recipientIds;
        }

        $raw = (string) Settings::getSetting('alliance_tax_recipient_ids', '');
        if ($raw === '') {
            return $this->recipientIds = [];
        }

        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $ids = [];
        foreach ($parts as $part) {
            if (! is_numeric($part)) {
                continue;
            }
            $id = (int) $part;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        return $this->recipientIds = array_keys($ids);
    }

    /**
     * Parse the comma-separated description keywords from settings.
     * Whitespace / comma / semicolon delimited. Empty tokens dropped.
     * Order preserved; case kept as written (MySQL LIKE is
     * case-insensitive on default collations so case doesn't matter
     * for matching, but preserving it makes the diagnostic view
     * readable).
     *
     * @return string[]
     */
    private function getDescriptionKeywords(): array
    {
        if ($this->descriptionKeywords !== null) {
            return $this->descriptionKeywords;
        }

        $raw = (string) Settings::getSetting('alliance_tax_description_keywords', '');
        if ($raw === '') {
            return $this->descriptionKeywords = [];
        }

        // Only comma / semicolon split, NOT whitespace - keywords may
        // legitimately contain spaces (e.g. "monthly alliance fee").
        $parts = preg_split('/[,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $keywords = [];
        foreach ($parts as $part) {
            $trimmed = trim($part);
            if ($trimmed !== '') {
                $keywords[] = $trimmed;
            }
        }

        return $this->descriptionKeywords = $keywords;
    }

    /** Read the same five alliance rates ContributionService uses. */
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

    /** ['2026-01', '2026-02', ..., current]. */
    private function periodsForLastMonths(int $months): array
    {
        $now = Carbon::now();
        $periods = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $periods[] = $now->copy()->subMonths($i)->format('Y-m');
        }
        return $periods;
    }

    private function zeroBuckets(): array
    {
        return [
            'ratting'            => 0.0,
            'mission'            => 0.0,
            'tax_payment'        => 0.0,
            'donation_voluntary' => 0.0,
            'industry'           => 0.0,
        ];
    }
}
