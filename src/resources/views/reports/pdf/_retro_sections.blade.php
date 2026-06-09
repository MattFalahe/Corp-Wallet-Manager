{{--
    Shared retrospective sections rendered by every cadence that
    populates the v3 retro keys: weekly, monthly, quarterly, annual.
    Daily and on-demand cadences (executive / financial / division /
    custom) do NOT populate these keys, so including this partial
    from a non-retro template is safe (every section is gated on its
    own data key and renders nothing when the key is absent).

    Expected variables in scope:
      $report   - the corpwalletmanager_reports row (stdClass)
      $data     - the decoded JSON payload (array)
      $corpName - resolved corp name string

    The annual / quarterly templates wrap this with their cover page
    + executive summary + YoY/QoQ comparison; the weekly / monthly
    template renders this after the legacy sections so a stored
    report row with no retro keys still renders identically to v2.
--}}

{{-- Monthly Balance Trend --}}
@if(! empty($data['monthly_breakdown']))
    <h2>Monthly Balance Trend</h2>
    @php
        // Find the max absolute monthly change for inline bar scaling.
        $scale = 0.0;
        foreach ($data['monthly_breakdown'] as $m) {
            $abs = abs((float) ($m['net'] ?? 0));
            if ($abs > $scale) $scale = $abs;
        }
        if ($scale <= 0) $scale = 1.0;
    @endphp
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th class="num">Income</th>
                <th class="num">Expenses</th>
                <th class="num">Net Change</th>
                <th>Trend</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['monthly_breakdown'] as $m)
                @php
                    $net = (float) ($m['net'] ?? 0);
                    $barWidth = min(100, (int) round((abs($net) / $scale) * 100));
                    $barColor = $net >= 0 ? '#27ae60' : '#e74c3c';
                @endphp
                <tr>
                    <td>{{ $m['period'] ?? '' }}</td>
                    <td class="num">{{ number_format((float) ($m['income'] ?? 0), 0) }}</td>
                    <td class="num">{{ number_format((float) ($m['expenses'] ?? 0), 0) }}</td>
                    <td class="num">{{ number_format($net, 0) }}</td>
                    <td>
                        <div class="bar-wrap">
                            <div class="bar" style="width: {{ $barWidth }}%; background: {{ $barColor }};"></div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Top Contributors --}}
@if(! empty($data['top_contributors']))
    <h2>Top Contributors</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Character</th>
                <th class="num">Ratting</th>
                <th class="num">Mission</th>
                <th class="num">Industry</th>
                <th class="num">Tax</th>
                <th class="num">Donation</th>
                <th class="num">Total</th>
                <th class="num">Months Active</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['top_contributors'] as $i => $c)
                @php $c = (array) $c; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $c['character_name'] ?? ('Character ' . ($c['character_id'] ?? '')) }}</td>
                    <td class="num">{{ number_format((float) ($c['ratting_amount'] ?? 0), 0) }}</td>
                    <td class="num">{{ number_format((float) ($c['mission_amount'] ?? 0), 0) }}</td>
                    <td class="num">{{ number_format((float) ($c['industry_amount'] ?? 0), 0) }}</td>
                    <td class="num">{{ number_format((float) ($c['tax_payment_amount'] ?? 0), 0) }}</td>
                    <td class="num">{{ number_format((float) ($c['donation_voluntary_amount'] ?? 0), 0) }}</td>
                    <td class="num"><strong>{{ number_format((float) ($c['total_contribution_amount'] ?? 0), 0) }}</strong></td>
                    <td class="num">{{ (int) ($c['months_active'] ?? 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Activity Mix --}}
@if(! empty($data['activity_breakdown']['buckets']))
    <h2>Activity Mix</h2>
    @php
        // Distinct color per activity bucket, matching the convention used
        // across the suite.
        $activityColors = [
            'ratting'            => '#27ae60',
            'mission'            => '#3498db',
            'industry'           => '#9b59b6',
            'tax_payment'        => '#f39c12',
            'donation_voluntary' => '#1abc9c',
        ];
    @endphp
    <table>
        <thead>
            <tr>
                <th></th>
                <th>Activity</th>
                <th class="num">Total ISK</th>
                <th class="num">% of Contributions</th>
                <th class="num">Members</th>
                <th>Share</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['activity_breakdown']['buckets'] as $bucket => $entry)
                @php
                    $entry = (array) $entry;
                    $pct = (float) ($entry['pct'] ?? 0);
                    $barWidth = max(1, (int) round($pct));
                    $color = $activityColors[$bucket] ?? '#667eea';
                @endphp
                <tr>
                    <td><span class="swatch" style="background: {{ $color }};"></span></td>
                    <td>{{ str_replace('_', ' ', $bucket) }}</td>
                    <td class="num">{{ number_format((float) ($entry['amount'] ?? 0), 0) }}</td>
                    <td class="num">{{ number_format($pct, 1) }}%</td>
                    <td class="num">{{ (int) ($entry['members'] ?? 0) }}</td>
                    <td>
                        <div class="bar-wrap">
                            <div class="bar" style="width: {{ $barWidth }}%; background: {{ $color }};"></div>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Expense Attribution (v3.0 retrofit) --}}
