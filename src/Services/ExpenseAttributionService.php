<?php

namespace CorpWalletManager\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use CorpWalletManager\Support\JournalFilters;

/**
 * Per-category corp wallet expense analytics for the Director "Expense
 * Attribution" tab.
 *
 * Counterpart to ContributionService::getProfitAttribution: where that
 * answers "what activity types drove the income?", this answers "what
 * category of expense ate the corp's outgoings?". Operators can use it
 * to spot the biggest expense lines and decide where structural cost
 * cuts would actually move the needle.
 *
 * The category taxonomy is a fixed CCP ref_type bucket map (see
 * EXPENSE_CATEGORIES below). Alliance Tax is special-cased because
 * the actual alliance remittance hits the journal as a generic
 * `corporation_account_withdrawal` or `player_donation`; the heuristic
 * for "which outflows ARE the alliance tax" lives in
 * AllianceTaxService::getAllianceTaxByRefType. We pull that fraction
 * out and subtract it from corp_withdrawal so the two categories
 * never double-count.
 *
 * Every journal query is scoped to (corporation_id, expense rows) and
 * runs through JournalFilters::excludeInternalTransfers so inter-
 * division ISK movements never inflate any category.
 */
class ExpenseAttributionService
{
    /**
     * Fixed taxonomy mapping CCP ref_types to operator-facing
     * expense categories. The category label is rendered straight into
     * the UI; no lang keys, since this is an internal taxonomy that
     * does not vary by locale.
     *
     * Two special cases:
     *   - 'alliance_tax' has no ref_types list; the amount comes from
     *     AllianceTaxService::getAllianceTaxByRefType (recipient-id /
     *     keyword matched outflows). It is reported as its own
     *     category even though the underlying journal ref_types are
     *     corporation_account_withdrawal / player_donation.
     *   - 'corp_withdrawal' SUBTRACTS the alliance_tax fraction whose
     *     source ref_type was corporation_account_withdrawal so the
     *     two categories sum to the original total.
     *   - 'other' has no ref_types list; it catches everything not
     *     matched by an earlier category.
     */
    private const EXPENSE_CATEGORIES = [
        'alliance_tax' => [
            'label'     => 'Alliance Tax',
            'ref_types' => [],
        ],
        'corp_withdrawal' => [
            'label'     => 'Corp Withdrawal',
            'ref_types' => ['corporation_account_withdrawal'],
        ],
        'market_fees' => [
            'label'     => 'Market Fees',
            'ref_types' => [
                'brokers_fee',
                'contract_brokers_fee',
                'contract_brokers_fee_corp',
                'transaction_tax',
                'contract_sales_tax',
            ],
        ],
        'office_rental' => [
            'label'     => 'Office Rental',
            'ref_types' => ['office_rental_fee'],
        ],
        'industry' => [
            'label'     => 'Industry Costs',
            'ref_types' => [
                'industry_job_fee',
                'factory_slot_rental_fee',
                'copying',
                'manufacturing',
                'reaction',
                'reverse_engineering',
                'researching_material_productivity',
                'researching_technology',
                'researching_time_productivity',
                'datacore_fee',
            ],
        ],
        'contracts' => [
            'label'     => 'Contracts',
            'ref_types' => [
                'contract_collateral',
                'contract_price',
                'contract_deposit',
                'contract_collateral_deposited_corp',
                'contract_reward_deposited_corp',
                'contract_reward_refund',
                'contract_price_payment_corp',
                'contract_auction_bid_corp',
                'contract_deposit_corp',
                'contract_deposit_sales_tax',
                'courier_mission_escrow',
            ],
        ],
        'structure_sov' => [
            'label'     => 'Structure & Sovereignty',
            'ref_types' => [
                'sovereignity_bill',
                'infrastructure_hub_maintenance',
                'structure_gate_jump',
                'jump_clone_activation_fee',
                'jump_clone_installation_fee',
                'upkeep_adjustment_fee',
            ],
        ],
        'insurance_war' => [
            'label'     => 'Insurance & War',
            'ref_types' => [
                'insurance',
                'war_fee',
                'war_ally_contract',
                'war_fee_surrender',
                'corporation_logo_change_cost',
            ],
        ],
        'other' => [
            'label'     => 'Other',
            'ref_types' => [],
        ],
    ];

