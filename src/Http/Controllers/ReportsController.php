<?php
namespace CorpWalletManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use CorpWalletManager\Http\Controllers\Concerns\AuthorizesCorporationAccess;
use CorpWalletManager\Jobs\GenerateReport;

class ReportsController extends Controller
{
    use AuthorizesCorporationAccess;

    /**
     * Display the reports view
     */
    public function index()
    {
        // Reports feature is now integrated in Director view
        return redirect()
            ->route('corpwalletmanager.director')
            ->with('info', 'Reports are now in the Director view. Click the Reports tab to access all reporting features.');
    }

    /**
     * Generate a report
     */
    public function generate(Request $request)
    {
        try {
            $validated = $request->validate([
                'report_type' => 'required|in:executive,financial,division,custom,annual,quarterly,daily,weekly,monthly',
                'date_from' => 'required|date',
                'date_to' => 'required|date|after_or_equal:date_from',
                'sections' => 'array',
                'send_to_discord' => 'boolean'
            ]);

            $corporationId = $this->getCorporationId($request);

            if (!$corporationId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select a corporation in settings first.'
                ], 400);
            }

            // Dispatch the job
            GenerateReport::dispatch(
                $corporationId,
                $validated['report_type'],
                Carbon::parse($validated['date_from']),
                Carbon::parse($validated['date_to']),
                $validated['sections'] ?? [],
                $validated['send_to_discord'] ?? false
            );