@if(! empty($data['expense_attribution']['by_category']))
    @php $ea = $data['expense_attribution']; @endphp
    <h2>Expense Attribution</h2>
    <p class="muted">Total expense in this period: {{ number_format((float) ($ea['total_expense'] ?? 0), 2) }} ISK.</p>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th class="num">Total ISK</th>
                <th class="num">% of Expenses</th>
                <th class="num">Transactions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ea['by_category'] as $row)
                @php $row = (array) $row; @endphp
                <tr>
                    <td>{{ $row['label'] ?? ($row['category'] ?? '') }}</td>
                    <td class="num">{{ number_format((float) ($row['total'] ?? 0), 2) }}</td>
                    <td class="num">{{ number_format((float) ($row['pct_of_total'] ?? 0), 1) }}%</td>
                    <td class="num">{{ number_format((int) ($row['count'] ?? 0)) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Notable Transactions --}}
@if(! empty($data['notable_transactions']['incoming']) || ! empty($data['notable_transactions']['outgoing']))
    <h2>Notable Transactions</h2>
    @if(! empty($data['notable_transactions']['incoming']))
        <h3>Top Incoming</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Ref Type</th>
                    <th class="num">Amount (ISK)</th>
                    <th>From</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['notable_transactions']['incoming'] as $t)
                    @php $t = (array) $t; @endphp
                    <tr>
                        <td>{{ substr((string) ($t['date'] ?? ''), 0, 10) }}</td>
                        <td>{{ str_replace('_', ' ', (string) ($t['ref_type'] ?? '')) }}</td>
                        <td class="num">{{ number_format((float) ($t['amount'] ?? 0), 2) }}</td>
                        <td>{{ $t['first_party_name'] ?? (isset($t['first_party_id']) ? 'ID ' . $t['first_party_id'] : '-') }}</td>
                        <td class="desc">{{ \Illuminate\Support\Str::limit((string) ($t['description'] ?? ''), 80) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    @if(! empty($data['notable_transactions']['outgoing']))
        <h3>Top Outgoing</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Ref Type</th>
                    <th class="num">Amount (ISK)</th>
                    <th>To</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['notable_transactions']['outgoing'] as $t)
                    @php $t = (array) $t; @endphp
                    <tr>
                        <td>{{ substr((string) ($t['date'] ?? ''), 0, 10) }}</td>
                        <td>{{ str_replace('_', ' ', (string) ($t['ref_type'] ?? '')) }}</td>
                        <td class="num">{{ number_format((float) ($t['amount'] ?? 0), 2) }}</td>
                        <td>{{ $t['second_party_name'] ?? (isset($t['second_party_id']) ? 'ID ' . $t['second_party_id'] : '-') }}</td>
                        <td class="desc">{{ \Illuminate\Support\Str::limit((string) ($t['description'] ?? ''), 80) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

{{-- Alliance Tax Remits + Expected (v3.0 retrofit pairs the
     expected-vs-actual numbers when both are available). --}}
@php
    $atr      = $data['alliance_tax_remit']    ?? null;
    $expected = $data['alliance_tax_expected'] ?? null;
@endphp
@if(! empty($atr['has_match_rules']) || ! empty($expected))
    <h2>Alliance Tax</h2>
    @if(! empty($expected))
        @php
            $expectedTotal = (float) ($expected['total_expected'] ?? 0);
            $actualTotal   = ! empty($atr['has_match_rules']) ? (float) ($atr['total'] ?? 0) : null;
            $diff          = $actualTotal !== null ? ($actualTotal - $expectedTotal) : null;
        @endphp
        <table class="summary-grid">
            <tr>
                <td><strong>Expected (per-bucket × rate):</strong></td>
                <td class="num">{{ number_format($expectedTotal, 2) }} ISK</td>
                <td><strong>Actual (matched remittances):</strong></td>
                <td class="num">
                    @if($actualTotal !== null)
                        {{ number_format($actualTotal, 2) }} ISK
                    @else
                        <span class="muted">No match rules configured</span>
                    @endif
                </td>
            </tr>
            @if($diff !== null)
                <tr>
                    <td><strong>Difference (actual minus expected):</strong></td>
                    <td class="num {{ $diff >= 0 ? 'pos' : 'neg' }}">{{ number_format($diff, 2) }} ISK</td>
                    <td colspan="2" class="muted">Positive: paid more than calculated (uncovered income or rate under-set). Negative: under-remitted vs calc.</td>
                </tr>
            @endif
        </table>
        @if(! empty($expected['by_bucket']))
            <h3>Expected by Bucket</h3>
            <table>
                <thead>
                    <tr>
                        <th>Bucket</th>
                        <th class="num">Rate</th>
                        <th class="num">Bucket Income (ISK)</th>
                        <th class="num">Expected (ISK)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expected['by_bucket'] as $row)
                        @php $row = (array) $row; @endphp
                        <tr>
                            <td>{{ str_replace('_', ' ', (string) ($row['bucket'] ?? '')) }}</td>
                            <td class="num">{{ number_format((float) ($row['rate_pct'] ?? 0), 2) }}%</td>
                            <td class="num">{{ number_format((float) ($row['bucket_total'] ?? 0), 2) }}</td>
                            <td class="num">{{ number_format((float) ($row['expected_alliance_tax'] ?? 0), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif
    @if(! empty($atr['has_match_rules']) && empty($expected))
        <table class="summary-grid">
            <tr>
                <td><strong>Total Remitted:</strong></td>
                <td class="num">{{ number_format((float) ($atr['total'] ?? 0), 2) }} ISK</td>
                <td><strong>Matched Payments:</strong></td>
                <td class="num">{{ number_format((int) ($atr['count'] ?? 0)) }}</td>
            </tr>
        </table>
    @endif
    @if(! empty($atr['by_ref_type']))
        <h3>Actual Remittance by Ref Type</h3>
        <table>
            <thead>
                <tr>
                    <th>Ref Type</th>
                    <th class="num">Amount (ISK)</th>
                    <th class="num">Count</th>
                </tr>
            </thead>
            <tbody>
                @foreach($atr['by_ref_type'] as $rt)
                    @php $rt = (array) $rt; @endphp
                    <tr>
                        <td>{{ str_replace('_', ' ', (string) ($rt['ref_type'] ?? '')) }}</td>
                        <td class="num">{{ number_format((float) ($rt['amount'] ?? 0), 2) }}</td>
                        <td class="num">{{ number_format((int) ($rt['count'] ?? 0)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

{{-- Mining Manager Compliance (v3.0 retrofit) --}}
@if(! empty($data['mm_compliance']))
    @php $mm = $data['mm_compliance']; @endphp
    <h2>Mining Manager Tax Compliance</h2>
    <table class="summary-grid">
        <tr>
            <td><strong>Total Owed:</strong></td>
            <td class="num">{{ number_format((float) ($mm['owed'] ?? 0), 2) }} ISK</td>
            <td><strong>Total Paid:</strong></td>
            <td class="num">{{ number_format((float) ($mm['paid'] ?? 0), 2) }} ISK</td>
        </tr>
        <tr>
            <td><strong>Compliance:</strong></td>
            <td class="num">{{ number_format((float) ($mm['compliance_pct'] ?? 0), 1) }}%</td>
            <td><strong>Members:</strong></td>
            <td class="num">
                {{ (int) ($mm['members_compliant'] ?? 0) }} of {{ (int) ($mm['members_with_owed'] ?? 0) }} fully paid
            </td>
        </tr>
    </table>
@endif

{{-- Member Milestones --}}
@if(! empty($data['member_milestones']))
    <h2>Milestones Reached</h2>
    <table>
        <thead>
            <tr>
                <th>Character</th>
                <th class="num">Highest Milestone (ISK)</th>
                <th>Reached At</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['member_milestones'] as $m)
                @php $m = (array) $m; @endphp
                <tr>
                    <td>{{ $m['character_name'] ?? ('Character ' . ($m['character_id'] ?? '')) }}</td>
                    <td class="num">{{ number_format((float) ($m['highest_milestone_isk'] ?? 0), 0) }}</td>
                    <td>{{ substr((string) ($m['reached_at'] ?? ''), 0, 10) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Prior Period Comparison (week-over-week / month-over-month /
     quarter-over-quarter / year-over-year). When included from
     _partials.blade.php (annual + quarterly), the Comparison block
     there renders this same content using $kind + $compLabel and we
     skip the duplicate. The $partials_includes_comparison flag is
     set true by _partials.blade.php right before @include to opt out
     of the duplicate render. --}}
@php
    $partialsHandlesComparison = isset($partials_includes_comparison) ? (bool) $partials_includes_comparison : false;
@endphp
@if(! $partialsHandlesComparison && ! empty($data['prior_period']))
    @php
        $pp        = $data['prior_period'];
        $income    = $data['income_analysis']  ?? null;
        $expense   = $data['expense_analysis'] ?? null;
        $bh        = $data['balance_history']  ?? null;
        $cadence   = (string) ($pp['kind'] ?? ($report->report_type ?? ''));
        $compLabel = match ($cadence) {
            'weekly'    => 'Week-over-Week',
            'monthly'   => 'Month-over-Month',
            'quarterly' => 'Quarter-over-Quarter',
            'annual'    => 'Year-over-Year',
            default     => 'Prior Period',
        };
        $deltaIncome  = (float) ($income['total'] ?? 0)   - (float) ($pp['income']     ?? 0);
        $deltaExpense = (float) ($expense['total'] ?? 0)  - (float) ($pp['expense']    ?? 0);
        $deltaNet     = (float) ($bh['change'] ?? 0)      - (float) ($pp['net_change'] ?? 0);
        $deltaEnd     = (float) ($bh['end_balance'] ?? 0) - (float) ($pp['end_balance'] ?? 0);
    @endphp
    <h2>{{ $compLabel }} Comparison</h2>
    <table>
        <thead>
            <tr>
                <th>Metric</th>
                <th class="num">This Period</th>
                <th class="num">Prior Period ({{ $pp['period_from'] ?? '' }} to {{ $pp['period_to'] ?? '' }})</th>
                <th class="num">Delta</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Total Income</td>
                <td class="num">{{ number_format((float) ($income['total'] ?? 0), 2) }}</td>
                <td class="num">{{ number_format((float) ($pp['income'] ?? 0), 2) }}</td>
                <td class="num {{ $deltaIncome >= 0 ? 'pos' : 'neg' }}">{{ number_format($deltaIncome, 2) }}</td>
            </tr>
            <tr>
                <td>Total Expenses</td>
                <td class="num">{{ number_format((float) ($expense['total'] ?? 0), 2) }}</td>
                <td class="num">{{ number_format((float) ($pp['expense'] ?? 0), 2) }}</td>
                <td class="num {{ $deltaExpense <= 0 ? 'pos' : 'neg' }}">{{ number_format($deltaExpense, 2) }}</td>
            </tr>
            <tr>
                <td>Net Change</td>
                <td class="num">{{ number_format((float) ($bh['change'] ?? 0), 2) }}</td>
                <td class="num">{{ number_format((float) ($pp['net_change'] ?? 0), 2) }}</td>
                <td class="num {{ $deltaNet >= 0 ? 'pos' : 'neg' }}">{{ number_format($deltaNet, 2) }}</td>
            </tr>
            <tr>
                <td>End Balance</td>
                <td class="num">{{ number_format((float) ($bh['end_balance'] ?? 0), 2) }}</td>
                <td class="num">{{ number_format((float) ($pp['end_balance'] ?? 0), 2) }}</td>
                <td class="num {{ $deltaEnd >= 0 ? 'pos' : 'neg' }}">{{ number_format($deltaEnd, 2) }}</td>
            </tr>
        </tbody>
    </table>
@endif

{{-- Anomaly Summary (v3.0 retrofit) --}}
@if(! empty($data['anomaly_summary']))
    @php
        $anom  = $data['anomaly_summary'];
        $drops = $anom['contribution_drops']  ?? [];
        $unus  = $anom['unusual_recipients']  ?? [];
        $totalAnomalies = (int) ($anom['total_anomalies'] ?? (count($drops) + count($unus)));
    @endphp
    <h2>Anomaly Summary</h2>
    @if(! empty($anom['note']))
        <p class="muted">{{ $anom['note'] }}</p>
    @endif
    @if($totalAnomalies === 0)
        <p class="muted">No anomalies raised during this period (or the detectors are disabled in Settings).</p>
    @else
        @if(! empty($drops))
            <h3>Contribution Drops</h3>
            <table>
                <thead>
                    <tr>
                        <th>Character</th>
                        <th class="num">Prior 3-Month Average (ISK)</th>
                        <th class="num">Recent 3-Month Average (ISK)</th>
                        <th>Raised At</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($drops as $d)
                        @php $d = (array) $d; @endphp
                        <tr>
                            <td>{{ $d['character_name'] ?? ('Character ' . ($d['character_id'] ?? '')) }}</td>
                            <td class="num">{{ number_format((float) ($d['prior_avg'] ?? 0), 0) }}</td>
                            <td class="num">{{ number_format((float) ($d['recent_avg'] ?? 0), 0) }}</td>
                            <td>{{ substr((string) ($d['raised_at'] ?? ''), 0, 16) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @if(! empty($unus))
            <h3>Unusual Recipients</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Recipient</th>
                        <th class="num">Amount (ISK)</th>
                        <th class="num">Division</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($unus as $u)
                        @php $u = (array) $u; @endphp
                        <tr>
                            <td>{{ substr((string) ($u['date'] ?? ''), 0, 10) }}</td>
                            <td>{{ $u['recipient_name'] ?? ('Entity ' . ($u['recipient_id'] ?? '')) }}</td>
                            <td class="num">{{ number_format(abs((float) ($u['amount'] ?? 0)), 2) }}</td>
                            <td class="num">{{ (int) ($u['division'] ?? 0) }}</td>
                            <td class="desc">{{ \Illuminate\Support\Str::limit((string) ($u['description'] ?? ''), 80) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif
@endif
