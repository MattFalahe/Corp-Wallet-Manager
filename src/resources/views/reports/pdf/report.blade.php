<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ ucfirst($report->report_type ?? 'Report') }} Report - {{ $corpName }}</title>
<style>
    @page { margin: 1.5cm 1.5cm 2cm 1.5cm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10pt; color: #333; line-height: 1.4; }
    h1 { color: #667eea; font-size: 18pt; margin: 0 0 6px 0; }
    h2 { color: #667eea; font-size: 13pt; margin: 18px 0 8px 0; border-bottom: 1px solid #ddd; padding-bottom: 4px; }
    h3 { color: #555;    font-size: 11pt; margin: 14px 0 6px 0; }
    .meta { color: #666; font-size: 9pt; margin-bottom: 16px; }
    .meta div { margin: 2px 0; }
    table { border-collapse: collapse; width: 100%; margin-bottom: 8px; }
    th, td { border: 1px solid #ddd; padding: 5px 8px; text-align: left; font-size: 9pt; vertical-align: middle; }
    th { background: #f0f4f8; font-weight: bold; color: #555; }
    td.num, th.num { text-align: right; font-family: 'DejaVu Sans Mono', monospace; }
    td.desc { font-size: 8pt; color: #666; }
    .summary-grid td { border: 0; padding: 4px 0; }
    .pill { display: inline-block; padding: 2px 8px; border-radius: 8px; font-size: 8pt; font-weight: bold; color: #fff; }
    .pill-high { background: #e74c3c; }
    .pill-medium { background: #f39c12; }
    .pill-low { background: #27ae60; }
    .pill-verylow { background: #3498db; }
    .muted { color: #999; font-style: italic; font-size: 9pt; }
    .pos { color: #27ae60; }
    .neg { color: #e74c3c; }
    .bar-wrap { width: 100%; height: 10px; background: #f0f0f0; border: 1px solid #e0e0e0; border-radius: 3px; }
    .bar { height: 100%; border-radius: 3px; }
    .swatch { display: inline-block; width: 12px; height: 12px; border-radius: 2px; vertical-align: middle; }
    .headline { background: #f0f4f8; border-left: 3px solid #667eea; padding: 8px 12px; margin: 12px 0; font-size: 11pt; color: #444; }
    .description { background: #fafbfc; border: 1px solid #eee; padding: 8px 12px; margin: 8px 0 16px 0; font-size: 9pt; color: #666; }
    .footer { position: fixed; bottom: -1cm; left: 0; right: 0; text-align: center; font-size: 8pt; color: #999; }
</style>
</head>
<body>

<h1>{{ ucfirst($report->report_type ?? 'Report') }} Wallet Report</h1>
<div class="meta">
    <div><strong>Corporation:</strong> {{ $corpName }} (ID: {{ $report->corporation_id }})</div>
    <div><strong>Period:</strong> {{ $report->date_from }} to {{ $report->date_to }}</div>
    <div><strong>Generated:</strong> {{ $report->created_at }}</div>
</div>

{{-- Per-report-type description block. Renders only when the stored
     payload carries report_description (populated by GenerateReport in
     v3.0+). Legacy reports without this key skip the block. --}}
@if(! empty($data['report_description']))
    <div class="description">{{ $data['report_description'] }}</div>
@endif

{{-- Executive one-line headline. Executive Summary populates this with
     a copy-pasteable sentence ("Mercurialis Inc. is up 2.1B ISK this
     period, MEDIUM risk with 45 days of runway"). Only renders when
     present so retro / financial / division / daily reports skip it. --}}
@if(! empty($data['one_line_headline']))
    <div class="headline">{{ $data['one_line_headline'] }}</div>
@endif

@if(! empty($data['balance_history']))
    @php $bh = $data['balance_history']; @endphp
    <h2>Balance Summary</h2>
    <table class="summary-grid">
        <tr>
            <td><strong>Start Balance:</strong></td>
            <td class="num">{{ number_format((float) ($bh['start_balance'] ?? 0), 2) }} ISK</td>
            <td><strong>End Balance:</strong></td>
            <td class="num">{{ number_format((float) ($bh['end_balance'] ?? 0), 2) }} ISK</td>
        </tr>
        <tr>
            <td><strong>Change:</strong></td>
            <td class="num">{{ number_format((float) ($bh['change'] ?? 0), 2) }} ISK</td>
            <td><strong>Change %:</strong></td>
            <td class="num">{{ number_format((float) ($bh['change_percent'] ?? 0), 2) }}%</td>
        </tr>
    </table>
@endif

@if(! empty($data['income_analysis']) || ! empty($data['expense_analysis']))
    <h2>Income &amp; Expense</h2>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th class="num">Total (ISK)</th>
                <th class="num">Transactions</th>
                <th class="num">Average</th>
                <th class="num">Highest</th>
                <th class="num">Lowest</th>
            </tr>
        </thead>
        <tbody>
            @foreach(['income' => 'Income', 'expense' => 'Expense'] as $key => $label)
                @php $section = $data[$key . '_analysis'] ?? null; @endphp
                @if($section)
                    <tr>
                        <td>{{ $label }}</td>
                        <td class="num">{{ number_format((float) ($section['total'] ?? 0), 2) }}</td>
                        <td class="num">{{ number_format((int) ($section['transactions'] ?? 0)) }}</td>
                        <td class="num">{{ number_format((float) ($section['average'] ?? 0), 2) }}</td>
                        <td class="num">{{ number_format((float) ($section['highest'] ?? 0), 2) }}</td>
                        <td class="num">{{ number_format((float) ($section['lowest'] ?? 0), 2) }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
@endif

{{-- Income Breakdown by Ref Type (Financial Analysis only). --}}
@if(! empty($data['income_breakdown']))
    <h2>Income Breakdown by Ref Type</h2>
    <table>
        <thead>
            <tr>
                <th>Ref Type</th>
                <th class="num">Count</th>
                <th class="num">Total (ISK)</th>
                <th class="num">Average (ISK)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['income_breakdown'] as $tx)
                @php $t = (array) $tx; @endphp
                <tr>
                    <td>{{ str_replace('_', ' ', (string) ($t['ref_type'] ?? '')) }}</td>
                    <td class="num">{{ number_format((int) ($t['count'] ?? 0)) }}</td>
                    <td class="num">{{ number_format((float) ($t['total_amount'] ?? 0), 2) }}</td>
                    <td class="num">{{ number_format((float) ($t['avg_amount'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Expense Breakdown by Ref Type (Financial Analysis only). --}}
@if(! empty($data['expense_breakdown']))
    <h2>Expense Breakdown by Ref Type</h2>
    <table>
        <thead>
            <tr>
                <th>Ref Type</th>
                <th class="num">Count</th>
                <th class="num">Total (ISK)</th>
                <th class="num">Average (ISK)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['expense_breakdown'] as $tx)
                @php $t = (array) $tx; @endphp
                <tr>
                    <td>{{ str_replace('_', ' ', (string) ($t['ref_type'] ?? '')) }}</td>
                    <td class="num">{{ number_format((int) ($t['count'] ?? 0)) }}</td>
                    <td class="num">{{ number_format((float) ($t['total_amount'] ?? 0), 2) }}</td>
                    <td class="num">{{ number_format((float) ($t['avg_amount'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if(! empty($data['transaction_breakdown']))
    <h2>Transaction Breakdown by Ref Type</h2>
    <table>
        <thead>
            <tr>
                <th>Ref Type</th>
                <th class="num">Count</th>
                <th class="num">Total (ISK)</th>
                <th class="num">Average (ISK)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['transaction_breakdown'] as $tx)
                @php $t = (array) $tx; @endphp
                <tr>
                    <td>{{ str_replace('_', ' ', (string) ($t['ref_type'] ?? '')) }}</td>
                    <td class="num">{{ number_format((int) ($t['count'] ?? 0)) }}</td>
                    <td class="num">{{ number_format((float) ($t['total_amount'] ?? 0), 2) }}</td>
                    <td class="num">{{ number_format((float) ($t['avg_amount'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Division Summary. Detects the rich Division-Performance shape
     (rows carry balance_start / balance_end / top_ref_types_in /
     top_ref_types_out) and renders the per-division top-ref-types
     detail when present; otherwise falls back to the legacy compact
     table for retro reports and the basic shape. --}}
@if(! empty($data['division_summary']))
    @php
        $firstDiv = (array) ($data['division_summary'][0] ?? []);
        $isRichDivision = array_key_exists('balance_start', $firstDiv)
            || array_key_exists('top_ref_types_in', $firstDiv);
    @endphp
    <h2>Division Summary</h2>
    @if($isRichDivision)
        <table>
            <thead>
                <tr>
                    <th>Division</th>
                    <th class="num">Transactions</th>
                    <th class="num">Opening (ISK)</th>
                    <th class="num">Closing (ISK)</th>
                    <th class="num">Income (ISK)</th>
                    <th class="num">Expenses (ISK)</th>
                    <th class="num">Net Change (ISK)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['division_summary'] as $div)
                    @php $d = (array) $div; @endphp
                    <tr>
                        <td>Division {{ $d['division'] ?? '' }}</td>
                        <td class="num">{{ number_format((int) ($d['transactions'] ?? 0)) }}</td>
                        <td class="num">{{ number_format((float) ($d['balance_start'] ?? 0), 2) }}</td>
                        <td class="num">{{ number_format((float) ($d['balance_end'] ?? 0), 2) }}</td>
                        <td class="num">{{ number_format((float) ($d['income'] ?? 0), 2) }}</td>
                        <td class="num">{{ number_format((float) ($d['expenses'] ?? 0), 2) }}</td>
                        <td class="num {{ ((float) ($d['net_change'] ?? 0)) >= 0 ? 'pos' : 'neg' }}">{{ number_format((float) ($d['net_change'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Per-division top-ref-type detail (only present in the rich
             Division Performance shape). Skips divisions with no
             activity to avoid noise. --}}
        @foreach($data['division_summary'] as $div)
            @php
                $d        = (array) $div;
                $topIn    = $d['top_ref_types_in']  ?? [];
                $topOut   = $d['top_ref_types_out'] ?? [];
                $hasDetail = ! empty($topIn) || ! empty($topOut);
            @endphp
            @if($hasDetail)
                <h3>Division {{ $d['division'] ?? '' }} - Top Ref Types</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Direction</th>
                            <th>Ref Type</th>
                            <th class="num">Amount (ISK)</th>
                            <th class="num">Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topIn as $rt)
                            @php $rt = (array) $rt; @endphp
                            <tr>
                                <td><span class="pos">Incoming</span></td>
                                <td>{{ str_replace('_', ' ', (string) ($rt['ref_type'] ?? '')) }}</td>
                                <td class="num">{{ number_format((float) ($rt['amount'] ?? 0), 2) }}</td>
                                <td class="num">{{ number_format((int) ($rt['count'] ?? 0)) }}</td>
                            </tr>
                        @endforeach
                        @foreach($topOut as $rt)
                            @php $rt = (array) $rt; @endphp
                            <tr>
                                <td><span class="neg">Outgoing</span></td>
                                <td>{{ str_replace('_', ' ', (string) ($rt['ref_type'] ?? '')) }}</td>
                                <td class="num">{{ number_format((float) ($rt['amount'] ?? 0), 2) }}</td>
                                <td class="num">{{ number_format((int) ($rt['count'] ?? 0)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach
    @else
        <table>
            <thead>
                <tr>
                    <th>Division</th>
                    <th class="num">Transactions</th>
                    <th class="num">Income (ISK)</th>
                    <th class="num">Expenses (ISK)</th>
                    <th class="num">Net Change (ISK)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['division_summary'] as $div)
                    @php $d = (array) $div; @endphp
                    <tr>
                        <td>{{ $d['division'] ?? '' }}</td>
                        <td class="num">{{ number_format((int) ($d['transactions'] ?? 0)) }}</td>
                        <td class="num">{{ number_format((float) ($d['income'] ?? 0), 2) }}</td>
                        <td class="num">{{ number_format((float) ($d['expenses'] ?? 0), 2) }}</td>
                        <td class="num">{{ number_format((float) ($d['net_change'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

{{-- Internal Transfers Summary (Division Performance only). Counts +
     sums the inter-division movements that the corp totals exclude. --}}
@if(! empty($data['internal_transfers_summary']))
    @php $its = $data['internal_transfers_summary']; @endphp
    <h2>Internal Transfers</h2>
    @if((int) ($its['total_count'] ?? 0) === 0)
        <p class="muted">No inter-division transfers recorded in this period.</p>
    @else
        <p class="muted">
            Inter-division transfers move ISK between corp wallet divisions. They
            net to zero at the corp level and are excluded from the corp totals
            above, but they matter for per-division balance tracking.
        </p>
        <table class="summary-grid">
            <tr>
                <td><strong>Total Transfers:</strong></td>
                <td class="num">{{ number_format((int) ($its['total_count'] ?? 0)) }}</td>
                <td><strong>Total Amount:</strong></td>
                <td class="num">{{ number_format((float) ($its['total_amount'] ?? 0), 2) }} ISK</td>
            </tr>
        </table>
        @if(! empty($its['per_division']))
            <table>
                <thead>
                    <tr>
                        <th>Receiving Division</th>
                        <th class="num">Transfers Received</th>
                        <th class="num">Amount Received (ISK)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($its['per_division'] as $row)
                        @php $row = (array) $row; @endphp
                        <tr>
                            <td>Division {{ $row['division'] ?? '' }}</td>
                            <td class="num">{{ number_format((int) ($row['transfer_count'] ?? 0)) }}</td>
                            <td class="num">{{ number_format((float) ($row['total_amount'] ?? 0), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif
@endif

{{-- Top Contributors block. Executive Summary populates this with the
     top 3, retro reports populate with the top 10 (rendered inside
     the _retro_sections include). To avoid double-rendering, we only
     render here when the report is NOT a retro shape (no
     monthly_breakdown / activity_breakdown). --}}
@php
    $isRetroReport = ! empty($data['monthly_breakdown'])
        || ! empty($data['activity_breakdown']['buckets'])
        || ! empty($data['notable_transactions'])
        || ! empty($data['member_milestones'])
        || ! empty($data['expense_attribution']['by_category'])
        || ! empty($data['alliance_tax_expected'])
        || ! empty($data['mm_compliance'])
        || ! empty($data['anomaly_summary']);
@endphp
@if(! empty($data['top_contributors']) && ! $isRetroReport)
    <h2>Top Contributors</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Character</th>
                <th class="num">Total (ISK)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['top_contributors'] as $i => $c)
                @php $c = (array) $c; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $c['character_name'] ?? ('Character ' . ($c['character_id'] ?? '')) }}</td>
                    <td class="num">{{ number_format((float) ($c['total_contribution_amount'] ?? 0), 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if(! empty($data['risk_assessment']))
    @php
        $ra = $data['risk_assessment'];
        $pillClass = match (strtolower($ra['risk_level'] ?? '')) {
            'high'     => 'pill-high',
            'medium'   => 'pill-medium',
            'low'      => 'pill-low',
            'very_low' => 'pill-verylow',
            default    => 'pill-low',
        };
    @endphp
    <h2>Risk Assessment</h2>
    <table class="summary-grid">
        <tr>
            <td><strong>Current Balance:</strong></td>
            <td class="num">{{ number_format((float) ($ra['current_balance'] ?? 0), 2) }} ISK</td>
            <td><strong>Days of Runway:</strong></td>
            <td class="num">{{ number_format((float) ($ra['days_of_runway'] ?? 0), 1) }} days</td>
        </tr>
        <tr>
            <td><strong>Volatility:</strong></td>
            <td class="num">{{ number_format((float) ($ra['volatility'] ?? 0), 2) }}</td>
            <td><strong>Risk Level:</strong></td>
            <td><span class="pill {{ $pillClass }}">{{ $ra['risk_level'] ?? 'UNKNOWN' }}</span></td>
        </tr>
    </table>
@endif

{{-- v3.0 retro retrofit: weekly + monthly reports populate the same
     retro keys annual + quarterly do, so they render the shared
     sections (monthly trend, top contributors, activity mix, expense
     attribution, notable transactions, alliance tax expected + actual,
     MM compliance, milestones, anomaly summary). Each section gates on
     its own data key so daily / executive / financial / division /
     custom reports (which leave the retro keys null) skip the block
     entirely and render exactly as before.

     Financial Analysis populates SOME of these keys (activity,
     expense_attribution, notable_transactions, alliance_tax_*,
     mm_compliance) so the retro partial happily renders those
     sections — the partial skips any key it doesn't see. The
     prior_period block at the bottom of the retro partial is the only
     bit Financial doesn't want because it expects a same-cadence
     prior; Executive uses its own prior_period (same range immediately
     before) so the comparison there reads correctly. --}}
@if($isRetroReport)
    @include('corpwalletmanager::reports.pdf._retro_sections')
@endif

<div class="footer">Generated by Corp Wallet Manager [CWM] &middot; {{ $report->created_at }}</div>

</body>
</html>
