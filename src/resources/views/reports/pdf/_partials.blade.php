{{--
    Shared retrospective partials for the annual / quarterly PDF reports.
    Pulled in via @include from each template after the cover page.

    All sections are defensive — when the underlying data array key is
    missing or empty the section renders a muted "No data" placeholder
    rather than erroring. This matches the convention the legacy
    report.blade.php uses with @if guards and keeps the templates
    operator-friendly when a corp has e.g. no divisions or no alliance
    tax recipients configured.

    Expected variables in scope:
      $report   - the corpwalletmanager_reports row (stdClass)
      $data     - the decoded JSON payload (array)
      $corpName - resolved corp name string
      $kind     - 'annual' or 'quarterly' (used for headline copy)
--}}

{{-- Executive Summary panel --}}
@php
    $bh      = $data['balance_history']    ?? null;
    $income  = $data['income_analysis']    ?? null;
    $expense = $data['expense_analysis']   ?? null;
    $ra      = $data['risk_assessment']    ?? null;
    $atr     = $data['alliance_tax_remit'] ?? null;
    $topC    = $data['top_contributors']   ?? [];
    $act     = $data['activity_breakdown'] ?? null;
    $topContributorName = (! empty($topC) && isset($topC[0]['character_name'])) ? $topC[0]['character_name'] : null;
    $topActivityName = null;
    if (! empty($act['buckets'])) {
        $sorted = $act['buckets'];
        uasort($sorted, fn ($a, $b) => ($b['amount'] ?? 0) <=> ($a['amount'] ?? 0));
        $topActivityName = array_key_first($sorted);
    }
@endphp

<h2>Executive Summary</h2>
<table class="summary-grid">
    <tr>
        <td><strong>Opening Balance:</strong></td>
        <td class="num">{{ number_format((float) ($bh['start_balance'] ?? 0), 2) }} ISK</td>
        <td><strong>Closing Balance:</strong></td>
        <td class="num">{{ number_format((float) ($bh['end_balance'] ?? 0), 2) }} ISK</td>
    </tr>
    <tr>
        <td><strong>Net Change:</strong></td>
        <td class="num">{{ number_format((float) ($bh['change'] ?? 0), 2) }} ISK ({{ number_format((float) ($bh['change_percent'] ?? 0), 2) }}%)</td>
        <td><strong>Total Income:</strong></td>
        <td class="num">{{ number_format((float) ($income['total'] ?? 0), 2) }} ISK</td>
    </tr>
    <tr>
        <td><strong>Total Expenses:</strong></td>
        <td class="num">{{ number_format((float) ($expense['total'] ?? 0), 2) }} ISK</td>
        <td><strong>Top Contributor:</strong></td>
        <td>{{ $topContributorName ?? 'No data' }}</td>
    </tr>
    <tr>
        <td><strong>Top Activity:</strong></td>
        <td>{{ $topActivityName ? str_replace('_', ' ', $topActivityName) : 'No data' }}</td>
        <td><strong>Alliance Tax Remitted:</strong></td>
        <td class="num">
            @if(! empty($atr['has_match_rules']))
                {{ number_format((float) ($atr['total'] ?? 0), 2) }} ISK
            @else
                <span class="muted">Not configured</span>
            @endif
        </td>
    </tr>
</table>

{{-- Shared retro sections: monthly trend, top contributors, activity
     mix, expense attribution (v3), notable transactions, alliance tax
     expected + actual (v3), MM compliance (v3), milestones, anomaly
     summary (v3). Falls back to muted "No data" placeholders for
     keys that aren't populated, so this works whether the stored
     report is v2 (legacy retro keys only) or v3 (full retrofit).

     The Comparison block at the end of _retro_sections is suppressed
     here because the annual / quarterly templates have their own
     Comparison block below (with the $kind-driven YoY/QoQ label). --}}
@include('corpwalletmanager::reports.pdf._retro_sections', ['partials_includes_comparison' => true])

{{-- Division Performance — kept here rather than in the shared
     partial because report.blade.php has its own Division Performance
     block already, so weekly / monthly don't want this duplicated. --}}
<h2>Division Performance</h2>
@if(empty($data['division_summary']))
    <p class="muted">No division activity recorded.</p>
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
                    <td>Division {{ $d['division'] ?? '' }}</td>
                    <td class="num">{{ number_format((int) ($d['transactions'] ?? 0)) }}</td>
                    <td class="num">{{ number_format((float) ($d['income'] ?? 0), 2) }}</td>
                    <td class="num">{{ number_format((float) ($d['expenses'] ?? 0), 2) }}</td>
                    <td class="num">{{ number_format((float) ($d['net_change'] ?? 0), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Risk Assessment + Comparison --}}
@php
    $pillClass = match (strtolower($ra['risk_level'] ?? '')) {
        'high'     => 'pill-high',
        'medium'   => 'pill-medium',
        'low'      => 'pill-low',
        'very_low' => 'pill-verylow',
        default    => 'pill-low',
    };
    $pp = $data['prior_period'] ?? null;
    $compLabel = $kind === 'quarterly' ? 'Quarter-over-Quarter' : 'Year-over-Year';
@endphp
<h2>Risk Assessment</h2>
@if(empty($ra))
    <p class="muted">No risk assessment available.</p>
@else
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

<h3>{{ $compLabel }} Comparison</h3>
@if(empty($pp))
    <p class="muted">No prior {{ $kind }} report stored for this corporation. Generate the same report type for the prior period to enable {{ strtolower($compLabel) }} comparison.</p>
@else
    @php
        $deltaIncome  = (float) ($income['total'] ?? 0)   - (float) ($pp['income']     ?? 0);
        $deltaExpense = (float) ($expense['total'] ?? 0)  - (float) ($pp['expense']    ?? 0);
        $deltaNet     = (float) ($bh['change'] ?? 0)      - (float) ($pp['net_change'] ?? 0);
        $deltaEnd     = (float) ($bh['end_balance'] ?? 0) - (float) ($pp['end_balance'] ?? 0);
    @endphp
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