    /**
     * Single-month expense breakdown for the Director "Expense Attribution"
     * tab. Same shape philosophy as ContributionService::getProfitAttribution:
     * a flat per-category list with per-row totals, counts, % of total,
     * and trend vs the immediately-preceding calendar month.
     *
     * Period strings are validated upstream by the controller; we
     * defensively re-default to the current month here to keep the
     * service safe to call directly.
     *
     * Return shape:
     *   corporation_id, period (YYYY-MM),
     *   total_expense, prior_total_expense,
     *   by_category: [
     *     {category, label, total, count, pct_of_total,
     *      trend_vs_prior_pct}, ...
     *   ]   // sorted by total descending
     */
    public function getCurrentPeriod(int $corpId, string $period): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = Carbon::now()->format('Y-m');
        }

        $current = $this->aggregatePeriod($corpId, $period);
        $prior   = $this->aggregatePeriod($corpId, $this->priorPeriod($period));

        $totalExpense = 0.0;
        foreach ($current as $row) {
            $totalExpense += $row['total'];
        }
        $priorTotalExpense = 0.0;
        foreach ($prior as $row) {
            $priorTotalExpense += $row['total'];
        }

        $byCategory = [];
        foreach (self::EXPENSE_CATEGORIES as $category => $meta) {
            $entry      = $current[$category] ?? ['total' => 0.0, 'count' => 0];
            $priorEntry = $prior[$category]   ?? ['total' => 0.0, 'count' => 0];

            $total      = (float) $entry['total'];
            $count      = (int)   $entry['count'];
            $priorTotal = (float) $priorEntry['total'];

            $pctOfTotal = $totalExpense > 0.0
                ? ($total / $totalExpense) * 100.0
                : 0.0;

            $trendPct = null;
            if ($priorTotal > 0.0) {
                $pct = (($total - $priorTotal) / $priorTotal) * 100.0;
                // Cap to ±1000% to mirror ContributionService's
                // trend-blow-up guard for tiny-but-non-zero prior windows.
                $trendPct = max(-1000.0, min(1000.0, $pct));
            }

            $byCategory[] = [
                'category'           => $category,
                'label'              => $meta['label'],
                'total'              => $total,
                'count'              => $count,
                'pct_of_total'       => (float) $pctOfTotal,
                'trend_vs_prior_pct' => $trendPct,
            ];
        }

        // Sort by total descending so the pie + table render with the
        // largest expense first. Matches getProfitAttribution.
        usort($byCategory, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'corporation_id'      => $corpId,
            'period'              => $period,
            'total_expense'       => (float) $totalExpense,
            'prior_total_expense' => (float) $priorTotalExpense,
            'by_category'         => $byCategory,
        ];
    }

    /**
     * Trailing-N-months expense breakdown for the stacked-bar trend
     * chart underneath the per-period pie + table. Pivots the per-
     * period aggregate so each category has a flat array of N values
     * matching the periods array order (oldest first).
     *
     * Months parameter is clamped to [1, 24] defensively; controller
     * validates upstream too.
     *
     * Return shape:
     *   corporation_id, months,
     *   periods: ['YYYY-MM', ...],  // oldest first
     *   categories: [
     *     {category, label, series: [float, ...], total},
     *     ...                       // sorted by trailing-window total desc
     *   ]
     */
    public function getTrend(int $corpId, int $months = 12): array
    {
        $months  = max(1, min(24, $months));
        $periods = $this->periodsForLastMonths($months);

        // Window bounds: start of the oldest period .. end of the newest.
        [$winStart] = $this->periodRange($periods[0]);
        [, $winEnd] = $this->periodRange($periods[count($periods) - 1]);

        // Map every explicitly-claimed ref_type to its category so a single
        // grouped query can be bucketed in PHP. Anything unmapped falls to
        // 'other'. This replaces the old "N periods x ~9 queries" fan-out
        // (which scanned the journal ~108 times and timed out on a large
        // corporation_wallet_journals) with ONE grouped scan over the whole
        // window plus the per-period alliance-tax heuristic below.
        $refTypeToCategory = [];
        foreach (self::EXPENSE_CATEGORIES as $category => $meta) {
            if (in_array($category, ['alliance_tax', 'other'], true)) {
                continue;
            }
            foreach ($meta['ref_types'] as $rt) {
                $refTypeToCategory[$rt] = $category;
            }
        }

        // [category => [period => total]] zero-initialised.
        $byCategoryPeriod = [];
        foreach (self::EXPENSE_CATEGORIES as $category => $meta) {
            $byCategoryPeriod[$category] = array_fill_keys($periods, 0.0);
        }

        // ONE grouped query: per (year-month, ref_type) expense sum across
        // the whole window. amount < 0 = expense; internal transfers filtered.
        $rowsQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corpId)
            ->whereBetween('date', [$winStart, $winEnd])
            ->where('amount', '<', 0);
        $rowsQuery = JournalFilters::excludeInternalTransfers($rowsQuery, $corpId);
        $rows = $rowsQuery
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') AS ym, ref_type, SUM(ABS(amount)) AS total")
            ->groupByRaw("DATE_FORMAT(date, '%Y-%m'), ref_type")
            ->get();

        $periodSet = array_fill_keys($periods, true);
        foreach ($rows as $r) {
            $ym = (string) $r->ym;
            if (! isset($periodSet[$ym])) {
                continue;
            }
            $cat = $refTypeToCategory[(string) $r->ref_type] ?? 'other';
            $byCategoryPeriod[$cat][$ym] += (float) $r->total;
        }

        // Alliance Tax is recipient/keyword matched, not ref_type bucketed,
        // so it still needs the AllianceTaxService heuristic once per period.
        // corp_withdrawal then sheds the corporation_account_withdrawal slice
        // the alliance match already claimed so the two never double-count -
        // same split semantics as aggregateRange().
        foreach ($periods as $period) {
            [$from, $to] = $this->periodRange($period);
            $allianceShares = app(AllianceTaxService::class)
                ->getAllianceTaxByRefType($corpId, $from, $to);

            $allianceTotal = 0.0;
            foreach ($allianceShares as $share) {
                $allianceTotal += (float) ($share['amount'] ?? 0);
            }
            $byCategoryPeriod['alliance_tax'][$period] = $allianceTotal;

            $cwShare = $allianceShares['corporation_account_withdrawal'] ?? null;
            if ($cwShare !== null) {
                $byCategoryPeriod['corp_withdrawal'][$period] = max(
                    0.0,
                    $byCategoryPeriod['corp_withdrawal'][$period] - (float) ($cwShare['amount'] ?? 0)
                );
            }
        }

        $categories = [];
        foreach (self::EXPENSE_CATEGORIES as $category => $meta) {
            $series = [];
            $total  = 0.0;
            foreach ($periods as $p) {
                $value = (float) ($byCategoryPeriod[$category][$p] ?? 0.0);
                $series[] = $value;
                $total   += $value;
            }
            $categories[] = [
                'category' => $category,
                'label'    => $meta['label'],
                'series'   => $series,
                'total'    => $total,
            ];
        }

        // Sort by trailing-window total descending so the largest
        // category sits at the bottom of the stack (Chart.js stacks
        // bottom-up in dataset order; the bar legend reads top-down
        // by reversed order in the chart code).
        usort($categories, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'corporation_id' => $corpId,
            'months'         => $months,
            'periods'        => $periods,
            'categories'     => $categories,
        ];
    }

    /**
     * Arbitrary-range expense breakdown for the scheduled report
     * surfaces (weekly / monthly / quarterly / annual retrospectives).
     * Same 9-category taxonomy as getCurrentPeriod() and the same
     * Alliance Tax / Corp Withdrawal split semantics, but no
     * prior-period comparison (the report does its own period
     * comparison via prior_period and trend math doesn't carry to a
     * range that isn't a calendar month).
     *
     * $from and $to are anything DB::whereBetween('date', ...) accepts:
     * Carbon, DateTimeInterface, or 'Y-m-d H:i:s' strings.
     *
     * Return shape:
     *   corporation_id, from, to,
     *   total_expense,
     *   by_category: [
     *     {category, label, total, count, pct_of_total},
     *     ...
     *   ]  // sorted by total descending
     */
    public function getForRange(int $corpId, $from, $to): array
    {
        $aggregate = $this->aggregateRange($corpId, $from, $to);

        $totalExpense = 0.0;
        foreach ($aggregate as $row) {
            $totalExpense += (float) $row['total'];
        }

        $byCategory = [];
        foreach (self::EXPENSE_CATEGORIES as $category => $meta) {
            $entry = $aggregate[$category] ?? ['total' => 0.0, 'count' => 0];
            $total = (float) $entry['total'];
            $count = (int)   $entry['count'];
            $pctOfTotal = $totalExpense > 0.0 ? ($total / $totalExpense) * 100.0 : 0.0;

            $byCategory[] = [
                'category'     => $category,
                'label'        => $meta['label'],
                'total'        => $total,
                'count'        => $count,
                'pct_of_total' => (float) $pctOfTotal,
            ];
        }

        // Sort by total descending so the largest category renders first
        // in the report table.
        usort($byCategory, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'corporation_id' => $corpId,
            'from'           => is_string($from) ? $from : (string) $from,
            'to'             => is_string($to)   ? $to   : (string) $to,
            'total_expense'  => (float) $totalExpense,
            'by_category'    => $byCategory,
        ];
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * One-period per-category aggregate. Returns an associative array
     * keyed by category name with ['total' => float, 'count' => int]
     * values. Categories with no rows in the period appear with zero
     * total / count.
     *
     * Implementation walks each category individually:
     *   - 'alliance_tax' pulls from AllianceTaxService::getAllianceTaxByRefType
     *     and sums the per-ref shares.
     *   - 'corp_withdrawal' queries the standard ref_type sum, then
     *     subtracts the alliance-tax share whose source ref_type was
     *     corporation_account_withdrawal so the two never double-count.
     *   - explicit-ref_types categories run a single SUM/COUNT over
     *     the ref_type IN (...) list.
     *   - 'other' runs a SUM/COUNT excluding every ref_type already
     *     claimed by an earlier category.
     */
    private function aggregatePeriod(int $corpId, string $period): array
    {
        [$from, $to] = $this->periodRange($period);
        return $this->aggregateRange($corpId, $from, $to);
    }

    /**
     * Per-category aggregate for an arbitrary date range. Same taxonomy
     * + Alliance-Tax / Corp-Withdrawal split as aggregatePeriod; pulled
     * out so getForRange() can reuse it without having to fake a
     * single-month period string.
     */
    private function aggregateRange(int $corpId, $from, $to): array
    {
        // Collect every ref_type used by the explicit categories so
        // 'other' can exclude them. corp_withdrawal's ref_types are
        // included; the alliance-tax slice that comes FROM those
        // ref_types is just a subtraction, the ref_types are still
        // owned by corp_withdrawal for "other" classification.
        $claimedRefTypes = [];
        foreach (self::EXPENSE_CATEGORIES as $category => $meta) {
            if (in_array($category, ['alliance_tax', 'other'], true)) {
                continue;
            }
            foreach ($meta['ref_types'] as $rt) {
                $claimedRefTypes[$rt] = true;
            }
        }
        $claimedRefTypes = array_keys($claimedRefTypes);

        $result = [];

        // ---- alliance_tax: special case via AllianceTaxService ----
        // getAllianceTaxByRefType returns per-source-ref_type shares of
        // outflows matching the configured alliance-tax recipients /
        // keywords. Sum amount + count across every source ref_type.
        $allianceShares = app(AllianceTaxService::class)
            ->getAllianceTaxByRefType($corpId, $from, $to);

        $allianceTotal = 0.0;
        $allianceCount = 0;
        foreach ($allianceShares as $share) {
            $allianceTotal += (float) ($share['amount'] ?? 0);
            $allianceCount += (int)   ($share['count']  ?? 0);
        }
        $result['alliance_tax'] = [
            'total' => $allianceTotal,
            'count' => $allianceCount,
        ];

        // ---- explicit-ref_types categories ----
        foreach (self::EXPENSE_CATEGORIES as $category => $meta) {
            if ($category === 'alliance_tax' || $category === 'other') {
                continue;
            }
            if (empty($meta['ref_types'])) {
                $result[$category] = ['total' => 0.0, 'count' => 0];
                continue;
            }

            $query = DB::table('corporation_wallet_journals')
                ->where('corporation_id', $corpId)
                ->whereBetween('date', [$from, $to])
                ->whereIn('ref_type', $meta['ref_types'])
                ->where('amount', '<', 0);
            $query = JournalFilters::excludeInternalTransfers($query, $corpId);

            $row = $query
                ->selectRaw('SUM(ABS(amount)) AS total, COUNT(*) AS cnt')
                ->first();

            $result[$category] = [
                'total' => (float) ($row->total ?? 0),
                'count' => (int)   ($row->cnt   ?? 0),
            ];
        }

        // ---- corp_withdrawal: subtract the alliance-tax slice ----
        // The alliance-tax breakdown returns shares keyed by source
        // ref_type. Whatever fraction came from
        // corporation_account_withdrawal is BOTH alliance_tax AND
        // corp_withdrawal in the raw query above; subtract it from
        // corp_withdrawal so the two categories sum cleanly.
        $cwShare = $allianceShares['corporation_account_withdrawal'] ?? null;
        if ($cwShare !== null && isset($result['corp_withdrawal'])) {
            $result['corp_withdrawal']['total'] = max(0.0, $result['corp_withdrawal']['total'] - (float) $cwShare['amount']);
            $result['corp_withdrawal']['count'] = max(0,   $result['corp_withdrawal']['count'] - (int)   $cwShare['count']);
        }

        // ---- other: everything not matched above ----
        $otherQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corpId)
            ->whereBetween('date', [$from, $to])
            ->where('amount', '<', 0);
        if (! empty($claimedRefTypes)) {
            $otherQuery->whereNotIn('ref_type', $claimedRefTypes);
        }
        $otherQuery = JournalFilters::excludeInternalTransfers($otherQuery, $corpId);

        $otherRow = $otherQuery
            ->selectRaw('SUM(ABS(amount)) AS total, COUNT(*) AS cnt')
            ->first();

        $result['other'] = [
            'total' => (float) ($otherRow->total ?? 0),
            'count' => (int)   ($otherRow->cnt   ?? 0),
        ];

        return $result;
    }

    /**
     * Convert a "YYYY-MM" period string to [first-day, last-day]
     * Carbon timestamps suitable for whereBetween('date', ...).
     *
     * @return array{0: string, 1: string}
     */
    private function periodRange(string $period): array
    {
        [$y, $m] = array_map('intval', explode('-', $period));
        $start = Carbon::createFromDate($y, $m, 1)->startOfDay();
        $end   = $start->copy()->endOfMonth()->endOfDay();
        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    /** "YYYY-MM" of the calendar month immediately before $period. */
    private function priorPeriod(string $period): string
    {
        [$y, $m] = array_map('intval', explode('-', $period));
        return Carbon::createFromDate($y, $m, 1)->subMonth()->format('Y-m');
    }

    /**
     * Trailing N period strings, OLDEST FIRST so the trend chart reads
     * left-to-right naturally. Matches AllianceTaxService's convention.
     *
     * @return array<int,string>
     */
    private function periodsForLastMonths(int $months): array
    {
        $now = Carbon::now();
        $periods = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $periods[] = $now->copy()->subMonths($i)->format('Y-m');
        }
        return $periods;
    }
}
