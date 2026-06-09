<?php
namespace CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use CorpWalletManager\Services\WebhookService;
use CorpWalletManager\Services\ContributionService;
use CorpWalletManager\Services\AllianceTaxService;
use CorpWalletManager\Services\ExpenseAttributionService;
use CorpWalletManager\Services\AnomalyReportService;
use CorpWalletManager\Services\EntityNameResolver;
use CorpWalletManager\Support\JournalFilters;

class GenerateReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 1;

    protected $corporationId;
    protected $reportType;
    protected $dateFrom;
    protected $dateTo;
    protected $sections;
    protected $sendToDiscord;

    public function __construct($corporationId, $reportType, $dateFrom, $dateTo, $sections = [], $sendToDiscord = false)
    {
        $this->corporationId = $corporationId;
        $this->reportType = $reportType;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->sections = $sections;
        $this->sendToDiscord = $sendToDiscord;
    }

    public function tags(): array
    {
        return [
            'corpwalletmanager',
            'reports',
            'type:' . $this->reportType,
            'corp:' . ($this->corporationId ?? 'all'),
        ];
    }

    /**
     * Lazy-normalized accessors. The constructor and queue-serialized
     * wakeup paths may leave $this->dateFrom / $this->dateTo as Carbon,
     * a DateTimeInterface, or a plain string depending on the caller
     * (ReportsController passes Carbon; GenerateReportCommand has
     * historically passed strings). Normalizing on every read guarantees
     * Carbon downstream regardless of how the job was dispatched and
     * regardless of any serialization quirk in the queue driver.
     */
    private function dateFrom(): Carbon
    {
        if (! $this->dateFrom instanceof Carbon) {
            $this->dateFrom = Carbon::parse($this->dateFrom);
        }
        return $this->dateFrom;
    }

    private function dateTo(): Carbon
    {
        if (! $this->dateTo instanceof Carbon) {
            $this->dateTo = Carbon::parse($this->dateTo);
        }
        return $this->dateTo;
    }

    public function handle()
    {
        try {
            Log::info('GenerateReport started', [
                'corporation_id' => $this->corporationId,
                'type' => $this->reportType,
                'from' => $this->dateFrom(),
                'to' => $this->dateTo()
            ]);

            // Get corporation name
            $corpInfo = DB::table('corporation_infos')
                ->where('corporation_id', $this->corporationId)
                ->first();
            
            $corpName = $corpInfo ? $corpInfo->name : "Corporation {$this->corporationId}";

            // Generate report data based on type
            $reportData = $this->generateReportData();
            
            // Save report to database
            $reportId = DB::table('corpwalletmanager_reports')->insertGetId([
                'corporation_id' => $this->corporationId,
                'report_type' => $this->reportType,
                'date_from' => $this->dateFrom(),
                'date_to' => $this->dateTo(),
                'data' => json_encode($reportData),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Deliver to subscribed Discord webhooks
            if ($this->sendToDiscord) {
                $this->deliverToWebhooks($corpName, $reportData);
            }

            Log::info('GenerateReport completed', [
                'report_id' => $reportId,
                'corporation_id' => $this->corporationId
            ]);

        } catch (\Exception $e) {
            Log::error('GenerateReport failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function generateReportData()
    {
        $data = [
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'from' => $this->dateFrom()->toDateString(),
                'to' => $this->dateTo()->toDateString(),
                'days' => $this->dateFrom()->diffInDays($this->dateTo()) + 1
            ],
            'report_kind' => $this->reportType,
            'report_description' => $this->getReportDescription(),
        ];

        // Per-type shape selectors. Each report type composes a
        // genuinely distinct payload rather than just relabeling the
        // same balance/income/expense/risk tuple. The PDF and Discord
        // templates gate each section on its own data key, so a
        // missing key renders nothing rather than erroring. Retro
        // cadences (weekly / monthly / quarterly / annual) keep the
        // full retrofit shape — daily stays terse, executive is a
        // KPI snapshot, financial is the deep dive, division is per
        // wallet division, custom keeps the legacy flexible shape.
        switch ($this->reportType) {
            case 'executive':
                $this->composeExecutiveShape($data);
                break;
            case 'financial':
                $this->composeFinancialShape($data);
                break;
            case 'division':
                $this->composeDivisionShape($data);
                break;
            case 'daily':
                $this->composeDailyShape($data);
                break;
            case 'weekly':
            case 'monthly':
            case 'quarterly':
            case 'annual':
                $this->composeRetroShape($data);
                break;
            case 'custom':
            default:
                $this->composeCustomShape($data);
                break;
        }

        return $data;
    }

    /**
     * Executive Summary: KPI snapshot for non-finance directors.
     *
     * Balance / income totals / expense totals / risk / top 3
     * contributors / one-line headline. Optimized for a single-glance
     * read. NO breakdown, NO division detail, NO notable transactions,
     * NO activity breakdown, NO expense attribution, NO alliance tax.
     */
    protected function composeExecutiveShape(array &$data): void
    {
        $data['balance_history']  = $this->getBalanceHistory();
        $data['income_analysis']  = $this->getIncomeAnalysis();
        $data['expense_analysis'] = $this->getExpenseAnalysis();
        $data['risk_assessment']  = $this->getRiskAssessment();
        $data['top_contributors'] = $this->getTopContributorsForPeriod(3);
        $data['prior_period']     = $this->getPriorPeriodComparisonGeneric();
        $data['one_line_headline'] = $this->buildExecutiveHeadline($data);
    }

    /**
     * Financial Analysis: deep dive into where ISK came from and went.
     *
     * Balance, income + expense WITH per-ref_type breakdowns, the full
     * transaction_breakdown, the 5-bucket activity attribution, the
     * 9-category expense attribution, top 10 notable transactions each
     * way, alliance tax remit (when configured), MM compliance (when
     * MM installed), and the full risk assessment. NO top_contributors
     * (that is a people view, not a money view) and NO monthly
     * breakdown (this is a snapshot, not a trend report).
     */
    protected function composeFinancialShape(array &$data): void
    {
        $data['balance_history']        = $this->getBalanceHistory();
        $data['income_analysis']        = $this->getIncomeAnalysis();
        $data['income_breakdown']       = $this->getIncomeBreakdown(20);
        $data['expense_analysis']       = $this->getExpenseAnalysis();
        $data['expense_breakdown']      = $this->getExpenseBreakdown(20);
        $data['transaction_breakdown']  = $this->getTransactionBreakdown();
        $data['activity_breakdown']     = $this->getActivityBreakdown();
        $data['expense_attribution']    = $this->getExpenseAttributionForRange();
        $data['notable_transactions']   = $this->getNotableTransactions(10);
        $data['alliance_tax_remit']     = $this->getAllianceTaxRemit();
        $data['alliance_tax_expected']  = $this->getAllianceTaxExpectedForRange();
        $data['mm_compliance']          = $this->getMmComplianceForRange();
        $data['risk_assessment']        = $this->getRiskAssessment();
    }

    /**
     * Division Performance: per-wallet-division focus.
     *
     * Corp-wide balance plus rich per-division detail (balance change,
     * income/expense totals, transaction count, top 5 incoming +
     * outgoing ref_types per division) plus an internal-transfers
     * summary so directors can see inter-division movement (those rows
     * are excluded from the corp totals by JournalFilters but are
     * highly visible at division level). NO top_contributors, NO
     * activity_breakdown, NO expense_attribution, NO notable_transactions.
     */
    protected function composeDivisionShape(array &$data): void
    {
        $data['balance_history']            = $this->getBalanceHistory();
        $data['division_summary']           = $this->getDivisionSummaryRich();
        $data['internal_transfers_summary'] = $this->getInternalTransfersSummary();
        $data['risk_assessment']            = $this->getRiskAssessment();
    }

    /**
     * Daily Summary: terse pulse-check.
     *
     * Balance + income total + expense total + risk. That's it; no
     * breakdowns, no per-division, no notable. A director scanning a
     * daily report just wants to know "did anything weird happen".
     */
    protected function composeDailyShape(array &$data): void
    {
        $data['balance_history']  = $this->getBalanceHistory();
        $data['income_analysis']  = $this->getIncomeAnalysis();
        $data['expense_analysis'] = $this->getExpenseAnalysis();
        $data['risk_assessment']  = $this->getRiskAssessment();
    }

    /**
     * Retro Shape: weekly / monthly / quarterly / annual.
     *
     * The full retrofit shape: balance, income, expense, transaction
     * breakdown, per-division summary, risk, plus per-month series,
     * top contributors, activity buckets, alliance tax remit, notable
     * transactions, milestones, prior-period comparison, expense
     * attribution, expected alliance tax, MM compliance, and anomaly
     * summary. The PDF template branches via @if guards so cadences
     * with missing data (e.g. milestone state table absent on older
     * installs) skip gracefully.
     */
    protected function composeRetroShape(array &$data): void
    {
        $data['balance_history']       = $this->getBalanceHistory();
        $data['income_analysis']       = $this->getIncomeAnalysis();
        $data['expense_analysis']      = $this->getExpenseAnalysis();
        $data['transaction_breakdown'] = $this->getTransactionBreakdown();
        $data['division_summary']      = $this->getDivisionSummary();
        $data['risk_assessment']       = $this->getRiskAssessment();

        $data['retrospective'] = [
            'kind' => $this->reportType,
        ];
        $data['monthly_breakdown']     = $this->getMonthlyBreakdown();
        $data['top_contributors']      = $this->getTopContributorsForPeriod(10);
        $data['activity_breakdown']    = $this->getActivityBreakdown();
        $data['alliance_tax_remit']    = $this->getAllianceTaxRemit();
        $data['notable_transactions']  = $this->getNotableTransactions(10);
        $data['member_milestones']     = $this->getMemberMilestonesReached();
        $data['prior_period']          = $this->getPriorPeriodComparison();
        $data['expense_attribution']   = $this->getExpenseAttributionForRange();
        $data['alliance_tax_expected'] = $this->getAllianceTaxExpectedForRange();
        $data['mm_compliance']         = $this->getMmComplianceForRange();
        $data['anomaly_summary']       = $this->getAnomalySummaryForRange();
    }

    /**
     * Custom Shape: legacy flexible shape, honours $this->sections.
     *
     * Preserved as-is so existing custom report definitions keep
     * rendering the same way. When no sections are specified
     * shouldIncludeSection returns true for everything and the report
     * ends up with the original balance / income / expense /
     * breakdown / division / risk tuple.
     */
    protected function composeCustomShape(array &$data): void
    {
        if ($this->shouldIncludeSection('balance_history')) {
            $data['balance_history'] = $this->getBalanceHistory();
        }
        if ($this->shouldIncludeSection('income_analysis')) {
            $data['income_analysis'] = $this->getIncomeAnalysis();
        }
        if ($this->shouldIncludeSection('expense_analysis')) {
            $data['expense_analysis'] = $this->getExpenseAnalysis();
        }
        if ($this->shouldIncludeSection('transaction_breakdown')) {
            $data['transaction_breakdown'] = $this->getTransactionBreakdown();
        }
        if ($this->shouldIncludeSection('division_summary')) {
            $data['division_summary'] = $this->getDivisionSummary();
        }
        if ($this->shouldIncludeSection('risk_assessment')) {
            $data['risk_assessment'] = $this->getRiskAssessment();
        }
    }

    /**
     * Human-readable copy explaining what each report type covers.
     * Rendered at the top of the PDF so directors looking at an
     * exported file understand the scope without having to remember
     * which dropdown option produced it.
     */
    protected function getReportDescription(): string
    {
        return match ($this->reportType) {
            'executive' => 'High-level financial KPIs for non-finance directors. Opening / closing balance, income + expense totals, runway, and the top three contributors. Use this for a single-glance read at directorate meetings.',
            'financial' => 'Detailed investigation of where ISK came from and went. Full per-ref_type income + expense breakdown, profit attribution by activity, expense attribution by category, notable single transactions, alliance tax reconciliation, and MM compliance.',
            'division'  => 'Per-wallet-division focus. Net change, income, expense, transaction count, and top ref_types in + out for each of the seven corp divisions, plus a summary of internal (inter-division) transfers that the corp-wide totals deliberately exclude.',
            'daily'     => 'Terse daily pulse-check. Balance, income total, expense total, and risk assessment.',
            'weekly'    => 'Weekly retrospective. Same shape as monthly / quarterly / annual: monthly trend, top contributors, activity mix, expense attribution, notable transactions, alliance tax expected vs actual, MM compliance, milestones, anomaly summary, and week-over-week comparison.',
            'monthly'   => 'Monthly retrospective. Monthly trend, top contributors, activity mix, expense attribution, notable transactions, alliance tax expected vs actual, MM compliance, milestones, anomaly summary, and month-over-month comparison.',
            'quarterly' => 'Three-month retrospective. Monthly trend, top contributors, activity mix, expense attribution, notable transactions, alliance tax expected vs actual, MM compliance, milestones, anomaly summary, and quarter-over-quarter comparison.',
            'annual'    => 'Full-year retrospective. Monthly trend, top contributors, activity mix, expense attribution, notable transactions, alliance tax expected vs actual, MM compliance, milestones, anomaly summary, and year-over-year comparison.',
            'custom'    => 'Operator-defined section selection over an arbitrary date range.',
            default     => 'Corporation wallet report.',
        };
    }

    /**
     * Per-ref_type income breakdown (positive amounts only). Used by
     * Financial Analysis to show where income came from. Internal
     * transfers excluded; alliance-tax split applied so the breakdown
     * reflects how much of e.g. player_donations was the monthly
     * alliance remit vs genuine donations.
     */
    protected function getIncomeBreakdown(int $limit = 20)
    {
        $query = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()])
            ->where('amount', '>', 0);
        $query = JournalFilters::excludeInternalTransfers($query, (int) $this->corporationId);

        $rows = $query->selectRaw('
                ref_type,
                COUNT(*) as count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount
            ')
            ->groupBy('ref_type')
            ->orderByDesc('total_amount')
            ->limit($limit + 2) // headroom for alliance_tax split, then take($limit) below
            ->get();

        $rows = app(AllianceTaxService::class)
            ->applyAllianceTaxBreakdown($rows, (int) $this->corporationId, $this->dateFrom(), $this->dateTo());

        return collect($rows)->take($limit);
    }

    /**
     * Per-ref_type expense breakdown (negative amounts only). Used by
     * Financial Analysis. Same alliance-tax split applied so an
     * alliance-tax-as-corp-withdrawal shows up as its own bucket
     * rather than diluting the generic Corp Withdrawal line.
     */
    protected function getExpenseBreakdown(int $limit = 20)
    {
        $query = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()])
            ->where('amount', '<', 0);
        $query = JournalFilters::excludeInternalTransfers($query, (int) $this->corporationId);

        $rows = $query->selectRaw('
                ref_type,
                COUNT(*) as count,
                SUM(ABS(amount)) as total_amount,
                AVG(ABS(amount)) as avg_amount
            ')
            ->groupBy('ref_type')
            ->orderByDesc('total_amount')
            ->limit($limit + 2)
            ->get();

        $rows = app(AllianceTaxService::class)
            ->applyAllianceTaxBreakdown($rows, (int) $this->corporationId, $this->dateFrom(), $this->dateTo());

        return collect($rows)->take($limit);
    }

    /**
     * Per-division summary with deep ref-type detail for the Division
     * Performance report. Builds on getDivisionSummary() by adding
     * start/end balance + change per division plus the top 5 incoming
     * and top 5 outgoing ref_types within each division.
     */
    protected function getDivisionSummaryRich(): array
    {
        $corpId = (int) $this->corporationId;

        // Baseline aggregates per division — count, income, expense,
        // net change. Same query the basic getDivisionSummary uses.
        $baseQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corpId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()]);
        $baseQuery = JournalFilters::excludeInternalTransfers($baseQuery, $corpId);

        $baseRows = $baseQuery->selectRaw('
                division,
                COUNT(*) as transactions,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses,
                SUM(amount) as net_change
            ')
            ->groupBy('division')
            ->orderBy('division')
            ->get();

        // Opening balance per division (sum of all journal rows before
        // the period start). Internal transfers stay in the per-division
        // running balance because they matter for that division even
        // when they net to zero at the corp level.
        $startBalances = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corpId)
            ->where('date', '<', $this->dateFrom())
            ->selectRaw('division, SUM(amount) as opening_balance')
            ->groupBy('division')
            ->pluck('opening_balance', 'division');

        // In-period net change per division (no internal-transfer
        // exclusion, on purpose — division balance does include the
        // inter-division movement). Used to compute closing balance.
        $periodNetByDiv = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corpId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()])
            ->selectRaw('division, SUM(amount) as period_net')
            ->groupBy('division')
            ->pluck('period_net', 'division');

        // Top 5 ref_types in + out per division. Single grouped query
        // covers all divisions, then we slice it down in PHP.
        $refTypeRows = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corpId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()]);
        $refTypeRows = JournalFilters::excludeInternalTransfers($refTypeRows, $corpId);
        $refTypeRows = $refTypeRows
            ->selectRaw('
                division,
                ref_type,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as in_amount,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as out_amount,
                COUNT(*) as tx_count
            ')
            ->groupBy('division', 'ref_type')
            ->get();

        // Group ref_type rows by division for fast slicing.
        $byDivision = [];
        foreach ($refTypeRows as $r) {
            $div = (int) $r->division;
            $byDivision[$div][] = $r;
        }

        $out = [];
        foreach ($baseRows as $row) {
            $div = (int) $row->division;
            $opening = (float) ($startBalances[$div] ?? 0);
            $closing = $opening + (float) ($periodNetByDiv[$div] ?? 0);

            $rt = $byDivision[$div] ?? [];

            $topIn = collect($rt)
                ->filter(fn ($r) => (float) $r->in_amount > 0)
                ->sortByDesc(fn ($r) => (float) $r->in_amount)
                ->take(5)
                ->map(fn ($r) => [
                    'ref_type' => (string) $r->ref_type,
                    'amount'   => (float) $r->in_amount,
                    'count'    => (int) $r->tx_count,
                ])
                ->values()
                ->all();

            $topOut = collect($rt)
                ->filter(fn ($r) => (float) $r->out_amount > 0)
                ->sortByDesc(fn ($r) => (float) $r->out_amount)
                ->take(5)
                ->map(fn ($r) => [
                    'ref_type' => (string) $r->ref_type,
                    'amount'   => (float) $r->out_amount,
                    'count'    => (int) $r->tx_count,
                ])
                ->values()
                ->all();

            $out[] = [
                'division'         => $div,
                'transactions'     => (int) $row->transactions,
                'income'           => (float) $row->income,
                'expenses'         => (float) $row->expenses,
                'net_change'       => (float) $row->net_change,
                'balance_start'    => $opening,
                'balance_end'      => $closing,
                'top_ref_types_in' => $topIn,
                'top_ref_types_out'=> $topOut,
            ];
        }

        return $out;
    }

    /**
     * Internal (inter-division) transfer summary. Counts + sums rows
     * where first_party_id == second_party_id == corporation_id, which
     * is how EVE logs ISK moved between corp wallet divisions. These
     * rows are excluded from corp totals by JournalFilters but matter
     * for the Division Performance report because each transfer
     * shows up in two division balances (sending -X, receiving +X).
     */
    protected function getInternalTransfersSummary(): array
    {
        $corpId = (int) $this->corporationId;

        $rows = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corpId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()])
            ->where('first_party_id', $corpId)
            ->where('second_party_id', $corpId)
            ->where('amount', '>', 0) // only the receiving side; halves the row count, sum is same
            ->selectRaw('
                division,
                COUNT(*) as transfer_count,
                SUM(amount) as total_amount
            ')
            ->groupBy('division')
            ->orderBy('division')
            ->get();

        $total = 0.0;
        $count = 0;
        $perDivision = [];
        foreach ($rows as $r) {
            $amount = (float) $r->total_amount;
            $cnt    = (int) $r->transfer_count;
            $total += $amount;
            $count += $cnt;
            $perDivision[] = [
                'division'       => (int) $r->division,
                'transfer_count' => $cnt,
                'total_amount'   => $amount,
            ];
        }

        return [
            'total_count'   => $count,
            'total_amount'  => $total,
            'per_division'  => $perDivision,
        ];
    }

    /**
     * Build the executive-summary headline string. Example:
     *   "Mercurialis Inc. is up 2.1B ISK this period (+12.4% from
     *    prior period), MEDIUM risk with 45 days of runway"
     *
     * Renders to a single sentence the operator can paste into a
     * forum post or Discord message. Fed the already-populated
     * sections so it never re-queries.
     */
    protected function buildExecutiveHeadline(array $data): string
    {
        $corpInfo = DB::table('corporation_infos')
            ->where('corporation_id', $this->corporationId)
            ->first();
        $corpName = $corpInfo ? $corpInfo->name : "Corporation {$this->corporationId}";

        $bh   = $data['balance_history']  ?? [];
        $risk = $data['risk_assessment']  ?? [];
        $pp   = $data['prior_period']     ?? null;

        $change = (float) ($bh['change'] ?? 0);
        $direction = $change >= 0 ? 'up' : 'down';
        $magnitude = $this->formatIskShort(abs($change));

        $riskLevel = (string) ($risk['risk_level'] ?? 'UNKNOWN');
        $runway    = (float) ($risk['days_of_runway'] ?? 0);

        $sentence = sprintf(
            '%s is %s %s ISK this period',
            $corpName,
            $direction,
            $magnitude
        );

        if ($pp !== null && isset($pp['net_change'])) {
            $priorChange = (float) $pp['net_change'];
            if (abs($priorChange) > 0.0) {
                $deltaPct = (($change - $priorChange) / abs($priorChange)) * 100.0;
                $sentence .= sprintf(' (%+.1f%% from prior period)', $deltaPct);
            } else {
                $sentence .= ' (no prior-period baseline)';
            }
        }

        $sentence .= sprintf(
            ', %s risk with %.0f days of runway.',
            $riskLevel,
            $runway
        );

        return $sentence;
    }

    /**
     * Compact ISK formatter for the headline: 1.2B, 450M, 78K. Falls
     * back to full integer rendering for sub-thousand values.
     */
    protected function formatIskShort(float $amount): string
    {
        if ($amount >= 1_000_000_000) {
            return number_format($amount / 1_000_000_000, 1) . 'B';
        }
        if ($amount >= 1_000_000) {
            return number_format($amount / 1_000_000, 1) . 'M';
        }
        if ($amount >= 1_000) {
            return number_format($amount / 1_000, 1) . 'K';
        }
        return number_format($amount, 0);
    }

    /**
     * Look up the prior period's stored report for executive / daily
     * cadence-agnostic comparison. Uses a same-length window
     * immediately before [dateFrom, dateTo]. Returns null when no
     * stored report covers that window.
     */
    protected function getPriorPeriodComparisonGeneric(): ?array
    {
        $corpId    = (int) $this->corporationId;
        $rangeDays = $this->dateFrom()->diffInDays($this->dateTo()) + 1;
        $priorTo   = $this->dateFrom()->copy()->subDay();
        $priorFrom = $priorTo->copy()->subDays(max(0, $rangeDays - 1));

        try {
            $prior = DB::table('corpwalletmanager_reports')
                ->where('corporation_id', $corpId)
                ->where('report_type', $this->reportType)
                ->whereDate('date_from', $priorFrom->toDateString())
                ->whereDate('date_to', $priorTo->toDateString())
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        if ($prior === null) {
            return null;
        }

        $priorData = is_string($prior->data) ? json_decode($prior->data, true) : (array) $prior->data;
        if (! is_array($priorData)) {
            return null;
        }

        return [
            'kind'         => $this->reportType,
            'period_from'  => $priorFrom->toDateString(),
            'period_to'    => $priorTo->toDateString(),
            'report_id'    => (int) $prior->id,
            'income'       => (float) ($priorData['income_analysis']['total']   ?? 0),
            'expense'      => (float) ($priorData['expense_analysis']['total']  ?? 0),
            'net_change'   => (float) ($priorData['balance_history']['change'] ?? 0),
            'end_balance'  => (float) ($priorData['balance_history']['end_balance'] ?? 0),
        ];
    }

    protected function shouldIncludeSection($section)
    {
        // If no sections specified, include all for the report type
        if (empty($this->sections)) {
            return true;
        }
        return in_array($section, $this->sections);
    }

    protected function getBalanceHistory()
    {
        $corpId = (int) $this->corporationId;

        $balancesQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()]);
        $balancesQuery = JournalFilters::excludeInternalTransfers($balancesQuery, $corpId);

        $balances = $balancesQuery
            ->selectRaw('DATE(date) as date, SUM(amount) as daily_change')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $startBalanceQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->where('date', '<', $this->dateFrom());
        $startBalanceQuery = JournalFilters::excludeInternalTransfers($startBalanceQuery, $corpId);
        $startBalance = $startBalanceQuery->sum('amount');

        $endBalance = $startBalance + $balances->sum('daily_change');

        return [
            'start_balance' => $startBalance,
            'end_balance' => $endBalance,
            'change' => $endBalance - $startBalance,
            'change_percent' => $startBalance != 0 ? (($endBalance - $startBalance) / abs($startBalance)) * 100 : 0,
            'daily_data' => $balances
        ];
    }

    protected function getIncomeAnalysis()
    {
        $query = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()])
            ->where('amount', '>', 0);
        $query = JournalFilters::excludeInternalTransfers($query, (int) $this->corporationId);

        $income = $query->selectRaw('
                COUNT(*) as transaction_count,
                SUM(amount) as total_income,
                AVG(amount) as avg_income,
                MAX(amount) as max_income,
                MIN(amount) as min_income
            ')
            ->first();

        return [
            'total' => $income->total_income ?? 0,
            'transactions' => $income->transaction_count ?? 0,
            'average' => $income->avg_income ?? 0,
            'highest' => $income->max_income ?? 0,
            'lowest' => $income->min_income ?? 0
        ];
    }

    protected function getExpenseAnalysis()
    {
        $query = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()])
            ->where('amount', '<', 0);
        $query = JournalFilters::excludeInternalTransfers($query, (int) $this->corporationId);

        $expenses = $query->selectRaw('
                COUNT(*) as transaction_count,
                SUM(ABS(amount)) as total_expenses,
                AVG(ABS(amount)) as avg_expense,
                MAX(ABS(amount)) as max_expense,
                MIN(ABS(amount)) as min_expense
            ')
            ->first();

        return [
            'total' => $expenses->total_expenses ?? 0,
            'transactions' => $expenses->transaction_count ?? 0,
            'average' => $expenses->avg_expense ?? 0,
            'highest' => $expenses->max_expense ?? 0,
            'lowest' => $expenses->min_expense ?? 0
        ];
    }

    protected function getTransactionBreakdown()
    {
        $query = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()]);
        $query = JournalFilters::excludeInternalTransfers($query, (int) $this->corporationId);

        $breakdown = $query->selectRaw('
                ref_type,
                COUNT(*) as count,
                SUM(amount) as total_amount,
                AVG(amount) as avg_amount
            ')
            ->groupBy('ref_type')
            ->orderByDesc('total_amount')
            ->limit(20) // raised from 10 so the alliance_tax split below stays inside the cutoff
            ->get();

        // Split out alliance_tax from the corporation_account_withdrawal /
        // player_donation buckets so the breakdown reflects how much of
        // "Corp Withdrawal" was really just the monthly alliance remit
        // vs genuine other outflows. No-op when no alliance-tax match
        // rules are configured in Settings.
        $breakdown = app(\CorpWalletManager\Services\AllianceTaxService::class)
            ->applyAllianceTaxBreakdown($breakdown, (int) $this->corporationId, $this->dateFrom(), $this->dateTo());

        // Top 10 after the split (was 10 before the limit bump above).
        return collect($breakdown)->take(10);
    }

    protected function getDivisionSummary()
    {
        $query = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()]);
        $query = JournalFilters::excludeInternalTransfers($query, (int) $this->corporationId);

        $divisions = $query->selectRaw('
                division,
                COUNT(*) as transactions,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses,
                SUM(amount) as net_change
            ')
            ->groupBy('division')
            ->get();

        return $divisions;
    }

    protected function getRiskAssessment()
    {
        $corpId = (int) $this->corporationId;

        $currentBalanceQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId);
        $currentBalanceQuery = JournalFilters::excludeInternalTransfers($currentBalanceQuery, $corpId);
        $currentBalance = $currentBalanceQuery->sum('amount');

        $avgDailyExpensesQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()])
            ->where('amount', '<', 0);
        $avgDailyExpensesQuery = JournalFilters::excludeInternalTransfers($avgDailyExpensesQuery, $corpId);
        $avgDailyExpenses = $avgDailyExpensesQuery
            ->selectRaw('AVG(ABS(amount)) as avg_expense')
            ->value('avg_expense') ?? 0;

        $daysOfRunway = $avgDailyExpenses > 0 ? $currentBalance / $avgDailyExpenses : 0;

        $volatilityQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()]);
        $volatilityQuery = JournalFilters::excludeInternalTransfers($volatilityQuery, $corpId);
        $volatility = $volatilityQuery
            ->selectRaw('STDDEV(amount) as volatility')
            ->value('volatility') ?? 0;

        return [
            'current_balance' => $currentBalance,
            'days_of_runway' => round($daysOfRunway, 1),
            'volatility' => $volatility,
            'risk_level' => $this->calculateRiskLevel($daysOfRunway, $volatility)
        ];
    }

    protected function calculateRiskLevel($daysOfRunway, $volatility)
    {
        if ($daysOfRunway < 30) return 'HIGH';
        if ($daysOfRunway < 90) return 'MEDIUM';
        if ($daysOfRunway < 180) return 'LOW';
        return 'VERY_LOW';
    }

    // ------------------------------------------------------------------
    // Annual / quarterly retrospective helpers
    // ------------------------------------------------------------------
    //
    // Each method below is defensive: when the data source is missing
    // (no MM, no contribution cache, no alliance tax config, no
    // milestone state table) it returns an empty / null shape and the
    // PDF template renders a muted placeholder. Nothing throws.

    /**
     * Per-month income / expense / net breakdown across the report
     * period. Returns one row per YYYY-MM bucket spanning the period,
     * in chronological order. Zero-fills months with no data so the
     * series is dense for chart-style rendering in the PDF.
     */
    protected function getMonthlyBreakdown(): array
    {
        $corpId = (int) $this->corporationId;

        $query = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $this->corporationId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()]);
        $query = JournalFilters::excludeInternalTransfers($query, $corpId);

        $rows = $query
            ->selectRaw(
                "DATE_FORMAT(date, '%Y-%m') AS period, " .
                'SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) AS income, ' .
                'SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) AS expenses, ' .
                'SUM(amount) AS net, ' .
                'COUNT(*) AS tx_count'
            )
            ->groupByRaw("DATE_FORMAT(date, '%Y-%m')")
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        // Build the dense series — walk from dateFrom to dateTo in
        // 1-month increments so months with no activity still appear.
        $series = [];
        $cursor = $this->dateFrom()->copy()->startOfMonth();
        $end    = $this->dateTo()->copy()->startOfMonth();

        // Safety cap: cover at most 18 months (annual + slack); avoids
        // runaway when callers pass a bogus range.
        $iterations = 0;
        while ($cursor->lte($end) && $iterations < 24) {
            $key = $cursor->format('Y-m');
            $row = $rows->get($key);
            $series[] = [
                'period'   => $key,
                'income'   => (float) ($row->income ?? 0),
                'expenses' => (float) ($row->expenses ?? 0),
                'net'      => (float) ($row->net ?? 0),
                'tx_count' => (int) ($row->tx_count ?? 0),
            ];
            $cursor->addMonth();
            $iterations++;
        }

        return $series;
    }

    /**
     * Top N contributors across the entire report period. Aggregates
     * ContributionService::getTopContributors() output across every
     * monthly period that intersects [dateFrom, dateTo].
     *
     * Returns an empty array when the contribution cache table is
     * missing (older installs) or no rows exist for the period.
     */
    protected function getTopContributorsForPeriod(int $limit = 10): array
    {
        if (! Schema::hasTable('corpwalletmanager_character_contributions')) {
            return [];
        }

        $corpId = (int) $this->corporationId;
        $service = app(ContributionService::class);
        $periods = $this->monthlyPeriods();

        if (empty($periods)) {
            return [];
        }

        // Per-period leaderboards, then re-aggregate at the main level
        // so a contributor active across multiple months collapses into
        // one row. Use the service to keep the alliance-tax + MM
        // resolution path consistent.
        $aggregate = [];
        foreach ($periods as $period) {
            try {
                $result = $service->getTopContributors($corpId, $period, 100);
            } catch (\Throwable $e) {
                Log::warning('[CWM] Retrospective: getTopContributors failed', [
                    'period' => $period,
                    'error'  => $e->getMessage(),
                ]);
                continue;
            }
            foreach ($result['contributors'] ?? [] as $c) {
                $mainId = (int) ($c['main_character_id'] ?? $c['character_id'] ?? 0);
                if ($mainId <= 0) {
                    continue;
                }
                if (! isset($aggregate[$mainId])) {
                    $aggregate[$mainId] = [
                        'character_id'              => $mainId,
                        'character_name'            => $c['character_name'] ?? ('Character ' . $mainId),
                        'ratting_amount'            => 0.0,
                        'mission_amount'            => 0.0,
                        'industry_amount'           => 0.0,
                        'tax_payment_amount'        => 0.0,
                        'donation_voluntary_amount' => 0.0,
                        'total_contribution_amount' => 0.0,
                        'months_active'             => 0,
                    ];
                }
                $aggregate[$mainId]['ratting_amount']            += (float) ($c['ratting_amount'] ?? 0);
                $aggregate[$mainId]['mission_amount']            += (float) ($c['mission_amount'] ?? 0);
                $aggregate[$mainId]['industry_amount']           += (float) ($c['industry_amount'] ?? 0);
                $aggregate[$mainId]['tax_payment_amount']        += (float) ($c['tax_payment_amount'] ?? 0);
                $aggregate[$mainId]['donation_voluntary_amount'] += (float) ($c['donation_voluntary_amount'] ?? 0);
                $aggregate[$mainId]['total_contribution_amount'] += (float) ($c['total_contribution_amount'] ?? 0);
                if ((float) ($c['total_contribution_amount'] ?? 0) > 0) {
                    $aggregate[$mainId]['months_active']++;
                }
            }
        }

        $contributors = array_values($aggregate);
        usort($contributors, fn ($a, $b) => $b['total_contribution_amount'] <=> $a['total_contribution_amount']);
        return array_slice($contributors, 0, max(1, $limit));
    }

    /**
     * Per-activity (bucket) totals + unique-member counts across the
     * report period. Aggregated straight off the contribution cache so
     * this is cheap. Bucket order matches the rest of the suite.
     *
     * Returns an empty array when the cache table is missing.
     */
    protected function getActivityBreakdown(): array
    {
        if (! Schema::hasTable('corpwalletmanager_character_contributions')) {
            return [];
        }

        $corpId = (int) $this->corporationId;
        $periods = $this->monthlyPeriods();
        if (empty($periods)) {
            return [];
        }

        $rows = DB::table('corpwalletmanager_character_contributions')
            ->where('corporation_id', $corpId)
            ->whereIn('period', $periods)
            ->where('character_id', '>=', 90000000)
            ->whereColumn('character_id', '!=', 'corporation_id')
            ->selectRaw(
                'SUM(ratting_amount)            AS ratting_amount, ' .
                'SUM(mission_amount)            AS mission_amount, ' .
                'SUM(industry_amount)           AS industry_amount, ' .
                'SUM(tax_payment_amount)        AS tax_payment_amount, ' .
                'SUM(donation_voluntary_amount) AS donation_voluntary_amount, ' .
                'COUNT(DISTINCT CASE WHEN ratting_amount            > 0 THEN character_id END) AS ratting_members, ' .
                'COUNT(DISTINCT CASE WHEN mission_amount            > 0 THEN character_id END) AS mission_members, ' .
                'COUNT(DISTINCT CASE WHEN industry_amount           > 0 THEN character_id END) AS industry_members, ' .
                'COUNT(DISTINCT CASE WHEN tax_payment_amount        > 0 THEN character_id END) AS tax_payment_members, ' .
                'COUNT(DISTINCT CASE WHEN donation_voluntary_amount > 0 THEN character_id END) AS donation_voluntary_members'
            )
            ->first();

        if ($rows === null) {
            return [];
        }

        $buckets = ['ratting', 'mission', 'industry', 'tax_payment', 'donation_voluntary'];
        $total = 0.0;
        $perBucket = [];
        foreach ($buckets as $b) {
            $amount = (float) ($rows->{$b . '_amount'} ?? 0);
            $perBucket[$b] = [
                'amount'  => $amount,
                'members' => (int) ($rows->{$b . '_members'} ?? 0),
            ];
            $total += $amount;
        }

        // Pct of contribution-side income each bucket represents. Pure
        // ratio of bucket / sum-of-buckets; not a fraction of all corp
        // income (the other ref_types like industry_job_tax, market
        // fees, contract movements are excluded by design).
        foreach ($buckets as $b) {
            $perBucket[$b]['pct'] = $total > 0 ? ($perBucket[$b]['amount'] / $total) * 100.0 : 0.0;
        }

        return [
            'buckets' => $perBucket,
            'total'   => $total,
        ];
    }

    /**
     * Total alliance tax remitted across the report period. Returns
     * an empty shape with has_match_rules = false when no recipient
     * IDs / description keywords are configured; the PDF template uses
     * that to render a "not configured" placeholder.
     */
    protected function getAllianceTaxRemit(): array
    {
        try {
            $byRefType = app(AllianceTaxService::class)
                ->getAllianceTaxByRefType((int) $this->corporationId, $this->dateFrom(), $this->dateTo());
        } catch (\Throwable $e) {
            Log::warning('[CWM] Retrospective: getAllianceTaxByRefType failed', [
                'error' => $e->getMessage(),
            ]);
            return ['has_match_rules' => false, 'total' => 0.0, 'count' => 0, 'by_ref_type' => []];
        }

        if (empty($byRefType)) {
            return ['has_match_rules' => false, 'total' => 0.0, 'count' => 0, 'by_ref_type' => []];
        }

        $total = 0.0;
        $count = 0;
        $rows = [];
        foreach ($byRefType as $refType => $entry) {
            $amount = (float) ($entry['amount'] ?? 0);
            $cnt    = (int) ($entry['count'] ?? 0);
            $total += $amount;
            $count += $cnt;
            $rows[] = [
                'ref_type' => $refType,
                'amount'   => $amount,
                'count'    => $cnt,
            ];
        }

        return [
            'has_match_rules' => true,
            'total'           => $total,
            'count'           => $count,
            'by_ref_type'     => $rows,
        ];
    }

    /**
     * Top N incoming + top N outgoing single transactions in the
     * period. Internal transfers excluded. Party names resolved via
     * EntityNameResolver where possible.
     */
    protected function getNotableTransactions(int $limit = 10): array
    {
        $corpId = (int) $this->corporationId;

        $incomingQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corpId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()])
            ->where('amount', '>', 0);
        $incomingQuery = JournalFilters::excludeInternalTransfers($incomingQuery, $corpId);

        $incoming = $incomingQuery
            ->orderByDesc('amount')
            ->limit($limit)
            ->get(['id', 'date', 'ref_type', 'amount', 'first_party_id', 'second_party_id', 'description'])
            ->all();

        $outgoingQuery = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corpId)
            ->whereBetween('date', [$this->dateFrom(), $this->dateTo()])
            ->where('amount', '<', 0);
        $outgoingQuery = JournalFilters::excludeInternalTransfers($outgoingQuery, $corpId);

        $outgoing = $outgoingQuery
            ->orderBy('amount') // most negative first
            ->limit($limit)
            ->get(['id', 'date', 'ref_type', 'amount', 'first_party_id', 'second_party_id', 'description'])
            ->all();

        // Batch-resolve all party ids in one ESI-free call (the report
        // job runs in queue; we don't want to fan out N ESI requests
        // per retrospective).
        $ids = [];
        foreach (array_merge($incoming, $outgoing) as $row) {
            if (! empty($row->first_party_id)) {
                $ids[] = (int) $row->first_party_id;
            }
            if (! empty($row->second_party_id)) {
                $ids[] = (int) $row->second_party_id;
            }
        }
        $names = [];
        if (! empty($ids)) {
            try {
                $resolved = app(EntityNameResolver::class)->resolve(array_unique($ids), false);
                foreach ($resolved as $id => $info) {
                    if (($info['name'] ?? 'Unknown') !== 'Unknown') {
                        $names[(int) $id] = (string) $info['name'];
                    }
                }
            } catch (\Throwable $e) {
                // Non-fatal — falls back to raw ids in the PDF.
            }
        }

        $shape = function (array $rows) use ($names) {
            $out = [];
            foreach ($rows as $r) {
                $first = $r->first_party_id !== null ? (int) $r->first_party_id : null;
                $second = $r->second_party_id !== null ? (int) $r->second_party_id : null;
                $out[] = [
                    'id'               => (int) $r->id,
                    'date'             => (string) $r->date,
                    'ref_type'         => (string) $r->ref_type,
                    'amount'           => (float) $r->amount,
                    'first_party_id'   => $first,
                    'second_party_id'  => $second,
                    'first_party_name' => $first !== null ? ($names[$first] ?? null) : null,
                    'second_party_name'=> $second !== null ? ($names[$second] ?? null) : null,
                    'description'      => (string) $r->description,
                ];
            }
            return $out;
        };

        return [
            'incoming' => $shape($incoming),
            'outgoing' => $shape($outgoing),
        ];
    }

    /**
     * Member milestones reached during the report period.
     *
     * Pulls from corpwalletmanager_member_milestone_state, which the
     * MemberMilestoneNotifier writes when a member crosses an ISK
     * threshold. Returns an empty array (no error) when the table
     * doesn't exist (older installs) or the period has no crossings.
     *
     * "Reached during the period" = the row's updated_at falls inside
     * the report range. We trust that watermark because the notifier
     * only touches highest_milestone_isk when a new rung is crossed.
     */
    protected function getMemberMilestonesReached(): array
    {
        if (! Schema::hasTable('corpwalletmanager_member_milestone_state')) {
            return [];
        }

        $corpId = (int) $this->corporationId;

        try {
            $rows = DB::table('corpwalletmanager_member_milestone_state')
                ->where('corporation_id', $corpId)
                ->whereBetween('updated_at', [$this->dateFrom(), $this->dateTo()])
                ->where('highest_milestone_isk', '>', 0)
                ->orderByDesc('highest_milestone_isk')
                ->get(['character_id', 'highest_milestone_isk', 'updated_at']);
        } catch (\Throwable $e) {
            Log::warning('[CWM] Retrospective: milestone state lookup failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }

        if ($rows->isEmpty()) {
            return [];
        }

        $names = [];
        try {
            $resolved = app(EntityNameResolver::class)->resolve(
                $rows->pluck('character_id')->map(fn ($v) => (int) $v)->unique()->all(),
                false
            );
            foreach ($resolved as $id => $info) {
                if (($info['name'] ?? 'Unknown') !== 'Unknown') {
                    $names[(int) $id] = (string) $info['name'];
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal.
        }

        $out = [];
        foreach ($rows as $r) {
            $charId = (int) $r->character_id;
            $out[] = [
                'character_id'          => $charId,
                'character_name'        => $names[$charId] ?? ('Character ' . $charId),
                'highest_milestone_isk' => (float) $r->highest_milestone_isk,
                'reached_at'            => (string) $r->updated_at,
            ];
        }

        return $out;
    }

    /**
     * Look up the prior period's stored report for week-over-week,
     * month-over-month, quarter-over-quarter, or year-over-year
     * comparison depending on cadence. Returns null when no prior
     * report exists or the stored data is malformed.
     */
    protected function getPriorPeriodComparison(): ?array
    {
        $corpId = (int) $this->corporationId;
        $priorTo   = $this->dateFrom()->copy()->subDay();
        switch ($this->reportType) {
            case 'quarterly':
                $priorFrom = $priorTo->copy()->subMonthsNoOverflow(3)->addDay();
                break;
            case 'monthly':
                $priorFrom = $priorTo->copy()->subMonthNoOverflow()->addDay();
                break;
            case 'weekly':
                $priorFrom = $priorTo->copy()->subWeek()->addDay();
                break;
            case 'annual':
            default:
                $priorFrom = $priorTo->copy()->subYearNoOverflow()->addDay();
                break;
        }

        try {
            $prior = DB::table('corpwalletmanager_reports')
                ->where('corporation_id', $corpId)
                ->where('report_type', $this->reportType)
                ->whereDate('date_from', $priorFrom->toDateString())
                ->whereDate('date_to', $priorTo->toDateString())
                ->orderByDesc('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        if ($prior === null) {
            return null;
        }

        $priorData = is_string($prior->data) ? json_decode($prior->data, true) : (array) $prior->data;
        if (! is_array($priorData)) {
            return null;
        }

        $priorIncome  = (float) ($priorData['income_analysis']['total'] ?? 0);
        $priorExpense = (float) ($priorData['expense_analysis']['total'] ?? 0);
        $priorNet     = (float) ($priorData['balance_history']['change'] ?? 0);
        $priorEnd     = (float) ($priorData['balance_history']['end_balance'] ?? 0);

        return [
            'kind'         => $this->reportType,
            'period_from'  => $priorFrom->toDateString(),
            'period_to'    => $priorTo->toDateString(),
            'report_id'    => (int) $prior->id,
            'income'       => $priorIncome,
            'expense'      => $priorExpense,
            'net_change'   => $priorNet,
            'end_balance'  => $priorEnd,
        ];
    }

    /**
     * Expense attribution by category over the report range. Reuses the
     * 9-bucket taxonomy from ExpenseAttributionService so the director
     * tab and the report can never disagree on what counts as Alliance
     * Tax vs Corp Withdrawal vs Industry Costs etc.
     *
     * Returns null on failure rather than throwing so the rest of the
     * report still generates.
     */
    protected function getExpenseAttributionForRange(): ?array
    {
        try {
            return app(ExpenseAttributionService::class)
                ->getForRange((int) $this->corporationId, $this->dateFrom(), $this->dateTo());
        } catch (\Throwable $e) {
            Log::warning('[CWM] Retrospective: expense attribution failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Per-bucket × rate alliance tax expected over the report range
     * (the math the director-view Alliance Tax tab does for each month,
     * collapsed to a single range total). Paired in the template with
     * the existing alliance_tax_remit (actual) so operators can see the
     * expected vs actual gap at a glance.
     *
     * Returns null when no rates are configured — the template renders
     * a muted placeholder.
     */
    protected function getAllianceTaxExpectedForRange(): ?array
    {
        try {
            $result = app(AllianceTaxService::class)
                ->getExpectedAllianceTaxForRange((int) $this->corporationId, $this->dateFrom(), $this->dateTo());
        } catch (\Throwable $e) {
            Log::warning('[CWM] Retrospective: alliance tax expected failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (! is_array($result)) {
            return null;
        }

        // Skip emit when all rates are zero — nothing meaningful to render.
        $hasAnyRate = false;
        foreach ($result['rates'] ?? [] as $rate) {
            if ((float) $rate > 0) {
                $hasAnyRate = true;
                break;
            }
        }
        if (! $hasAnyRate) {
            return null;
        }

        return $result;
    }

    /**
     * Aggregated corp-wide Mining Manager tax compliance for the report
     * range. Returns null when MM is absent or the mining_taxes table
     * is missing so the template skips the section entirely.
     */
    protected function getMmComplianceForRange(): ?array
    {
        try {
            return app(ContributionService::class)
                ->getMmCorpComplianceForRange((int) $this->corporationId, $this->dateFrom(), $this->dateTo());
        } catch (\Throwable $e) {
            Log::warning('[CWM] Retrospective: MM compliance failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Anomaly summary over the report range. Mix of historical and
     * present-state data depending on which detector left a timestamp:
     *   - contribution_drop: queried from the latch table by
     *     contribution_drop_notified_at, so this is "drops fired during
     *     the period".
     *   - unusual_recipient: re-walks the journal applying the same
     *     first-time-recipient heuristic the detector uses, so this is
     *     "rows that would have alerted within the period". Pragmatic
     *     reconstruction because the live detector keeps only a
     *     watermark, not a per-row log.
     *
     * The 'note' key carries the operator-facing caveat.
     */
    protected function getAnomalySummaryForRange(): ?array
    {
        try {
            return app(AnomalyReportService::class)
                ->getAnomalySummaryForRange((int) $this->corporationId, $this->dateFrom(), $this->dateTo());
        } catch (\Throwable $e) {
            Log::warning('[CWM] Retrospective: anomaly summary failed', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * List of YYYY-MM periods that intersect the report date range.
     * Used by the contribution-cache-backed sections.
     *
     * @return string[]
     */
    protected function monthlyPeriods(): array
    {
        $periods = [];
        $cursor = $this->dateFrom()->copy()->startOfMonth();
        $end    = $this->dateTo()->copy()->startOfMonth();
        $iterations = 0;
        while ($cursor->lte($end) && $iterations < 24) {
            $periods[] = $cursor->format('Y-m');
            $cursor->addMonth();
            $iterations++;
        }
        return $periods;
    }

    /**
     * Deliver the generated report to every Discord webhook subscribed to
     * this corporation + report category. The HTTP work, retry logic and
     * per-webhook health bookkeeping all live in WebhookService.
     */
    protected function deliverToWebhooks($corpName, $reportData)
    {
        $result = app(WebhookService::class)->dispatchReport(
            $this->corporationId ? (int) $this->corporationId : null,
            (string) $this->reportType,
            $this->createDiscordEmbed($corpName, $reportData)
        );

        Log::info('GenerateReport webhook delivery', array_merge($result, [
            'corporation_id' => $this->corporationId,
            'report_type'    => $this->reportType,
        ]));
    }

    protected function createDiscordEmbed($corpName, $reportData)
    {
        $color = $this->getEmbedColor($reportData);

        $embed = [
            'title' => "📊 {$this->getReportTitle()}",
            'description' => "Financial report for **{$corpName}**",
            'color' => $color,
            'timestamp' => now()->toIso8601String(),
            'fields' => []
        ];

        // Period — every report type carries this.
        $embed['fields'][] = [
            'name' => '📅 Period',
            'value' => "{$reportData['period']['from']} to {$reportData['period']['to']} ({$reportData['period']['days']} days)",
            'inline' => false
        ];

        // Per-type field composition. Each branch picks the embed
        // shape that matches the PDF: terse pulse-check for daily,
        // KPI snapshot for executive, deep ref-type detail for
        // financial, per-division for division, retro highlights for
        // weekly+ cadences. Discord caps embeds at 25 fields and
        // 6000 chars total, so each branch is bounded.
        switch ($this->reportType) {
            case 'executive':
                $this->appendExecutiveEmbedFields($embed, $reportData);
                break;
            case 'financial':
                $this->appendFinancialEmbedFields($embed, $reportData);
                break;
            case 'division':
                $this->appendDivisionEmbedFields($embed, $reportData);
                break;
            case 'daily':
                $this->appendDailyEmbedFields($embed, $reportData);
                break;
            case 'weekly':
            case 'monthly':
            case 'quarterly':
            case 'annual':
                // Retro shape: balance + income + expense + risk +
                // the retro highlights block (top contributors,
                // activity, alliance tax expected vs actual,
                // anomalies). Preserves the v3.0 cadence-report look.
                $this->appendBalanceField($embed, $reportData);
                $this->appendIncomeExpenseFields($embed, $reportData);
                $this->appendRiskField($embed, $reportData);
                $this->appendRetroEmbedFields($embed, $reportData);
                break;
            case 'custom':
            default:
                // Custom + fallback: keep the legacy compact shape so
                // existing custom reports render the same way.
                $this->appendBalanceField($embed, $reportData);
                $this->appendIncomeExpenseFields($embed, $reportData);
                $this->appendRiskField($embed, $reportData);
                break;
        }

        // Hard cap on field count so we never exceed Discord's 25.
        if (count($embed['fields']) > 25) {
            $embed['fields'] = array_slice($embed['fields'], 0, 25);
        }

        $embed['footer'] = [
            'text' => 'Corp Wallet Manager [CWM]'
        ];

        return $embed;
    }

    /**
     * Executive Discord embed: balance + income total + expense total
     * + risk + headline + top 3 contributors as one field. Designed
     * for an at-a-glance read in #leadership.
     */
    protected function appendExecutiveEmbedFields(array &$embed, array $reportData): void
    {
        $this->appendBalanceField($embed, $reportData);
        $this->appendIncomeExpenseFields($embed, $reportData);
        $this->appendRiskField($embed, $reportData);

        // Headline sentence — the executive shape always populates this.
        if (! empty($reportData['one_line_headline'])) {
            $embed['fields'][] = [
                'name'   => '🎯 Headline',
                'value'  => (string) $reportData['one_line_headline'],
                'inline' => false,
            ];
        }

        // Top 3 contributors collapsed into a single field.
        if (! empty($reportData['top_contributors'])) {
            $lines = [];
            foreach (array_slice($reportData['top_contributors'], 0, 3) as $i => $c) {
                $c = (array) $c;
                $name = $c['character_name'] ?? ('Character ' . ($c['character_id'] ?? '?'));
                $amount = (float) ($c['total_contribution_amount'] ?? 0);
                $lines[] = sprintf('%d. %s: %s ISK', $i + 1, $name, number_format($amount, 0));
            }
            if (! empty($lines)) {
                $embed['fields'][] = [
                    'name'   => '🏆 Top Contributors',
                    'value'  => implode("\n", $lines),
                    'inline' => false,
                ];
            }
        }
    }

    /**
     * Financial Discord embed: balance + income (top 3 ref_types) +
     * expense (top 3 ref_types) + activity breakdown (top 3 buckets)
     * + risk. The deep-dive content keeps the embed within the 25
     * field / 6000 char caps by limiting each list to top-3.
     */
    protected function appendFinancialEmbedFields(array &$embed, array $reportData): void
    {
        $this->appendBalanceField($embed, $reportData);

        // Top 3 income ref_types.
        if (! empty($reportData['income_breakdown'])) {
            $lines = [];
            $breakdown = $reportData['income_breakdown'];
            // Collection or array — normalize to plain array of items.
            if ($breakdown instanceof \Illuminate\Support\Collection) {
                $breakdown = $breakdown->all();
            }
            foreach (array_slice((array) $breakdown, 0, 3) as $row) {
                $row = (array) $row;
                $lines[] = sprintf(
                    '%s: %s ISK',
                    str_replace('_', ' ', (string) ($row['ref_type'] ?? '')),
                    number_format((float) ($row['total_amount'] ?? 0), 0)
                );
            }
            if (! empty($lines)) {
                $embed['fields'][] = [
                    'name'   => '💰 Top Income Sources',
                    'value'  => implode("\n", $lines),
                    'inline' => true,
                ];
            }
        }

        // Top 3 expense ref_types.
        if (! empty($reportData['expense_breakdown'])) {
            $lines = [];
            $breakdown = $reportData['expense_breakdown'];
            if ($breakdown instanceof \Illuminate\Support\Collection) {
                $breakdown = $breakdown->all();
            }
            foreach (array_slice((array) $breakdown, 0, 3) as $row) {
                $row = (array) $row;
                $lines[] = sprintf(
                    '%s: %s ISK',
                    str_replace('_', ' ', (string) ($row['ref_type'] ?? '')),
                    number_format((float) ($row['total_amount'] ?? 0), 0)
                );
            }
            if (! empty($lines)) {
                $embed['fields'][] = [
                    'name'   => '💸 Top Expense Categories',
                    'value'  => implode("\n", $lines),
                    'inline' => true,
                ];
            }
        }

        // Top 3 activity buckets.
        if (! empty($reportData['activity_breakdown']['buckets'])) {
            $buckets = $reportData['activity_breakdown']['buckets'];
            // Sort by amount desc, take 3.
            $sorted = $buckets;
            uasort($sorted, fn ($a, $b) => ((float) ($b['amount'] ?? 0)) <=> ((float) ($a['amount'] ?? 0)));
            $lines = [];
            $count = 0;
            foreach ($sorted as $key => $entry) {
                if ((float) ($entry['amount'] ?? 0) <= 0) continue;
                $lines[] = sprintf(
                    '%s: %.1f%% (%s ISK)',
                    ucwords(str_replace('_', ' ', (string) $key)),
                    (float) ($entry['pct'] ?? 0),
                    number_format((float) ($entry['amount'] ?? 0), 0)
                );
                if (++$count >= 3) break;
            }
            if (! empty($lines)) {
                $embed['fields'][] = [
                    'name'   => '📊 Activity Mix',
                    'value'  => implode("\n", $lines),
                    'inline' => false,
                ];
            }
        }

        $this->appendRiskField($embed, $reportData);
    }

    /**
     * Division Discord embed: balance + per-division change (top 5
     * by absolute change) + risk. Each division renders as one line
     * inside a single field so we stay inside the 25-field cap.
     */
    protected function appendDivisionEmbedFields(array &$embed, array $reportData): void
    {
        $this->appendBalanceField($embed, $reportData);

        if (! empty($reportData['division_summary'])) {
            $divisions = $reportData['division_summary'];
            // Sort by absolute net change desc, take 5.
            usort($divisions, function ($a, $b) {
                $a = (array) $a;
                $b = (array) $b;
                return abs((float) ($b['net_change'] ?? 0)) <=> abs((float) ($a['net_change'] ?? 0));
            });
            $lines = [];
            foreach (array_slice($divisions, 0, 5) as $div) {
                $d = (array) $div;
                $net = (float) ($d['net_change'] ?? 0);
                $arrow = $net >= 0 ? '+' : '';
                $lines[] = sprintf(
                    'Division %s: %s%s ISK (%s tx)',
                    $d['division'] ?? '?',
                    $arrow,
                    number_format($net, 0),
                    number_format((int) ($d['transactions'] ?? 0))
                );
            }
            if (! empty($lines)) {
                $embed['fields'][] = [
                    'name'   => '🏦 Per-Division Change',
                    'value'  => implode("\n", $lines),
                    'inline' => false,
                ];
            }
        }

        // Internal transfers headline.
        if (! empty($reportData['internal_transfers_summary'])) {
            $its = $reportData['internal_transfers_summary'];
            $count = (int) ($its['total_count'] ?? 0);
            if ($count > 0) {
                $embed['fields'][] = [
                    'name'   => '🔁 Internal Transfers',
                    'value'  => sprintf(
                        "%s transfers totalling %s ISK",
                        number_format($count),
                        number_format((float) ($its['total_amount'] ?? 0), 0)
                    ),
                    'inline' => true,
                ];
            }
        }

        $this->appendRiskField($embed, $reportData);
    }

    /**
     * Daily Discord embed: balance + today's flow + risk. The
     * absolute minimum a director needs to glance at first thing.
     */
    protected function appendDailyEmbedFields(array &$embed, array $reportData): void
    {
        $this->appendBalanceField($embed, $reportData);
        $this->appendIncomeExpenseFields($embed, $reportData);
        $this->appendRiskField($embed, $reportData);
    }

    /**
     * Shared balance change field, used by every report type.
     */
    protected function appendBalanceField(array &$embed, array $reportData): void
    {
        if (! isset($reportData['balance_history'])) {
            return;
        }
        $bh = $reportData['balance_history'];
        $changeIcon = ((float) ($bh['change'] ?? 0)) >= 0 ? '📈' : '📉';
        $embed['fields'][] = [
            'name'   => "{$changeIcon} Balance Change",
            'value'  => sprintf(
                "Start: %s ISK\nEnd: %s ISK\nChange: %s ISK (%+.2f%%)",
                number_format((float) ($bh['start_balance'] ?? 0), 0),
                number_format((float) ($bh['end_balance'] ?? 0), 0),
                number_format((float) ($bh['change'] ?? 0), 0),
                (float) ($bh['change_percent'] ?? 0)
            ),
            'inline' => false,
        ];
    }

    /**
     * Shared income + expense totals (no breakdown), used by daily,
     * executive, retro embeds.
     */
    protected function appendIncomeExpenseFields(array &$embed, array $reportData): void
    {
        if (! isset($reportData['income_analysis']) || ! isset($reportData['expense_analysis'])) {
            return;
        }
        $income = $reportData['income_analysis'];
        $expenses = $reportData['expense_analysis'];

        $embed['fields'][] = [
            'name'   => '💰 Income',
            'value'  => sprintf(
                "Total: %s ISK\nTransactions: %s",
                number_format((float) ($income['total'] ?? 0), 0),
                number_format((int) ($income['transactions'] ?? 0))
            ),
            'inline' => true,
        ];

        $embed['fields'][] = [
            'name'   => '💸 Expenses',
            'value'  => sprintf(
                "Total: %s ISK\nTransactions: %s",
                number_format((float) ($expenses['total'] ?? 0), 0),
                number_format((int) ($expenses['transactions'] ?? 0))
            ),
            'inline' => true,
        ];
    }

    /**
     * Shared risk-assessment field, used by every report type.
     */
    protected function appendRiskField(array &$embed, array $reportData): void
    {
        if (! isset($reportData['risk_assessment'])) {
            return;
        }
        $risk = $reportData['risk_assessment'];
        $riskIcons = [
            'HIGH'     => '🔴',
            'MEDIUM'   => '🟡',
            'LOW'      => '🟢',
            'VERY_LOW' => '🔵',
        ];
        $riskIcon = $riskIcons[$risk['risk_level'] ?? ''] ?? '⚪';

        $embed['fields'][] = [
            'name'   => "{$riskIcon} Risk Assessment",
            'value'  => sprintf(
                "Risk Level: **%s**\nDays of Runway: **%.1f days**\nCurrent Balance: %s ISK",
                $risk['risk_level'] ?? 'UNKNOWN',
                (float) ($risk['days_of_runway'] ?? 0),
                number_format((float) ($risk['current_balance'] ?? 0), 0)
            ),
            'inline' => false,
        ];
    }

    /**
     * Append a compact "Retro Highlights" block to the Discord embed
     * when retro data keys are present. Stays within Discord's caps by
     * limiting each field's value text and capping how many list items
     * we render (top 3 contributors, top 1 activity).
     *
     * Mutates $embed in place to keep the call-site readable.
     */
    protected function appendRetroEmbedFields(array &$embed, array $reportData): void
    {
        $topContributors = $reportData['top_contributors']    ?? null;
        $activity        = $reportData['activity_breakdown']  ?? null;
        $taxRemit        = $reportData['alliance_tax_remit']  ?? null;
        $taxExpected     = $reportData['alliance_tax_expected'] ?? null;
        $anomalies       = $reportData['anomaly_summary']     ?? null;
        $expenses        = $reportData['expense_attribution'] ?? null;

        $hasAny = ! empty($topContributors)
            || ! empty($activity['buckets'])
            || ! empty($taxRemit['has_match_rules'])
            || ! empty($taxExpected)
            || ! empty($anomalies)
            || ! empty($expenses['by_category']);

        if (! $hasAny) {
            return;
        }

        // Top 3 contributors as `Name (pct%)` — the pct here is each
        // contributor's share of the top-contributors-total (not the
        // corp's whole income) which matches how the leaderboard reads.
        if (! empty($topContributors)) {
            $topTotal = 0.0;
            foreach ($topContributors as $c) {
                $topTotal += (float) ($c['total_contribution_amount'] ?? 0);
            }
            $lines = [];
            foreach (array_slice($topContributors, 0, 3) as $c) {
                $amount = (float) ($c['total_contribution_amount'] ?? 0);
                $pct    = $topTotal > 0 ? ($amount / $topTotal) * 100.0 : 0.0;
                $name   = $c['character_name'] ?? ('Character ' . ($c['character_id'] ?? '?'));
                $lines[] = sprintf('%s (%.1f%%)', $name, $pct);
            }
            if (! empty($lines)) {
                $embed['fields'][] = [
                    'name'   => '🏆 Top Contributors',
                    'value'  => implode("\n", $lines),
                    'inline' => true,
                ];
            }
        }

        // Top 1 activity bucket label + share.
        if (! empty($activity['buckets'])) {
            $buckets = $activity['buckets'];
            $best    = null;
            $bestPct = 0.0;
            $bestKey = null;
            foreach ($buckets as $key => $entry) {
                $amount = (float) ($entry['amount'] ?? 0);
                $pct    = (float) ($entry['pct'] ?? 0);
                if ($amount > 0 && ($best === null || $amount > (float) ($best['amount'] ?? 0))) {
                    $best    = $entry;
                    $bestPct = $pct;
                    $bestKey = $key;
                }
            }
            if ($best !== null && $bestKey !== null) {
                $embed['fields'][] = [
                    'name'   => '📊 Top Activity',
                    'value'  => sprintf(
                        "%s\n%.1f%% of contributions",
                        ucwords(str_replace('_', ' ', (string) $bestKey)),
                        $bestPct
                    ),
                    'inline' => true,
                ];
            }
        }

        // Alliance tax expected vs actual one-liner. Only render when we
        // have a number for at least one side (avoids "0 vs 0" noise).
        $expectedTotal = isset($taxExpected['total_expected']) ? (float) $taxExpected['total_expected'] : null;
        $actualTotal   = (! empty($taxRemit['has_match_rules']) && isset($taxRemit['total']))
            ? (float) $taxRemit['total']
            : null;
        if ($expectedTotal !== null || $actualTotal !== null) {
            $expectedStr = $expectedTotal !== null ? number_format($expectedTotal, 0) . ' ISK' : 'not configured';
            $actualStr   = $actualTotal !== null   ? number_format($actualTotal, 0)   . ' ISK' : 'not matched';
            $embed['fields'][] = [
                'name'   => '🏛 Alliance Tax',
                'value'  => sprintf("Expected: %s\nActual: %s", $expectedStr, $actualStr),
                'inline' => true,
            ];
        }

        // Anomaly count summary.
        if (! empty($anomalies)) {
            $count = (int) ($anomalies['total_anomalies'] ?? 0);
            $drops = isset($anomalies['contribution_drops']) ? count($anomalies['contribution_drops']) : 0;
            $recipients = isset($anomalies['unusual_recipients']) ? count($anomalies['unusual_recipients']) : 0;
            $value = $count === 0
                ? 'None detected'
                : sprintf("%d total\nDrops: %d, Recipients: %d", $count, $drops, $recipients);
            $embed['fields'][] = [
                'name'   => '🚨 Anomalies',
                'value'  => $value,
                'inline' => true,
            ];
        }
    }

    protected function getReportTitle()
    {
        $titles = [
            'executive' => 'Executive Summary Report',
            'financial' => 'Financial Analysis Report',
            'division'  => 'Division Performance Report',
            'custom'    => 'Custom Report',
            'daily'     => 'Daily Wallet Report',
            'weekly'    => 'Weekly Wallet Report',
            'monthly'   => 'Monthly Wallet Report',
            'annual'    => 'Annual Summary Report',
            'quarterly' => 'Quarterly Summary Report',
        ];

        return $titles[$this->reportType] ?? 'Financial Report';
    }

    protected function getEmbedColor($reportData)
    {
        // Determine color based on financial health
        if (isset($reportData['risk_assessment'])) {
            $risk = $reportData['risk_assessment']['risk_level'];
            $colors = [
                'HIGH' => 15158332, // Red
                'MEDIUM' => 16776960, // Yellow
                'LOW' => 3066993, // Green
                'VERY_LOW' => 3447003 // Blue
            ];
            return $colors[$risk] ?? 3447003;
        }

        // Default to blue
        return 3447003;
    }
}