            return response()->json([
                'success' => true,
                'message' => 'Report generation started. You will receive a notification when complete.'
            ]);

        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get report history
     */
    public function history()
    {
        try {
            $query = DB::table('corpwalletmanager_reports')
                ->orderBy('created_at', 'desc')
                ->limit(50);

            if (!$this->userIsAdmin()) {
                $corps = $this->userCorporationIds();
                if (empty($corps)) {
                    return response()->json(['success' => true, 'reports' => []]);
                }
                $query->whereIn('corporation_id', $corps);
            }

            return response()->json([
                'success' => true,
                'reports' => $query->get(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch report history', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch report history'
            ], 500);
        }
    }

    /**
     * Get available report templates
     */
    public function templates()
    {
        return response()->json([
            'templates' => [
                [
                    'id' => 'executive',
                    'name' => 'Executive Summary',
                    'description' => 'High-level overview of corporation financial health',
                    'sections' => ['balance_history', 'income_analysis', 'expense_analysis', 'risk_assessment']
                ],
                [
                    'id' => 'financial',
                    'name' => 'Financial Report',
                    'description' => 'Detailed financial analysis with trends',
                    'sections' => ['balance_history', 'income_expense', 'transaction_breakdown', 'predictions']
                ],
                [
                    'id' => 'division',
                    'name' => 'Division Performance',
                    'description' => 'Performance breakdown by division',
                    'sections' => ['division_summary', 'division_comparison', 'division_trends']
                ],
                [
                    'id' => 'custom',
                    'name' => 'Custom Report',
                    'description' => 'Build your own report with selected sections',
                    'sections' => []
                ],
                [
                    'id' => 'annual',
                    'name' => 'Annual Summary',
                    'description' => 'Full-year retrospective covering monthly breakdown, top contributors, activity mix, alliance tax remit, notable transactions, milestones, and year-over-year comparison',
                    'sections' => []
                ],
                [
                    'id' => 'quarterly',
                    'name' => 'Quarterly Summary',
                    'description' => 'Three-month retrospective with the same sections as Annual, scoped to one fiscal quarter',
                    'sections' => []
                ]
            ]
        ]);
    }

    /**
     * Export a stored report as a downloadable PDF.
     *
     * Renders corpwalletmanager::reports.pdf.report through dompdf with the
     * report's stored data array. Corp-scoped: non-admins can only export
     * reports for corporations they have a character in.
     */
    public function exportPdf(Request $request, int $report)
    {
        try {
            [$row, $data] = $this->loadReportForExport($report);

            // annual / quarterly retrospectives have their own multi-
            // section template. Everything else uses the original report
            // template, which renders any combination of the legacy
            // sections defensively.
            $template = match ($row->report_type ?? '') {
                'annual'    => 'corpwalletmanager::reports.pdf.annual',
                'quarterly' => 'corpwalletmanager::reports.pdf.quarterly',
                default     => 'corpwalletmanager::reports.pdf.report',
            };

            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($template, [
                'report'   => $row,
                'data'     => $data,
                'corpName' => $this->resolveCorpName((int) $row->corporation_id),
            ]);

            return $pdf->download($this->reportFileName($row, 'pdf'));
        } catch (\Throwable $e) {
            Log::error('Report PDF export failed', [
                'report_id' => $report,
                'error'     => $e->getMessage(),
            ]);

            return redirect()
                ->route('corpwalletmanager.director')
                ->with('error', 'PDF export failed: ' . $e->getMessage());
        }
    }

    /**
     * Export a stored report as a multi-section CSV.
     *
     * Single file with section headers separated by blank rows so Excel can
     * open it directly and the operator can copy specific tables out.
     */
    public function exportCsv(Request $request, int $report)
    {
        try {
            [$row, $data] = $this->loadReportForExport($report);
            $filename = $this->reportFileName($row, 'csv');
            $corpName = $this->resolveCorpName((int) $row->corporation_id);

            return response()->streamDownload(function () use ($row, $data, $corpName) {
                $out = fopen('php://output', 'w');

                // Header block
                fputcsv($out, ['Corp Wallet Manager Report']);
                fputcsv($out, ['Corporation', $corpName . ' (' . $row->corporation_id . ')']);
                fputcsv($out, ['Report Type', $row->report_type]);
                fputcsv($out, ['Period', $row->date_from . ' to ' . $row->date_to]);
                fputcsv($out, ['Generated', (string) $row->created_at]);
                fputcsv($out, []);

                if (! empty($data['balance_history'])) {
                    $bh = $data['balance_history'];
                    fputcsv($out, ['BALANCE SUMMARY']);
                    fputcsv($out, ['Start Balance (ISK)', number_format((float) ($bh['start_balance'] ?? 0), 2, '.', '')]);
                    fputcsv($out, ['End Balance (ISK)', number_format((float) ($bh['end_balance'] ?? 0), 2, '.', '')]);
                    fputcsv($out, ['Change (ISK)', number_format((float) ($bh['change'] ?? 0), 2, '.', '')]);
                    fputcsv($out, ['Change (%)', number_format((float) ($bh['change_percent'] ?? 0), 2)]);
                    fputcsv($out, []);

                    if (! empty($bh['daily_data'])) {
                        fputcsv($out, ['DAILY BALANCE CHANGES']);
                        fputcsv($out, ['Date', 'Daily Change (ISK)']);
                        foreach ($bh['daily_data'] as $entry) {
                            $e = (array) $entry;
                            fputcsv($out, [
                                $e['date'] ?? '',
                                number_format((float) ($e['daily_change'] ?? 0), 2, '.', ''),
                            ]);
                        }
                        fputcsv($out, []);
                    }
                }

                if (! empty($data['income_analysis']) || ! empty($data['expense_analysis'])) {
                    fputcsv($out, ['INCOME / EXPENSE SUMMARY']);
                    fputcsv($out, ['Category', 'Total (ISK)', 'Transactions', 'Average', 'Highest', 'Lowest']);
                    foreach (['income' => 'Income', 'expense' => 'Expense'] as $key => $label) {
                        $section = $data[$key . '_analysis'] ?? null;
                        if ($section) {
                            fputcsv($out, [
                                $label,
                                number_format((float) ($section['total'] ?? 0), 2, '.', ''),
                                (int) ($section['transactions'] ?? 0),
                                number_format((float) ($section['average'] ?? 0), 2, '.', ''),
                                number_format((float) ($section['highest'] ?? 0), 2, '.', ''),
                                number_format((float) ($section['lowest'] ?? 0), 2, '.', ''),
                            ]);
                        }
                    }
                    fputcsv($out, []);
                }

                if (! empty($data['transaction_breakdown'])) {
                    fputcsv($out, ['TRANSACTION BREAKDOWN BY REF TYPE']);
                    fputcsv($out, ['Ref Type', 'Count', 'Total Amount (ISK)', 'Average Amount (ISK)']);
                    foreach ($data['transaction_breakdown'] as $tx) {
                        $t = (array) $tx;
                        fputcsv($out, [
                            $t['ref_type'] ?? '',
                            (int) ($t['count'] ?? 0),
                            number_format((float) ($t['total_amount'] ?? 0), 2, '.', ''),
                            number_format((float) ($t['avg_amount'] ?? 0), 2, '.', ''),
                        ]);
                    }
                    fputcsv($out, []);
                }

                if (! empty($data['division_summary'])) {
                    fputcsv($out, ['DIVISION SUMMARY']);
                    fputcsv($out, ['Division', 'Transactions', 'Income (ISK)', 'Expenses (ISK)', 'Net Change (ISK)']);
                    foreach ($data['division_summary'] as $div) {
                        $d = (array) $div;
                        fputcsv($out, [
                            $d['division'] ?? '',
                            (int) ($d['transactions'] ?? 0),
                            number_format((float) ($d['income'] ?? 0), 2, '.', ''),
                            number_format((float) ($d['expenses'] ?? 0), 2, '.', ''),
                            number_format((float) ($d['net_change'] ?? 0), 2, '.', ''),
                        ]);
                    }
                    fputcsv($out, []);
                }

                if (! empty($data['risk_assessment'])) {
                    $ra = $data['risk_assessment'];
                    fputcsv($out, ['RISK ASSESSMENT']);
                    fputcsv($out, ['Current Balance (ISK)', number_format((float) ($ra['current_balance'] ?? 0), 2, '.', '')]);
                    fputcsv($out, ['Days of Runway', number_format((float) ($ra['days_of_runway'] ?? 0), 1)]);
                    fputcsv($out, ['Volatility', number_format((float) ($ra['volatility'] ?? 0), 2, '.', '')]);
                    fputcsv($out, ['Risk Level', $ra['risk_level'] ?? 'UNKNOWN']);
                    fputcsv($out, []);
                }

                // Retrospective (annual / quarterly) extra sections. Each
                // block is gated on the data being present so legacy
                // reports still produce identical CSVs.

                if (! empty($data['monthly_breakdown'])) {
                    fputcsv($out, ['MONTHLY BREAKDOWN']);
                    fputcsv($out, ['Month', 'Income (ISK)', 'Expenses (ISK)', 'Net (ISK)', 'Transactions']);
                    foreach ($data['monthly_breakdown'] as $m) {
                        $m = (array) $m;
                        fputcsv($out, [
                            $m['period'] ?? '',
                            number_format((float) ($m['income'] ?? 0), 2, '.', ''),
                            number_format((float) ($m['expenses'] ?? 0), 2, '.', ''),
                            number_format((float) ($m['net'] ?? 0), 2, '.', ''),
                            (int) ($m['tx_count'] ?? 0),
                        ]);
                    }
                    fputcsv($out, []);
                }

                if (! empty($data['top_contributors'])) {
                    fputcsv($out, ['TOP CONTRIBUTORS (PERIOD)']);
                    fputcsv($out, [
                        'Rank', 'Character', 'Ratting', 'Mission', 'Industry',
                        'Tax Payment', 'Voluntary Donation', 'Total Contribution', 'Months Active',
                    ]);
                    foreach ($data['top_contributors'] as $i => $c) {
                        $c = (array) $c;
                        fputcsv($out, [
                            $i + 1,
                            $c['character_name'] ?? ('Character ' . ($c['character_id'] ?? '')),
                            number_format((float) ($c['ratting_amount'] ?? 0), 2, '.', ''),
                            number_format((float) ($c['mission_amount'] ?? 0), 2, '.', ''),
                            number_format((float) ($c['industry_amount'] ?? 0), 2, '.', ''),
                            number_format((float) ($c['tax_payment_amount'] ?? 0), 2, '.', ''),
                            number_format((float) ($c['donation_voluntary_amount'] ?? 0), 2, '.', ''),
                            number_format((float) ($c['total_contribution_amount'] ?? 0), 2, '.', ''),
                            (int) ($c['months_active'] ?? 0),
                        ]);
                    }
                    fputcsv($out, []);
                }

                if (! empty($data['activity_breakdown']['buckets'])) {
                    fputcsv($out, ['ACTIVITY MIX']);
                    fputcsv($out, ['Activity', 'Total (ISK)', 'Pct of Contributions', 'Members']);
                    foreach ($data['activity_breakdown']['buckets'] as $bucket => $entry) {
                        $entry = (array) $entry;
                        fputcsv($out, [
                            str_replace('_', ' ', $bucket),
                            number_format((float) ($entry['amount'] ?? 0), 2, '.', ''),
                            number_format((float) ($entry['pct'] ?? 0), 2),
                            (int) ($entry['members'] ?? 0),
                        ]);
                    }
                    fputcsv($out, []);
                }

                if (! empty($data['alliance_tax_remit']['has_match_rules'])) {
                    $atr = $data['alliance_tax_remit'];
                    fputcsv($out, ['ALLIANCE TAX REMIT']);
                    fputcsv($out, ['Total (ISK)', number_format((float) ($atr['total'] ?? 0), 2, '.', '')]);
                    fputcsv($out, ['Matched Payments', (int) ($atr['count'] ?? 0)]);
                    if (! empty($atr['by_ref_type'])) {
                        fputcsv($out, []);
                        fputcsv($out, ['By Ref Type', 'Amount (ISK)', 'Count']);
                        foreach ($atr['by_ref_type'] as $rt) {
                            $rt = (array) $rt;
                            fputcsv($out, [
                                $rt['ref_type'] ?? '',
                                number_format((float) ($rt['amount'] ?? 0), 2, '.', ''),
                                (int) ($rt['count'] ?? 0),
                            ]);
                        }
                    }
                    fputcsv($out, []);
                }

                if (! empty($data['notable_transactions']['incoming']) || ! empty($data['notable_transactions']['outgoing'])) {
                    fputcsv($out, ['NOTABLE TRANSACTIONS']);
                    foreach (['incoming' => 'Top Incoming', 'outgoing' => 'Top Outgoing'] as $key => $label) {
                        if (empty($data['notable_transactions'][$key])) {
                            continue;
                        }
                        fputcsv($out, [$label]);
                        fputcsv($out, ['Date', 'Ref Type', 'Amount (ISK)', 'Other Party', 'Description']);
                        foreach ($data['notable_transactions'][$key] as $t) {
                            $t = (array) $t;
                            // For incoming, the "other party" is whoever paid us
                            // (first_party_*). For outgoing, the destination
                            // (second_party_*).
                            $other = $key === 'incoming'
                                ? ($t['first_party_name'] ?? (isset($t['first_party_id']) ? 'ID ' . $t['first_party_id'] : ''))
                                : ($t['second_party_name'] ?? (isset($t['second_party_id']) ? 'ID ' . $t['second_party_id'] : ''));
                            fputcsv($out, [
                                $t['date'] ?? '',
                                $t['ref_type'] ?? '',
                                number_format((float) ($t['amount'] ?? 0), 2, '.', ''),
                                $other,
                                $t['description'] ?? '',
                            ]);
                        }
                        fputcsv($out, []);
                    }
                }

                if (! empty($data['member_milestones'])) {
                    fputcsv($out, ['MILESTONES REACHED']);
                    fputcsv($out, ['Character', 'Highest Milestone (ISK)', 'Reached At']);
                    foreach ($data['member_milestones'] as $m) {
                        $m = (array) $m;
                        fputcsv($out, [
                            $m['character_name'] ?? ('Character ' . ($m['character_id'] ?? '')),
                            number_format((float) ($m['highest_milestone_isk'] ?? 0), 2, '.', ''),
                            $m['reached_at'] ?? '',
                        ]);
                    }
                    fputcsv($out, []);
                }

                if (! empty($data['prior_period'])) {
                    $pp = $data['prior_period'];
                    fputcsv($out, ['PRIOR PERIOD COMPARISON']);
                    fputcsv($out, ['Prior Period', ($pp['period_from'] ?? '') . ' to ' . ($pp['period_to'] ?? '')]);
                    fputcsv($out, ['Prior Income (ISK)', number_format((float) ($pp['income'] ?? 0), 2, '.', '')]);
                    fputcsv($out, ['Prior Expense (ISK)', number_format((float) ($pp['expense'] ?? 0), 2, '.', '')]);
                    fputcsv($out, ['Prior Net Change (ISK)', number_format((float) ($pp['net_change'] ?? 0), 2, '.', '')]);
                    fputcsv($out, ['Prior End Balance (ISK)', number_format((float) ($pp['end_balance'] ?? 0), 2, '.', '')]);
                    fputcsv($out, []);
                }

                // v3.0 retro retrofit sections — same labelled-section
                // + blank-row pattern. Each block is gated on the key
                // being present so legacy reports CSV identically.

                if (! empty($data['expense_attribution']['by_category'])) {
                    $ea = $data['expense_attribution'];
                    fputcsv($out, ['EXPENSE ATTRIBUTION']);
                    fputcsv($out, ['Total Expense (ISK)', number_format((float) ($ea['total_expense'] ?? 0), 2, '.', '')]);
                    fputcsv($out, []);
                    fputcsv($out, ['Category', 'Total (ISK)', '% of Expenses', 'Transactions']);
                    foreach ($ea['by_category'] as $row) {
                        $row = (array) $row;
                        fputcsv($out, [
                            $row['label'] ?? ($row['category'] ?? ''),
                            number_format((float) ($row['total'] ?? 0), 2, '.', ''),
                            number_format((float) ($row['pct_of_total'] ?? 0), 2),
                            (int) ($row['count'] ?? 0),
                        ]);
                    }
                    fputcsv($out, []);
                }

                if (! empty($data['alliance_tax_expected'])) {
                    $ate = $data['alliance_tax_expected'];
                    fputcsv($out, ['ALLIANCE TAX EXPECTED']);
                    fputcsv($out, ['Total Expected (ISK)', number_format((float) ($ate['total_expected'] ?? 0), 2, '.', '')]);
                    if (! empty($ate['by_bucket'])) {
                        fputcsv($out, []);
                        fputcsv($out, ['Bucket', 'Rate (%)', 'Bucket Income (ISK)', 'Expected (ISK)']);
                        foreach ($ate['by_bucket'] as $row) {
                            $row = (array) $row;
                            fputcsv($out, [
                                $row['bucket'] ?? '',
                                number_format((float) ($row['rate_pct'] ?? 0), 2),
                                number_format((float) ($row['bucket_total'] ?? 0), 2, '.', ''),
                                number_format((float) ($row['expected_alliance_tax'] ?? 0), 2, '.', ''),
                            ]);
                        }
                    }
                    fputcsv($out, []);
                }

                if (! empty($data['mm_compliance'])) {
                    $mm = $data['mm_compliance'];
                    fputcsv($out, ['MINING MANAGER COMPLIANCE']);
                    fputcsv($out, ['Total Owed (ISK)', number_format((float) ($mm['owed'] ?? 0), 2, '.', '')]);
                    fputcsv($out, ['Total Paid (ISK)', number_format((float) ($mm['paid'] ?? 0), 2, '.', '')]);
                    fputcsv($out, ['Compliance (%)', number_format((float) ($mm['compliance_pct'] ?? 0), 2)]);
                    fputcsv($out, ['Members With Owed', (int) ($mm['members_with_owed'] ?? 0)]);
                    fputcsv($out, ['Members Fully Paid', (int) ($mm['members_compliant'] ?? 0)]);
                    fputcsv($out, []);
                }

                if (! empty($data['anomaly_summary'])) {
                    $anom  = $data['anomaly_summary'];
                    $drops = $anom['contribution_drops'] ?? [];
                    $unus  = $anom['unusual_recipients'] ?? [];
                    fputcsv($out, ['ANOMALY SUMMARY']);
                    fputcsv($out, ['Total Anomalies', (int) ($anom['total_anomalies'] ?? (count($drops) + count($unus)))]);
                    if (! empty($anom['note'])) {
                        fputcsv($out, ['Note', $anom['note']]);
                    }
                    if (! empty($drops)) {
                        fputcsv($out, []);
                        fputcsv($out, ['Contribution Drops']);
                        fputcsv($out, ['Character', 'Prior 3-Month Avg (ISK)', 'Recent 3-Month Avg (ISK)', 'Raised At']);
                        foreach ($drops as $d) {
                            $d = (array) $d;
                            fputcsv($out, [
                                $d['character_name'] ?? ('Character ' . ($d['character_id'] ?? '')),
                                number_format((float) ($d['prior_avg'] ?? 0), 2, '.', ''),
                                number_format((float) ($d['recent_avg'] ?? 0), 2, '.', ''),
                                $d['raised_at'] ?? '',
                            ]);
                        }
                    }
                    if (! empty($unus)) {
                        fputcsv($out, []);
                        fputcsv($out, ['Unusual Recipients']);
                        fputcsv($out, ['Date', 'Recipient', 'Amount (ISK)', 'Division', 'Description']);
                        foreach ($unus as $u) {
                            $u = (array) $u;
                            fputcsv($out, [
                                $u['date'] ?? '',
                                $u['recipient_name'] ?? ('Entity ' . ($u['recipient_id'] ?? '')),
                                number_format(abs((float) ($u['amount'] ?? 0)), 2, '.', ''),
                                (int) ($u['division'] ?? 0),
                                $u['description'] ?? '',
                            ]);
                        }
                    }
                    fputcsv($out, []);
                }

                fclose($out);
            }, $filename, [
                'Content-Type' => 'text/csv',
            ]);
        } catch (\Throwable $e) {
            Log::error('Report CSV export failed', [
                'report_id' => $report,
                'error'     => $e->getMessage(),
            ]);

            return redirect()
                ->route('corpwalletmanager.director')
                ->with('error', 'CSV export failed: ' . $e->getMessage());
        }
    }

    /**
     * Load a stored report and decode its data, enforcing per-corp authz.
     * Returns [stdClass $row, array $data] or aborts.
     *
     * @return array{0:object,1:array}
     */
    private function loadReportForExport(int $id): array
    {
        $row = DB::table('corpwalletmanager_reports')->where('id', $id)->first();
        if (! $row) {
            abort(404, 'Report not found.');
        }

        if (! $this->userIsAdmin()) {
            $corps = $this->userCorporationIds();
            if (! in_array((int) $row->corporation_id, $corps, true)) {
                abort(403, 'You are not authorized to view this corporation\'s reports.');
            }
        }

        $data = is_string($row->data) ? json_decode($row->data, true) : (array) $row->data;
        if (! is_array($data)) {
            $data = [];
        }

        return [$row, $data];
    }

    /**
     * Compose a stable filename for the download.
     */
    private function reportFileName($row, string $extension): string
    {
        return sprintf(
            'corpwallet_%s_%s_%s_to_%s.%s',
            $row->report_type ?? 'report',
            $row->corporation_id,
            $row->date_from,
            $row->date_to,
            $extension
        );
    }

    /**
     * Resolve a corp ID to its human name with a sensible fallback.
     */
    private function resolveCorpName(int $corporationId): string
    {
        return DB::table('corporation_infos')
            ->where('corporation_id', $corporationId)
            ->value('name') ?? ('Corporation ' . $corporationId);
    }
}
