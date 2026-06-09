@extends('web::layouts.grids.12')

@section('title', 'Corp Wallet Manager - Diagnostics')
@section('page_header', 'Corp Wallet Manager - Diagnostics')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/corp-wallet-manager/css/corp-wallet-manager.css') }}?v=3">
<style>
    /* Diagnostic page chrome - scoped to .corp-wallet-wrapper.diagnostic-page
       so it cannot leak into other CWM views. Mirrors Structure Manager's
       diagnostic pattern (tab underline / boxed sections / kv lists). */

    .corp-wallet-wrapper.diagnostic-page .diag-tabs {
        display: flex;
        gap: 0;
        border-bottom: 2px solid #454d55;
        margin: 1.25rem 0;
        padding: 0;
        list-style: none;
        flex-wrap: wrap;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-tab {
        padding: 0.6rem 1.1rem;
        color: #8b95a5;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.15s;
        user-select: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-tab:hover {
        color: #c2c7d0;
        border-bottom-color: #3a4049;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-tab.active {
        color: #6366f1;
        border-bottom-color: #6366f1;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-tab-count {
        font-size: 0.72rem;
        background: #454d55;
        color: #c2c7d0;
        padding: 1px 6px;
        border-radius: 8px;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-tab.active .diag-tab-count {
        background: rgba(99, 102, 241, 0.25);
        color: #a5b4fc;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-tab-pane { display: none; }
    .corp-wallet-wrapper.diagnostic-page .diag-tab-pane.active { display: block; }

    /* Tab intro - mandatory per the diagnostic standard. */
    .corp-wallet-wrapper.diagnostic-page .diag-tab-intro {
        padding: 0.85rem 1.1rem;
        background: rgba(99, 102, 241, 0.08);
        border-left: 3px solid #6366f1;
        border-radius: 5px;
        margin-bottom: 1.25rem;
        color: #c2c7d0;
        font-size: 0.92rem;
        line-height: 1.5;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-tab-intro strong { color: #c7d2fe; }
    .corp-wallet-wrapper.diagnostic-page .diag-tab-intro p { margin-bottom: 0.4rem; }
    .corp-wallet-wrapper.diagnostic-page .diag-tab-intro p:last-child { margin-bottom: 0; }
    .corp-wallet-wrapper.diagnostic-page .diag-tab-intro code {
        color: #a5b4fc;
        background: rgba(0, 0, 0, 0.25);
        padding: 0 0.25rem;
        border-radius: 3px;
    }

    /* Boxed sections inside a tab. Matches SM's visual pattern so the
       diagnostic chrome reads the same across the suite. */
    .corp-wallet-wrapper.diagnostic-page .diag-section {
        background: #2a2f3a;
        border: 1px solid #454d55;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        overflow: hidden;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-section-header {
        padding: 0.8rem 1.2rem;
        background: #343a45;
        border-bottom: 1px solid #454d55;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-section-title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 600;
        color: #fff;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-section-body { padding: 0.5rem 0; }

    /* Status badge — used both on the .diag-summary banner and as a
       trailing pill on individual section headers. Same colour palette
       SM uses so the suite reads consistent. */
    .corp-wallet-wrapper.diagnostic-page .diag-badge {
        font-size: 0.78rem;
        font-weight: 700;
        padding: 0.25rem 0.55rem;
        border-radius: 0.25rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        flex-shrink: 0;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-badge.ok      { background: #1c6f3e; color: #d4f4e2; }
    .corp-wallet-wrapper.diagnostic-page .diag-badge.warn,
    .corp-wallet-wrapper.diagnostic-page .diag-badge.warning { background: #7a5a0f; color: #fff1c7; }
    .corp-wallet-wrapper.diagnostic-page .diag-badge.error,
    .corp-wallet-wrapper.diagnostic-page .diag-badge.fail,
    .corp-wallet-wrapper.diagnostic-page .diag-badge.danger  { background: #7a1d2b; color: #fbd5db; }
    .corp-wallet-wrapper.diagnostic-page .diag-badge.info    { background: #1d4d7a; color: #d0e4fb; }

    /* Top-of-page summary banner — aggregate counts across every
       health check. Left border colour reflects the worst status
       found so a glance at the banner tells the operator whether
       anything needs attention. */
    .corp-wallet-wrapper.diagnostic-page .diag-summary {
        padding: 1rem 1.25rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        border: 1px solid #454d55;
        background: #2a2f3a;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-summary.ok    { border-left: 4px solid #1c6f3e; }
    .corp-wallet-wrapper.diagnostic-page .diag-summary.warn  { border-left: 4px solid #7a5a0f; }
    .corp-wallet-wrapper.diagnostic-page .diag-summary.error { border-left: 4px solid #7a1d2b; }
    .corp-wallet-wrapper.diagnostic-page .diag-summary-title {
        margin: 0 0 0.35rem 0;
        font-size: 1.25rem;
        font-weight: 600;
        color: #fff;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-summary-meta {
        margin: 0;
        font-size: 0.9rem;
        color: #c2c7d0;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-summary-meta .sep {
        margin: 0 0.4rem; color: #6b7280;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-summary-actions {
        display: flex; gap: 0.5rem; flex-shrink: 0;
    }

    /* Per-check row. */
    .corp-wallet-wrapper.diagnostic-page .diag-check {
        display: flex;
        gap: 0.85rem;
        padding: 0.55rem 1.1rem;
        align-items: center;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-check + .diag-check {
        border-top: 1px solid #353b46;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-check-status {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 2px 9px;
        border-radius: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        flex-shrink: 0;
        min-width: 56px;
        text-align: center;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-check-status.pass { background: #28a745; color: #fff; }
    .corp-wallet-wrapper.diagnostic-page .diag-check-status.warn { background: #f39c12; color: #1a1d24; }
    .corp-wallet-wrapper.diagnostic-page .diag-check-status.fail { background: #e74c3c; color: #fff; }
    .corp-wallet-wrapper.diagnostic-page .diag-check-status.info { background: #3498db; color: #fff; }
    .corp-wallet-wrapper.diagnostic-page .diag-check-label {
        font-weight: 600;
        color: #e2e8f0;
        flex-shrink: 0;
        min-width: 200px;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-check-detail {
        color: #94a3b8;
        font-size: 0.88rem;
        flex: 1;
    }

    /* KV table for Settings Health / Wallet Trace details. */
    .corp-wallet-wrapper.diagnostic-page .diag-kv {
        width: 100%;
        border-collapse: collapse;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-kv th,
    .corp-wallet-wrapper.diagnostic-page .diag-kv td {
        padding: 6px 12px;
        border-top: 1px solid #353b46;
        font-size: 0.88rem;
        text-align: left;
        vertical-align: top;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-kv th {
        color: #c2c7d0;
        font-weight: 600;
        width: 35%;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-kv td {
        color: #e2e8f0;
        font-family: 'Consolas', 'Monaco', monospace;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-kv tr:first-child th,
    .corp-wallet-wrapper.diagnostic-page .diag-kv tr:first-child td { border-top: 0; }

    /* Wide multi-column data tables (Donation Audit). Distinct from
       .diag-kv which is a 2-column key-value pattern with a fixed
       35%-width label column. */
    .corp-wallet-wrapper.diagnostic-page .diag-data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-data-table thead th {
        padding: 8px 10px;
        background: rgba(15, 23, 42, 0.55);
        color: #cbd5e1;
        font-weight: 600;
        text-align: left;
        border-bottom: 1px solid #353b46;
        white-space: nowrap;
        vertical-align: bottom;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-data-table tbody td {
        padding: 6px 10px;
        border-top: 1px solid #2d3340;
        color: #e2e8f0;
        vertical-align: top;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-data-table tbody tr:hover td {
        background: rgba(102, 126, 234, 0.06);
    }
    .corp-wallet-wrapper.diagnostic-page .diag-data-table .text-right { text-align: right; }
    .corp-wallet-wrapper.diagnostic-page .diag-data-table .nowrap { white-space: nowrap; }
    .corp-wallet-wrapper.diagnostic-page .diag-data-table .truncate {
        max-width: 320px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Master Test summary tiles. */
    .corp-wallet-wrapper.diagnostic-page .diag-summary-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 0.75rem;
        margin-bottom: 1.25rem;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-summary-tile {
        background: #2a2f3a;
        border: 1px solid #454d55;
        border-radius: 8px;
        padding: 0.85rem 1rem;
        text-align: center;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-summary-tile .num {
        font-size: 1.6rem;
        font-weight: 700;
        color: #e2e8f0;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-summary-tile .lbl {
        font-size: 0.78rem;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-summary-tile.passed .num { color: #28a745; }
    .corp-wallet-wrapper.diagnostic-page .diag-summary-tile.warned .num { color: #f39c12; }
    .corp-wallet-wrapper.diagnostic-page .diag-summary-tile.failed .num { color: #e74c3c; }
    .corp-wallet-wrapper.diagnostic-page .diag-summary-tile.info .num   { color: #3498db; }

    /* Refresh + form controls. */
    .corp-wallet-wrapper.diagnostic-page .diag-header-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        margin-bottom: 0.5rem;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-header-bar h2 {
        margin: 0;
        color: #e2e8f0;
        font-size: 1.3rem;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-header-bar .meta {
        color: #94a3b8;
        font-size: 0.85rem;
    }

    /* Wallet Trace form. */
    .corp-wallet-wrapper.diagnostic-page .diag-trace-form {
        display: flex;
        gap: 0.5rem;
        align-items: end;
        flex-wrap: wrap;
        margin-bottom: 1.25rem;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-trace-form label {
        display: block;
        font-size: 0.85rem;
        color: #94a3b8;
        margin-bottom: 0.25rem;
    }
    .corp-wallet-wrapper.diagnostic-page .diag-trace-form input[type="number"] {
        background: #1f242c;
        border: 1px solid #454d55;
        color: #e2e8f0;
        padding: 0.5rem 0.75rem;
        border-radius: 5px;
        font-family: 'Consolas', 'Monaco', monospace;
        width: 220px;
    }
</style>
@endpush

@section('content')
<div class="corp-wallet-wrapper diagnostic-page">

    <div class="diag-header-bar">
        <div>
            <h2><i class="fas fa-stethoscope"></i> Diagnostics</h2>
            <div class="meta">Admin-only. Not linked from the sidebar - intended for troubleshooting and smoke-testing.</div>
        </div>
    </div>

    {{-- Diagnostic Summary banner: aggregate counts across every Health
         Check row. Border-left colour reflects the worst status found,
         so a glance at the banner tells the operator whether anything
         needs attention. Mirrors SM's pattern. --}}
    @php
        $hcPass = $hcWarn = $hcFail = $hcInfo = 0;
        foreach ($healthChecks as $check) {
            $st = strtolower($check['status'] ?? '');
            if ($st === 'pass') { $hcPass++; }
            elseif ($st === 'warn') { $hcWarn++; }
            elseif ($st === 'fail') { $hcFail++; }
            elseif ($st === 'info') { $hcInfo++; }
        }
        $hcTotal = count($healthChecks);
        if ($hcFail > 0) {
            $bannerStatus = 'error'; $bannerLabel = 'Some checks failed'; $bannerBadge = 'FAIL';
        } elseif ($hcWarn > 0) {
            $bannerStatus = 'warn'; $bannerLabel = 'Some checks returned warnings'; $bannerBadge = 'WARN';
        } else {
            $bannerStatus = 'ok'; $bannerLabel = 'All checks passing'; $bannerBadge = 'OK';
        }
    @endphp
    <div class="diag-summary {{ $bannerStatus }}">
        <div>
            <h3 class="diag-summary-title">Diagnostic Summary</h3>
            <p class="diag-summary-meta">
                <strong>{{ $bannerLabel }}</strong>
                <span class="sep">—</span>
                OK: <strong>{{ $hcPass }}</strong>
                <span class="sep">·</span>
                Warnings: <strong>{{ $hcWarn }}</strong>
                <span class="sep">·</span>
                Errors: <strong>{{ $hcFail }}</strong>
                <span class="sep">·</span>
                Informational: <strong>{{ $hcInfo }}</strong>
                ({{ $hcTotal }} total)
            </p>
            <p class="diag-summary-meta mt-1" style="font-size:0.82rem; color:#94a3b8;">
                Heavy sections (System Validation / Settings Health / Data Integrity) are cached for 30-60s. Reload to refresh light checks; <em>Force refresh</em> recomputes everything live.
            </p>
        </div>
        <div class="diag-summary-actions">
            <a href="{{ route('corpwalletmanager.diagnostic') }}#{{ $activeTab }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-redo-alt"></i> Reload
            </a>
            <a href="{{ route('corpwalletmanager.diagnostic', ['refresh' => 1]) }}#{{ $activeTab }}" class="btn btn-sm btn-primary">
                <i class="fas fa-bolt"></i> Force refresh
            </a>
        </div>
    </div>

    {{-- Wrap tabs + content in card-dark so the page reads as a panel
         instead of plain text on the page background. Mirrors SM's
         pattern. --}}
    <div class="card card-dark">
        <div class="card-body">

    {{-- Tab nav --}}
    <ul class="diag-tabs">
        <li class="diag-tab {{ $activeTab === 'health-checks' ? 'active' : '' }}" data-target="health-checks">
            <i class="fas fa-heartbeat"></i> Health Checks
            <span class="diag-tab-count">{{ count($healthChecks) }}</span>
        </li>
        <li class="diag-tab {{ $activeTab === 'master-test' ? 'active' : '' }}" data-target="master-test">
            <i class="fas fa-vial"></i> Master Test
        </li>
        <li class="diag-tab {{ $activeTab === 'system-validation' ? 'active' : '' }}" data-target="system-validation">
            <i class="fas fa-shield-alt"></i> System Validation
        </li>
        <li class="diag-tab {{ $activeTab === 'settings-health' ? 'active' : '' }}" data-target="settings-health">
            <i class="fas fa-cog"></i> Settings Health
        </li>
        <li class="diag-tab {{ $activeTab === 'data-integrity' ? 'active' : '' }}" data-target="data-integrity">
            <i class="fas fa-database"></i> Data Integrity
        </li>
        <li class="diag-tab {{ $activeTab === 'wallet-trace' ? 'active' : '' }}" data-target="wallet-trace">
            <i class="fas fa-route"></i> Wallet Trace
        </li>
        <li class="diag-tab {{ $activeTab === 'donation-audit' ? 'active' : '' }}" data-target="donation-audit">
            <i class="fas fa-balance-scale-right"></i> Donation Audit
        </li>
        <li class="diag-tab {{ $activeTab === 'schedule-trace' ? 'active' : '' }}" data-target="schedule-trace">
            <i class="fas fa-clock"></i> Schedule Trace
        </li>
        <li class="diag-tab {{ $activeTab === 'notification-testing' ? 'active' : '' }}" data-target="notification-testing">
            <i class="fas fa-paper-plane"></i> Notification Testing
        </li>
    </ul>

    {{-- ==================== HEALTH CHECKS ==================== --}}
    <div class="diag-tab-pane {{ $activeTab === 'health-checks' ? 'active' : '' }}" id="health-checks">
        <div class="diag-tab-intro">
            <p><strong>What this tab does:</strong> a quick green/red read of every CWM subsystem - plugin tables, scheduled commands, webhooks, alert config, contribution cache, wallet journal data, and cross-plugin integrations.</p>
            <p><strong>When to use:</strong> first stop for any "is something broken?" question. Loads on every page visit; if a check shows <code>warn</code> or <code>fail</code> the detail column says what to do.</p>
            <p><strong>Heads up:</strong> results are cached for 30s. Click <code>Refresh checks</code> in the header to bust the cache and recompute live.</p>
        </div>

        <div class="diag-section">
            <div class="diag-section-header">Subsystem checks</div>
            <div class="diag-section-body">
                @foreach($healthChecks as $check)
                    <div class="diag-check">
                        <span class="diag-check-status {{ $check['status'] }}">{{ $check['status'] }}</span>
                        <span class="diag-check-label">{{ $check['label'] }}</span>
                        <span class="diag-check-detail">{{ $check['detail'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ==================== MASTER TEST ==================== --}}
    <div class="diag-tab-pane {{ $activeTab === 'master-test' ? 'active' : '' }}" id="master-test">
        <div class="diag-tab-intro">
            <p><strong>What this tab does:</strong> runs every Health Check plus a few extra smoke probes (classifier dry-run on the latest journal entry, last-report timestamp, large-transaction threshold preview against the last 7 days of activity).</p>
            <p><strong>When to use:</strong> after a release, after a config change, or when you want a single "are we OK?" verdict. The summary tiles at the top show the headcount of pass / warn / fail / info across all checks.</p>
            <p><strong>Heads up:</strong> results are cached for 60s. The threshold preview reads from <code>corporation_wallet_journals</code> which can be slow on very large installs.</p>
        </div>

        <div class="diag-summary-grid">
            <div class="diag-summary-tile">
                <div class="num">{{ $masterTest['summary']['total'] }}</div>
                <div class="lbl">Checks</div>
            </div>
            <div class="diag-summary-tile passed">
                <div class="num">{{ $masterTest['summary']['passed'] }}</div>
                <div class="lbl">Pass</div>
            </div>
            <div class="diag-summary-tile warned">
                <div class="num">{{ $masterTest['summary']['warned'] }}</div>
                <div class="lbl">Warn</div>
            </div>
            <div class="diag-summary-tile failed">
                <div class="num">{{ $masterTest['summary']['failed'] }}</div>
                <div class="lbl">Fail</div>
            </div>
            <div class="diag-summary-tile info">
                <div class="num">{{ $masterTest['summary']['info'] }}</div>
                <div class="lbl">Info</div>
            </div>
        </div>

        <div class="diag-section">
            <div class="diag-section-header">Smoke probes</div>
            <div class="diag-section-body">
                @foreach($masterTest['extras'] as $check)
                    <div class="diag-check">
                        <span class="diag-check-status {{ $check['status'] }}">{{ $check['status'] }}</span>
                        <span class="diag-check-label">{{ $check['label'] }}</span>
                        <span class="diag-check-detail">{{ $check['detail'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="diag-section">
            <div class="diag-section-header">All health checks (re-rendered for full report)</div>
            <div class="diag-section-body">
                @foreach($masterTest['checks'] as $check)
                    <div class="diag-check">
                        <span class="diag-check-status {{ $check['status'] }}">{{ $check['status'] }}</span>
                        <span class="diag-check-label">{{ $check['label'] }}</span>
                        <span class="diag-check-detail">{{ $check['detail'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ==================== SYSTEM VALIDATION ==================== --}}
    <div class="diag-tab-pane {{ $activeTab === 'system-validation' ? 'active' : '' }}" id="system-validation">
        <div class="diag-tab-intro">
            <p><strong>What this tab does:</strong> checks the coherence of CWM's configuration vs what's actually in the database - registered schedule expressions match the expected ones, permissions are assigned to roles, webhook URLs look like Discord URLs, job watermarks have been initialised.</p>
            <p><strong>When to use:</strong> after upgrading, after manually editing settings, or when a scheduled job appears to not be running.</p>
            <p><strong>Heads up:</strong> the "Permission assignment" check just counts permission rows tagged <code>corpwalletmanager.*</code>; it doesn't validate that the right roles have the right permissions.</p>
        </div>

        <div class="diag-section">
            <div class="diag-section-header">Validation checks</div>
            <div class="diag-section-body">
                @foreach($systemValidation as $check)
                    <div class="diag-check">
                        <span class="diag-check-status {{ $check['status'] }}">{{ $check['status'] }}</span>
                        <span class="diag-check-label">{{ $check['label'] }}</span>
                        <span class="diag-check-detail">{{ $check['detail'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Cross-plugin integration: detection rows for every sibling
             plugin in the suite (Manager Core, Mining Manager, HR Manager,
             Structure Manager, SeAT Broadcast, SeAT Connector). All rows
             carry `info` status because missing siblings are optional
             integrations, not CWM failures. --}}
        <div class="diag-section">
            <div class="diag-section-header">Cross-plugin integration</div>
            <div class="diag-section-body">
                @foreach($crossPluginChecks as $check)
                    <div class="diag-check">
                        <span class="diag-check-status {{ $check['status'] }}">{{ $check['status'] }}</span>
                        <span class="diag-check-label">{{ $check['label'] }}</span>
                        <span class="diag-check-detail">{{ $check['detail'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="diag-section">
            <div class="diag-section-header">Expected schedule</div>
            <div class="diag-section-body" style="padding: 0;">
                <table class="diag-kv">
                    <thead>
                        <tr>
                            <th style="width:55%; padding-left:18px;">Command</th>
                            <th>Cron expression</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($expectedSchedules as $cmd => $expr)
                            <tr>
                                <th style="padding-left:18px;">{{ $cmd }}</th>
                                <td>{{ $expr }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ==================== SETTINGS HEALTH ==================== --}}
    <div class="diag-tab-pane {{ $activeTab === 'settings-health' ? 'active' : '' }}" id="settings-health">
        <div class="diag-tab-intro">
            <p><strong>What this tab does:</strong> dumps every row in <code>corpwalletmanager_settings</code> with its current value, grouped by category (Display, Performance, Alerts, Contributions, Goals, Member view, Legacy pre-3.0 Discord, Other).</p>
            <p><strong>When to use:</strong> to confirm a setting actually persisted after saving, to inspect what the legacy pre-3.0 Discord rows look like after the v3 upgrade, or to spot suspicious / stale keys.</p>
            <p><strong>Heads up:</strong> webhook URLs are NOT shown here - they live in <code>corpwalletmanager_webhooks</code> now and are hidden from this dump on purpose. The pre-3.0 single <code>discord_webhook_url</code> setting row is left in place as dormant data after upgrade.</p>
        </div>

        @if(! empty($settingsHealth['rows']))
            @php
                $byCategory = collect($settingsHealth['rows'])->groupBy('category')->sortKeys();
            @endphp
            @foreach($byCategory as $cat => $rows)
                <div class="diag-section">
                    <div class="diag-section-header">{{ $cat }} ({{ count($rows) }})</div>
                    <div class="diag-section-body" style="padding: 0;">
                        <table class="diag-kv">
                            <thead>
                                <tr>
                                    <th style="padding-left:18px;">Key</th>
                                    <th>Value</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    <tr>
                                        <th style="padding-left:18px;">{{ $row['key'] }}</th>
                                        <td>{{ $row['value'] }}</td>
                                        <td style="color:#94a3b8;">{{ $row['updated_at'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
            <p style="color:#94a3b8; font-size:0.85rem;">{{ $settingsHealth['note'] }}</p>
        @else
            <div class="diag-section">
                <div class="diag-section-body" style="padding: 1.5rem;">
                    {{ $settingsHealth['note'] ?? 'No settings rows.' }}
                </div>
            </div>
        @endif
    </div>

    {{-- ==================== DATA INTEGRITY ==================== --}}
    <div class="diag-tab-pane {{ $activeTab === 'data-integrity' ? 'active' : '' }}" id="data-integrity">
        <div class="diag-tab-intro">
            <p><strong>What this tab does:</strong> row counts and shape checks for every CWM-owned table, plus an aggregate of webhook delivery success / failure and the count of corporations currently latched in the low-balance state.</p>
            <p><strong>When to use:</strong> to verify the contribution cache is populated after running the backfill, to confirm reports / predictions tables are growing as expected, or to spot a corporation stuck in low-balance state that should have recovered.</p>
            <p><strong>Heads up:</strong> the row counts and timestamps are cached for 60s. Click <code>Refresh checks</code> to recompute live.</p>
        </div>

        <div class="diag-section">
            <div class="diag-section-header">Table summaries</div>
            <div class="diag-section-body" style="padding: 0;">
                <table class="diag-kv">
                    <tbody>
                        @foreach($dataIntegrity as $block)
                            <tr>
                                <th style="padding-left:18px;">{{ $block['label'] }}</th>
                                <td>{{ $block['detail'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ===== v3.0.0: Schedule Status ===== --}}
        <div class="diag-section">
            <div class="diag-section-header">Schedule Status</div>
            <div class="diag-section-body" style="padding: 0;">
                @if(($scheduleStatus['state'] ?? '') === 'missing')
                    <div style="padding: 1rem 1.2rem; color:#fbbf24;">
                        <code>corpwalletmanager_report_schedules</code> table missing. Restart the SeAT stack to run pending migrations.
                    </div>
                @elseif(($scheduleStatus['state'] ?? '') === 'empty')
                    <div style="padding: 1rem 1.2rem; color:#94a3b8;">
                        No schedules configured. Use Settings (Scheduled Reports) to add per-corp + per-cadence schedules.
                    </div>
                @else
                    <table class="diag-data-table">
                        <thead>
                            <tr>
                                <th style="padding-left:18px;" class="nowrap">Status</th>
                                <th>Corporation</th>
                                <th class="nowrap">Cadence</th>
                                <th class="nowrap">Next run (UTC)</th>
                                <th class="nowrap">Last run (UTC)</th>
                                <th class="nowrap">Last status</th>
                                <th class="nowrap">Enabled</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($scheduleStatus['rows'] as $row)
                                <tr>
                                    <td style="padding-left:18px;" class="nowrap">
                                        @if($row['status'] === 'ok')
                                            <span style="color:#34d399;" title="Enabled and last run succeeded (or no run yet)"><i class="fas fa-check-circle"></i></span>
                                        @elseif($row['status'] === 'warn')
                                            <span style="color:#fbbf24;" title="Enabled but last run failed"><i class="fas fa-exclamation-triangle"></i></span>
                                        @else
                                            <span style="color:#94a3b8;" title="Disabled"><i class="fas fa-times-circle"></i></span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($row['corp_name'])
                                            {{ $row['corp_name'] }} <span class="text-muted">[{{ $row['corporation_id'] }}]</span>
                                        @else
                                            Corporation {{ $row['corporation_id'] }}
                                        @endif
                                    </td>
                                    <td class="nowrap"><code>{{ $row['report_type'] }}</code></td>
                                    <td class="nowrap">{{ $row['next_run_at'] ?? '—' }}</td>
                                    <td class="nowrap">{{ $row['last_run_at'] ?? 'never' }}</td>
                                    <td class="nowrap">
                                        @if($row['last_status'] === 'ok')
                                            <span style="color:#34d399;">ok</span>
                                        @elseif($row['last_status'] === 'failed')
                                            <span style="color:#f87171;" title="{{ $row['last_error'] }}">failed</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="nowrap">{{ $row['enabled'] ? 'yes' : 'no' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- ===== v3.0.0: Personal Wallet Aggregator Status ===== --}}
        <div class="diag-section">
            <div class="diag-section-header">Personal Wallet Aggregator Status</div>
            <div class="diag-section-body" style="padding: 0;">
                @if(($personalWalletStatus['state'] ?? '') === 'missing')
                    <div style="padding: 1rem 1.2rem; color:#fbbf24;">
                        <code>corpwalletmanager_personal_wallet_aggregates</code> table missing. Restart the SeAT stack to run pending migrations.
                    </div>
                @else
                    <table class="diag-kv">
                        <tbody>
                            <tr>
                                <th style="padding-left:18px;">Total aggregate rows</th>
                                <td>{{ number_format((int) ($personalWalletStatus['total_rows'] ?? 0)) }} row(s) across all character+period combinations</td>
                            </tr>
                            <tr>
                                <th style="padding-left:18px;">Last refresh</th>
                                <td>
                                    @if(! empty($personalWalletStatus['max_updated']))
                                        {{ $personalWalletStatus['max_updated'] }} ({{ \Carbon\Carbon::parse($personalWalletStatus['max_updated'])->diffForHumans() }})
                                    @else
                                        never (hourly job has not run yet)
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th style="padding-left:18px;">Current period</th>
                                <td><code>{{ $personalWalletStatus['period'] ?? '—' }}</code></td>
                            </tr>
                            <tr>
                                <th style="padding-left:18px;">Characters without an aggregate this period</th>
                                <td>
                                    @if(($personalWalletStatus['state'] ?? '') === 'partial')
                                        <span style="color:#fbbf24;">gap detection unavailable: {{ $personalWalletStatus['gap_error'] ?? 'query failed' }}</span>
                                    @elseif((int) ($personalWalletStatus['gap_count'] ?? 0) === 0)
                                        <span style="color:#34d399;">0 (all known player characters have an aggregate row for the current period)</span>
                                    @else
                                        <span style="color:#fbbf24;">{{ number_format((int) $personalWalletStatus['gap_count']) }} (hourly cron may be behind, or a recent character has not been backfilled yet)</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- ===== v3.0.0: Anomaly State ===== --}}
        <div class="diag-section">
            <div class="diag-section-header">Anomaly State</div>
            <div class="diag-section-body" style="padding: 0;">
                @if(($anomalyState['state'] ?? '') === 'missing')
                    <div style="padding: 1rem 1.2rem; color:#fbbf24;">
                        <code>corpwalletmanager_anomaly_state</code> table missing. Restart the SeAT stack to run pending migrations.
                    </div>
                @elseif(($anomalyState['state'] ?? '') === 'empty')
                    <div style="padding: 1rem 1.2rem; color:#94a3b8;">
                        No anomaly state recorded. Either the contribution-drop detector has never fired, or every prior alert has cleared on member recovery.
                    </div>
                @else
                    <table class="diag-data-table">
                        <thead>
                            <tr>
                                <th style="padding-left:18px;">Corporation</th>
                                <th>Character</th>
                                <th class="nowrap">Alert kind</th>
                                <th class="nowrap">Latched</th>
                                <th class="text-right nowrap">Prior 3-mo avg</th>
                                <th class="text-right nowrap">Recent 3-mo avg</th>
                                <th class="nowrap">Notified (UTC)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($anomalyState['rows'] as $row)
                                <tr>
                                    <td style="padding-left:18px;">
                                        @if($row['corp_name'])
                                            {{ $row['corp_name'] }} <span class="text-muted">[{{ $row['corporation_id'] }}]</span>
                                        @else
                                            Corporation {{ $row['corporation_id'] }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($row['character_name'])
                                            {{ $row['character_name'] }} <span class="text-muted">[{{ $row['character_id'] }}]</span>
                                        @else
                                            Character {{ $row['character_id'] }}
                                        @endif
                                    </td>
                                    <td class="nowrap"><code>{{ $row['alert_kind'] }}</code></td>
                                    <td class="nowrap">
                                        @if($row['latched'])
                                            <span style="color:#fbbf24;" title="Alert is currently open (member has not recovered above 50% of prior)">latched</span>
                                        @else
                                            <span class="text-muted">cleared</span>
                                        @endif
                                    </td>
                                    <td class="text-right nowrap">{{ number_format($row['contribution_drop_prior_avg'], 2) }} ISK</td>
                                    <td class="text-right nowrap">{{ number_format($row['contribution_drop_recent_avg'], 2) }} ISK</td>
                                    <td class="nowrap">{{ $row['contribution_drop_notified_at'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    {{-- ==================== WALLET TRACE ==================== --}}
    <div class="diag-tab-pane {{ $activeTab === 'wallet-trace' ? 'active' : '' }}" id="wallet-trace">
        <div class="diag-tab-intro">
            <p><strong>What this tab does:</strong> takes a single corp wallet journal entry and walks it through the CWM pipeline - classification, contribution-bucket assignment, large-transaction alert threshold, low-balance state for the corp, matching webhooks, and which (if any) Manager Core events would be published.</p>
            <p><strong>When to use:</strong> when an operator says "why didn't this transaction trigger an alert?" or "is this donation being counted as tax?". The trace shows exactly what CWM does with that one row, top to bottom.</p>
            <p><strong>Heads up:</strong> input either the CCP journal <code>id</code> (the long number from the wallet journal export) or the SeAT-internal <code>internal_id</code> (the auto-increment surrogate). Either works; the form remembers your last input.</p>
        </div>

        <form method="GET" action="{{ route('corpwalletmanager.diagnostic') }}" class="diag-trace-form">
            <input type="hidden" name="diag_tab" value="wallet-trace">
            <div>
                <label for="trace_journal_id">CCP journal id (the long number)</label>
                <input type="number" id="trace_journal_id" name="trace_journal_id" value="{{ $traceJournalId ?: '' }}" placeholder="e.g. 24500000000">
            </div>
            <div>
                <label for="trace_internal_id">or SeAT internal_id</label>
                <input type="number" id="trace_internal_id" name="trace_internal_id" value="{{ $traceInternalId ?: '' }}" placeholder="e.g. 12345">
            </div>
            <div>
                <button type="submit" class="btn btn-cwm-primary"><i class="fas fa-route"></i> Trace</button>
            </div>
        </form>

        @if($walletTrace['state'] === 'idle')
            <div class="diag-section">
                <div class="diag-section-body" style="padding: 1.5rem; color:#94a3b8;">
                    Enter a journal entry id above and click Trace to walk it through the classification, alert, and event-publish pipeline.
                </div>
            </div>
        @elseif($walletTrace['state'] === 'not_found')
            <div class="diag-section">
                <div class="diag-section-body" style="padding: 1.5rem; color:#e57373;">
                    No matching journal entry found. Double-check the id - the CCP <code>id</code> column and the SeAT <code>internal_id</code> are different things.
                </div>
            </div>
        @else
            @php $row = $walletTrace['row']; @endphp

            @if(! empty($walletTrace['is_internal_transfer']))
                <div class="diag-section">
                    <div class="diag-section-body" style="padding: 1.5rem; background:#3a2a14; color:#fde68a; border-left: 3px solid #f59e0b;">
                        <strong>This row is an inter-division transfer.</strong>
                        Both <code>first_party_id</code> and <code>second_party_id</code> equal the corporation id, which means this is ISK moved between divisions of the same corp (not real income or expense). CWM filters these out of: contribution classification (no Top Contributors entry), income/expense/breakdown queries in scheduled reports, and the large-transaction alert scan. The sister row in the other division logs the opposite sign and they net to zero in balance.
                    </div>
                </div>
            @endif

            <div class="diag-section">
                <div class="diag-section-header">1. Raw journal row</div>
                <div class="diag-section-body" style="padding: 0;">
                    <table class="diag-kv">
                        <tbody>
                            <tr><th style="padding-left:18px;">CCP journal id</th><td>{{ $row['id'] }}</td></tr>
                            <tr><th style="padding-left:18px;">SeAT internal_id</th><td>{{ $row['internal_id'] }}</td></tr>
                            @php
                                $partyNames = $walletTrace['party_names'] ?? [];
                                $fmtParty = function ($id) use ($partyNames) {
                                    if (! $id) return 'NULL';
                                    $info = $partyNames[(int) $id] ?? null;
                                    if (! $info || ($info['name'] ?? 'Unknown') === 'Unknown') {
                                        return $id;
                                    }
                                    return $info['name'] . ' [' . $id . ']';
                                };
                            @endphp
                            <tr><th style="padding-left:18px;">Corporation</th><td>{{ $fmtParty($row['corporation_id']) }}</td></tr>
                            <tr><th style="padding-left:18px;">Division</th><td>{{ $row['division'] }}</td></tr>
                            <tr><th style="padding-left:18px;">Date</th><td>{{ $row['date'] }}</td></tr>
                            <tr><th style="padding-left:18px;">Ref type</th><td>{{ $row['ref_type'] }}</td></tr>
                            <tr><th style="padding-left:18px;">Amount</th><td>{{ number_format((float) $row['amount'], 2) }} ISK</td></tr>
                            <tr><th style="padding-left:18px;">First party id</th><td>{{ $fmtParty($row['first_party_id']) }}</td></tr>
                            <tr><th style="padding-left:18px;">Second party id</th><td>{{ $fmtParty($row['second_party_id']) }}</td></tr>
                            <tr><th style="padding-left:18px;">Description</th><td>{{ $row['description'] }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="diag-section">
                <div class="diag-section-header">2. Classification (ContributionService)</div>
                <div class="diag-section-body" style="padding: 0;">
                    <table class="diag-kv">
                        <tbody>
                            @if($walletTrace['classification'] === null)
                                @if(! empty($walletTrace['is_internal_transfer']))
                                    <tr><th style="padding-left:18px;">Bucket</th><td><em>none (inter-division transfer; deliberately skipped)</em></td></tr>
                                @else
                                    <tr><th style="padding-left:18px;">Bucket</th><td><em>none (unattributable by ref_type or missing party id)</em></td></tr>
                                @endif
                                <tr><th style="padding-left:18px;">Effect on cache</th><td>This row is skipped by the contribution cache and does not appear in Top Contributors.</td></tr>
                            @else
                                <tr><th style="padding-left:18px;">Character id</th><td>{{ $fmtParty($walletTrace['classification']['character_id']) }}</td></tr>
                                <tr><th style="padding-left:18px;">Bucket</th><td><strong>{{ $walletTrace['classification']['bucket'] }}</strong></td></tr>
                                <tr><th style="padding-left:18px;">Effect on cache</th><td>Increments <code>{{ $walletTrace['classification']['bucket'] }}_amount</code> by {{ number_format(abs((float) $row['amount']), 2) }} on row (corp {{ $row['corporation_id'] }}, character {{ $walletTrace['classification']['character_id'] }}, period {{ $walletTrace['period'] }}).</td></tr>
                            @endif
                            <tr><th style="padding-left:18px;">MM tax-code match</th><td>{{ $walletTrace['tax_code_match'] ?? ($walletTrace['mm_installed'] ? 'No match (this entry would be voluntary, not tax)' : 'MM not installed - tax/voluntary split unavailable') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="diag-section">
                <div class="diag-section-header">3. Large-transaction alert</div>
                <div class="diag-section-body" style="padding: 0;">
                    <table class="diag-kv">
                        <tbody>
                            <tr><th style="padding-left:18px;">Threshold</th><td>{{ $walletTrace['large_threshold'] > 0 ? number_format($walletTrace['large_threshold'], 0) . ' ISK' : 'Disabled (0)' }}</td></tr>
                            <tr><th style="padding-left:18px;">|Amount|</th><td>{{ number_format(abs((float) $row['amount']), 2) }} ISK</td></tr>
                            <tr><th style="padding-left:18px;">Would trigger</th><td>
                                @if(! empty($walletTrace['is_internal_transfer']))
                                    No - inter-division transfers are filtered out of the alert scan
                                @elseif($walletTrace['would_large_alert'])
                                    YES - this row meets the threshold
                                @else
                                    No
                                @endif
                            </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="diag-section">
                <div class="diag-section-header">4. Low-balance state for corp {{ $row['corporation_id'] }}</div>
                <div class="diag-section-body" style="padding: 0;">
                    <table class="diag-kv">
                        <tbody>
                            @if($walletTrace['corp_balance_low'])
                                <tr><th style="padding-left:18px;">Threshold</th><td>{{ number_format($walletTrace['corp_balance_low']['threshold'], 0) }} ISK</td></tr>
                                <tr><th style="padding-left:18px;">Current balance (sum of divisions)</th><td>{{ number_format($walletTrace['corp_balance_low']['balance'], 2) }} ISK</td></tr>
                                <tr><th style="padding-left:18px;">Currently low?</th><td>{{ $walletTrace['corp_balance_low']['is_low'] ? 'YES' : 'No' }}</td></tr>
                            @else
                                <tr><th style="padding-left:18px;">Status</th><td>Low-balance check disabled (threshold = 0).</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="diag-section">
                <div class="diag-section-header">5. Matching webhooks (large-transfer subscribers for this corp)</div>
                <div class="diag-section-body" style="padding: 0;">
                    @if(empty($walletTrace['matching_webhooks']))
                        <div style="padding: 0.85rem 1.1rem; color:#94a3b8;">No enabled webhooks subscribed to <code>notify_large_transfer</code> for this corporation (or for global delivery).</div>
                    @else
                        <table class="diag-kv">
                            <thead>
                                <tr>
                                    <th style="padding-left:18px;">Webhook</th>
                                    <th>Scope</th>
                                    <th>Role mention</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($walletTrace['matching_webhooks'] as $wh)
                                    <tr>
                                        <th style="padding-left:18px;">{{ $wh->name }} (id {{ $wh->id }})</th>
                                        <td>{{ $wh->corporation_id ? 'Corp ' . $wh->corporation_id : 'Global' }}</td>
                                        <td>{{ $wh->discord_role_id ?: '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <div class="diag-section">
                <div class="diag-section-header">6. Manager Core events that would publish</div>
                <div class="diag-section-body" style="padding: 0;">
                    @if(empty($walletTrace['published_events']))
                        <div style="padding: 0.85rem 1.1rem; color:#94a3b8;">No events would publish for this row - neither the large-transaction nor the low-balance condition is met.</div>
                    @else
                        <table class="diag-kv">
                            <thead>
                                <tr>
                                    <th style="padding-left:18px;">Topic</th>
                                    <th>Guard</th>
                                    <th>MC installed?</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($walletTrace['published_events'] as $evt)
                                    <tr>
                                        <th style="padding-left:18px;"><code>{{ $evt['topic'] }}</code></th>
                                        <td><code>{{ $evt['guarded_by'] }}</code></td>
                                        <td>{{ $evt['mc_installed'] ? 'Yes - publish would fire' : 'No - publish skips cleanly' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- ==================== DONATION AUDIT ==================== --}}
    <div class="diag-tab-pane {{ $activeTab === 'donation-audit' ? 'active' : '' }}" id="donation-audit">
        <div class="diag-tab-intro">
            <p><strong>What this tab does:</strong> lists every <code>player_donation</code> journal row for a corp + month with the classifier's decision and the MM tax-code match side-by-side. Complements Wallet Trace (which walks one row) by showing the whole month at once so you can verify classification in bulk.</p>
            <p><strong>When to use:</strong> when Top Contributors shows unexpectedly large voluntary donations and you want to confirm whether the classifier is right (genuinely voluntary), or wrong (the description should have matched an MM tax code but didn't). Suspect rows (sent to voluntary while the description mentions "tax" / "mining") are highlighted so they jump out.</p>
            <p><strong>Heads up:</strong> queries the journal directly with no caching; expect a brief delay on large months. Capped at the top 500 donations by amount for the period.</p>
        </div>

        <form method="GET" action="{{ route('corpwalletmanager.diagnostic') }}" class="diag-trace-form">
            <input type="hidden" name="diag_tab" value="donation-audit">
            <div class="form-row align-items-end">
                <div class="form-group col-md-3">
                    <label for="audit_corp_id">Corporation id</label>
                    <input type="number" class="form-control" id="audit_corp_id" name="audit_corp_id" value="{{ $auditCorpId ?: '' }}" placeholder="e.g. 144534752">
                </div>
                <div class="form-group col-md-3">
                    <label for="audit_period">Period (YYYY-MM)</label>
                    <input type="text" class="form-control" id="audit_period" name="audit_period" value="{{ $auditPeriod }}" pattern="^\d{4}-\d{2}$" placeholder="e.g. 2026-05">
                </div>
                <div class="form-group col-md-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Run Audit
                    </button>
                </div>
            </div>
        </form>

        @php $audit = $donationAudit ?? ['state' => 'idle']; @endphp

        @if($audit['state'] === 'idle')
            <div class="diag-section">
                <div class="diag-section-body" style="padding: 1.5rem; color:#94a3b8;">
                    Enter a corporation id and a period and click <em>Run Audit</em> to inspect every <code>player_donation</code> row for that month.
                </div>
            </div>
        @elseif($audit['state'] === 'no_corp')
            <div class="diag-section">
                <div class="diag-section-body" style="padding: 1.5rem; color:#fbbf24;">
                    Enter a corporation id above. (Find it on Settings &rarr; General &rarr; Selected Corporation, or check Top Contributors which shows the selected corp at the top.)
                </div>
            </div>
        @elseif($audit['state'] === 'no_rows')
            <div class="diag-section">
                <div class="diag-section-body" style="padding: 1.5rem; color:#94a3b8;">
                    No <code>player_donation</code> rows for corp <strong>{{ $audit['corporation_id'] }}</strong> in period <strong>{{ $audit['period'] }}</strong>. Either there were no donations that month or the corp / period is wrong.
                </div>
            </div>
        @else
            <div class="diag-section">
                <div class="diag-section-header">Summary for {{ $audit['period'] }}</div>
                <div class="diag-section-body" style="padding: 0;">
                    <table class="diag-kv">
                        <tbody>
                            <tr><th style="padding-left:18px;">Mining Manager installed</th><td>{{ $audit['mm_installed'] ? 'Yes - donations split tax_payment vs voluntary' : 'No - all donations land in donation_voluntary (this is correct)' }}</td></tr>
                            <tr><th style="padding-left:18px;">MM-linked rows</th>
                                <td>
                                    @if($audit['mm_installed'])
                                        <strong style="color:#34d399;">{{ $audit['totals']['count_mm_linked'] }}</strong> of {{ count($audit['entries']) }} rows have <code>mining_taxes.transaction_id</code> pointing at them - these are authoritatively tax_payment regardless of description text.
                                    @else
                                        &mdash; (Mining Manager not installed)
                                    @endif
                                </td></tr>
                            <tr><th style="padding-left:18px;">tax_payment total</th><td>{{ number_format($audit['totals']['tax_payment'], 2) }} ISK across {{ $audit['totals']['count_tax'] }} rows</td></tr>
                            <tr><th style="padding-left:18px;">donation_voluntary total</th><td>{{ number_format($audit['totals']['donation_voluntary'], 2) }} ISK across {{ $audit['totals']['count_voluntary'] }} rows</td></tr>
                            <tr><th style="padding-left:18px;">Unattributed (donor &lt; 90M / NPC)</th><td>{{ number_format($audit['totals']['unattributed'], 2) }} ISK across {{ $audit['totals']['count_unattributed'] }} rows</td></tr>
                            <tr><th style="padding-left:18px;">Suspect rows (voluntary but description hints tax)</th>
                                <td>
                                    @if($audit['totals']['count_suspect'] > 0)
                                        <strong style="color:#fbbf24;">{{ $audit['totals']['count_suspect'] }}</strong> &mdash; highlighted in the table below for review. These are NOT MM-linked, so the only signal that they're tax payments is the description text.
                                    @else
                                        0 &mdash; nothing flagged for review.
                                    @endif
                                </td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="diag-section">
                <div class="diag-section-header">Per-row detail (top 500 by amount)</div>
                <div class="diag-section-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="diag-data-table">
                            <thead>
                                <tr>
                                    <th class="nowrap">Journal id</th>
                                    <th class="nowrap">Date</th>
                                    <th>Donor</th>
                                    <th class="text-right nowrap">Amount</th>
                                    <th>Description</th>
                                    <th title="Optional message the donating player typed (CCP wallet journal reason field)">Reason</th>
                                    <th class="nowrap">MM Linked</th>
                                    <th class="nowrap">MM tax code</th>
                                    <th class="nowrap">Bucket</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($audit['entries'] as $e)
                                    <tr @if($e['suspect']) style="background: rgba(251, 191, 36, 0.12);" @endif>
                                        <td class="nowrap">
                                            <a href="{{ route('corpwalletmanager.diagnostic') }}?diag_tab=wallet-trace&trace_journal_id={{ $e['journal_id'] }}" title="Open this row in Wallet Trace">{{ $e['journal_id'] }}</a>
                                        </td>
                                        <td class="nowrap">{{ substr($e['date'], 0, 10) }}</td>
                                        <td class="nowrap">
                                            @if($e['donor_id'])
                                                {{ $e['donor_name'] ?: 'Unknown' }} <span class="text-muted">[{{ $e['donor_id'] }}]</span>
                                            @else
                                                <em>none</em>
                                            @endif
                                        </td>
                                        <td class="text-right nowrap">{{ number_format($e['amount'], 2) }}</td>
                                        <td class="truncate" title="{{ $e['description'] }}">{{ $e['description'] ?: '—' }}</td>
                                        <td class="truncate" style="max-width: 200px;" title="{{ $e['reason'] }}">
                                            @if(trim($e['reason']) !== '')
                                                {{ $e['reason'] }}
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="nowrap">
                                            @if($e['mm_linked'])
                                                <span style="color:#34d399;" title="mining_taxes.transaction_id matches this journal id"><i class="fas fa-check"></i> linked</span>
                                            @elseif($audit['mm_installed'])
                                                <span class="text-muted">&mdash;</span>
                                            @else
                                                <span class="text-muted">n/a</span>
                                            @endif
                                        </td>
                                        <td class="nowrap">
                                            @if($e['tax_code'])
                                                <code>{{ $e['tax_code'] }}</code>
                                            @elseif($audit['mm_installed'])
                                                <span class="text-muted">no match</span>
                                            @else
                                                <span class="text-muted">MM not installed</span>
                                            @endif
                                        </td>
                                        <td class="nowrap">
                                            @if($e['bucket'] === 'tax_payment')
                                                <span style="color:#34d399;">tax_payment</span>
                                                @if($e['mm_linked'])
                                                    <i class="fas fa-link ml-1" style="color:#34d399;" title="Classified via MM transaction link"></i>
                                                @endif
                                            @elseif($e['bucket'] === 'donation_voluntary')
                                                <span style="color:#a5b4fc;">donation_voluntary</span>
                                                @if($e['suspect'])
                                                    <i class="fas fa-exclamation-triangle ml-1" style="color:#fbbf24;" title="Description hints at tax but no MM link and no MM code matched"></i>
                                                @endif
                                            @else
                                                <em>unattributed</em>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ==================== SCHEDULE TRACE ==================== --}}
    <div class="diag-tab-pane {{ $activeTab === 'schedule-trace' ? 'active' : '' }}" id="schedule-trace">
        <div class="diag-tab-intro">
            <p><strong>What this tab does:</strong> inspects a single (corporation, report type) schedule entry through the dispatcher pipeline. Shows the cron-resolved next run, the most recent dispatch outcome, and which webhooks would deliver the resulting report.</p>
            <p><strong>When to use:</strong> when "I added a schedule but it never fires" or "I'm not sure which webhook will get this report". The trace replicates <code>DispatchScheduledReportsCommand</code>'s date-window math so the operator can confirm exactly what period the next firing will cover.</p>
            <p><strong>Heads up:</strong> this is an operator-driven query and bypasses the 60s cache, so fresh data shows up immediately after a config change. The dispatcher cron itself runs every 5 minutes.</p>
        </div>

        <form method="GET" action="{{ route('corpwalletmanager.diagnostic') }}" class="diag-trace-form">
            <input type="hidden" name="diag_tab" value="schedule-trace">
            <div class="form-row align-items-end">
                <div class="form-group col-md-4">
                    <label for="schedule_trace_corp_id">Corporation</label>
                    <select class="form-control" id="schedule_trace_corp_id" name="schedule_trace_corp_id">
                        <option value="0">Select a corporation</option>
                        @foreach($corporations as $corp)
                            <option value="{{ $corp->corporation_id }}" {{ (int) $scheduleTraceCorpId === (int) $corp->corporation_id ? 'selected' : '' }}>
                                {{ $corp->name }} ({{ $corp->corporation_id }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="schedule_trace_type">Cadence</label>
                    <select class="form-control" id="schedule_trace_type" name="schedule_trace_type">
                        <option value="">Select a cadence</option>
                        @foreach(['daily', 'weekly', 'monthly', 'quarterly', 'annual'] as $cadence)
                            <option value="{{ $cadence }}" {{ $scheduleTraceType === $cadence ? 'selected' : '' }}>{{ ucfirst($cadence) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-clock"></i> Trace
                    </button>
                </div>
            </div>
        </form>

        @php $trace = $scheduleTrace ?? ['state' => 'idle']; @endphp

        @if($trace['state'] === 'idle')
            <div class="diag-section">
                <div class="diag-section-body" style="padding: 1.5rem; color:#94a3b8;">
                    Pick a corporation and a cadence above, then click <em>Trace</em> to walk that schedule entry through the dispatcher pipeline.
                </div>
            </div>
        @else
            <div class="diag-section">
                <div class="diag-section-header">
                    Trace target:
                    @if($trace['corp_name'])
                        {{ $trace['corp_name'] }} <span class="text-muted">[{{ $trace['corporation_id'] }}]</span>
                    @else
                        Corporation {{ $trace['corporation_id'] }}
                    @endif
                    &nbsp;/&nbsp;<code>{{ $trace['report_type'] }}</code>
                </div>
                <div class="diag-section-body" style="padding: 0;">
                    @if(empty($trace['schedule']))
                        <div style="padding: 1.5rem; color:#fbbf24; background:#3a2a14; border-left: 3px solid #f59e0b;">
                            <strong>No schedule configured for this corp + cadence.</strong>
                            The dispatcher cron will not fire for this combination. Add a schedule on Settings (Scheduled Reports).
                        </div>
                    @else
                        @php $sch = $trace['schedule']; @endphp
                        <table class="diag-kv">
                            <tbody>
                                <tr><th style="padding-left:18px;">Schedule id</th><td>{{ $sch['id'] }}</td></tr>
                                <tr><th style="padding-left:18px;">Enabled</th><td>{{ $sch['enabled'] ? 'yes' : 'no (dispatcher will skip)' }}</td></tr>
                                <tr><th style="padding-left:18px;">Time of day (UTC)</th><td>{{ sprintf('%02d:%02d', (int) $sch['hour'], (int) $sch['minute']) }}</td></tr>
                                @if(strtolower((string) $sch['report_type']) === 'weekly')
                                    <tr><th style="padding-left:18px;">Day of week</th><td>{{ $sch['day_of_week'] ?? '—' }} (1=Mon, 7=Sun)</td></tr>
                                @elseif(in_array(strtolower((string) $sch['report_type']), ['monthly', 'quarterly']))
                                    <tr><th style="padding-left:18px;">Day of month</th><td>{{ $sch['day_of_month'] ?? '—' }}</td></tr>
                                @elseif(strtolower((string) $sch['report_type']) === 'annual')
                                    <tr><th style="padding-left:18px;">Month of year</th><td>{{ $sch['month_of_year'] ?? '—' }}</td></tr>
                                    <tr><th style="padding-left:18px;">Day of month</th><td>{{ $sch['day_of_month'] ?? '—' }}</td></tr>
                                @endif
                                <tr><th style="padding-left:18px;">Last run at</th><td>{{ $sch['last_run_at'] ?? 'never' }}</td></tr>
                                <tr><th style="padding-left:18px;">Last status</th>
                                    <td>
                                        @if($sch['last_status'] === 'ok')
                                            <span style="color:#34d399;">ok</span>
                                        @elseif($sch['last_status'] === 'failed')
                                            <span style="color:#f87171;">failed</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                                @if(! empty($sch['last_error']))
                                    <tr><th style="padding-left:18px;">Last error</th><td><code>{{ $sch['last_error'] }}</code></td></tr>
                                @endif
                                <tr><th style="padding-left:18px;">Next run at</th><td>{{ $sch['next_run_at'] ?? 'unscheduled (will fire on next dispatcher pass)' }}</td></tr>
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <div class="diag-section">
                <div class="diag-section-header">
                    Dispatcher status
                    @php $dst = $trace['dispatcher']['status'] ?? 'unknown'; @endphp
                    <span class="diag-badge {{ $dst === 'ok' ? 'ok' : ($dst === 'warn' ? 'warn' : 'fail') }}">
                        @if($dst === 'ok')
                            firing recently, no failures
                        @elseif($dst === 'warn')
                            no fires in 24h
                        @else
                            recent failure recorded
                        @endif
                    </span>
                </div>
                <div class="diag-section-body" style="padding: 0;">
                    <table class="diag-kv">
                        <tbody>
                            <tr>
                                <th style="padding-left:18px;">Successful dispatches in last 24h</th>
                                <td>{{ number_format((int) ($trace['dispatcher']['success_count_24h'] ?? 0)) }}</td>
                            </tr>
                            <tr>
                                <th style="padding-left:18px;">Most recent failed_jobs entry</th>
                                <td>
                                    @if(! empty($trace['dispatcher']['last_failure']))
                                        <span style="color:#f87171;"><strong>Failed at {{ $trace['dispatcher']['last_failure']['failed_at'] }}</strong></span>
                                        <pre style="white-space:pre-wrap; margin-top:0.5rem; color:#fbbf24; font-size:0.78rem;">{{ $trace['dispatcher']['last_failure']['exception'] }}</pre>
                                    @else
                                        <span style="color:#34d399;">none for this corp + GenerateReport class</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="diag-section">
                <div class="diag-section-header">Webhook delivery preview</div>
                <div class="diag-section-body" style="padding: 0;">
                    @if(($trace['webhook_status'] ?? null) === 'missing_table')
                        <div style="padding: 1rem 1.2rem; color:#fbbf24;">
                            <code>corpwalletmanager_webhooks</code> table missing. Restart the SeAT stack to run pending migrations.
                        </div>
                    @elseif(($trace['webhook_status'] ?? null) === 'none')
                        <div style="padding: 1.5rem; color:#f87171; background:#3a1a1a; border-left: 3px solid #ef4444;">
                            <strong>No webhooks subscribed to this report type for this corp.</strong>
                            The schedule will fire but the report will not deliver to Discord. Add or enable a webhook on Settings (Discord Webhooks) with the matching <code>{{ $trace['webhook_flag'] ?? 'notify_*' }}</code> flag.
                        </div>
                    @else
                        <table class="diag-data-table">
                            <thead>
                                <tr>
                                    <th style="padding-left:18px;">Webhook</th>
                                    <th class="nowrap">Scope</th>
                                    <th>Role mention</th>
                                    <th class="nowrap">Would deliver</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($trace['webhooks'] as $wh)
                                    <tr>
                                        <td style="padding-left:18px;"><strong>{{ $wh->name }}</strong> <span class="text-muted">[id {{ $wh->id }}]</span></td>
                                        <td class="nowrap">
                                            @if($wh->corporation_id)
                                                <span class="badge badge-info">Corp {{ $wh->corporation_id }}</span>
                                            @else
                                                <span class="badge badge-secondary">Global</span>
                                            @endif
                                        </td>
                                        <td>{{ $wh->discord_role_id ?: '—' }}</td>
                                        <td class="nowrap"><span style="color:#34d399;"><i class="fas fa-check"></i> yes</span></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <div class="diag-section">
                <div class="diag-section-header">Computed next-run window</div>
                <div class="diag-section-body" style="padding: 0;">
                    <table class="diag-kv">
                        <tbody>
                            <tr><th style="padding-left:18px;">Next firing (UTC)</th><td>{{ $trace['window']['next_firing'] }}</td></tr>
                            <tr><th style="padding-left:18px;">Report period from</th><td>{{ $trace['window']['from'] }}</td></tr>
                            <tr><th style="padding-left:18px;">Report period to</th><td>{{ $trace['window']['to'] }}</td></tr>
                            <tr><th style="padding-left:18px;">Summary</th><td>{{ $trace['window']['human'] }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    {{-- ==================== NOTIFICATION TESTING ==================== --}}
    <div class="diag-tab-pane {{ $activeTab === 'notification-testing' ? 'active' : '' }}" id="notification-testing">
        <div class="diag-tab-intro">
            <p><strong>What this tab does:</strong> fires a real Discord webhook delivery for any of the five CWM notification categories without waiting for the scheduled trigger (report digests) or a matching real-world condition (large transfer / low balance). Each subscribed webhook is contacted individually and the per-channel outcome is reported below.</p>
            <p><strong>When to use:</strong> after configuring a new webhook, after a Discord channel permissions change, or any time you need to confirm that a category actually reaches the right channel. The embed sent to Discord is clearly labelled <code>TEST NOTIFICATION</code> so recipients know it is not a real report or alert.</p>
            <p><strong>Heads up:</strong> this fires the real webhook URL with a single attempt (no retry on 5xx). Subscriber selection mirrors live delivery: a webhook receives the test only if it is enabled AND has the matching <code>notify_$category</code> flag set AND its scope (global or a specific corp) matches the chosen target.</p>
        </div>

        @can('corpwalletmanager.settings')
        <form method="POST" action="{{ route('corpwalletmanager.diagnostic.fire-test') }}" class="diag-trace-form">
            @csrf
            <div>
                <label for="cwm-test-category">Notification category</label>
                <select class="form-control" id="cwm-test-category" name="category" required style="width: 280px;">
                    <option value="weekly_report">Weekly Report</option>
                    <option value="monthly_report">Monthly Report</option>
                    <option value="on_demand_report">On-Demand Report</option>
                    <option value="large_transfer">Large Transfer Alert</option>
                    <option value="low_balance">Low Balance Alert</option>
                    <option value="contribution_drop">Contribution Drop Alert</option>
                    <option value="unusual_recipient">Unusual Recipient Alert</option>
                </select>
            </div>
            <div>
                <label for="cwm-test-corp">Target corporation</label>
                <select class="form-control" id="cwm-test-corp" name="corporation_id" style="width: 320px;">
                    <option value="">All corps subscribed to this category</option>
                    @foreach($corporations as $corp)
                        <option value="{{ $corp->corporation_id }}" {{ $selectedCorpId === (int) $corp->corporation_id ? 'selected' : '' }}>
                            {{ $corp->name }} ({{ $corp->corporation_id }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="btn btn-cwm-primary" onclick="return confirm('Send a real test message to every subscribed webhook for this category?');">
                    <i class="fas fa-paper-plane"></i> Fire Test Notification
                </button>
            </div>
        </form>
        @else
        <div class="diag-section">
            <div class="diag-section-body" style="padding: 1.5rem; color:#fbbf24;">
                You do not have the <code>corpwalletmanager.settings</code> permission required to fire test notifications.
            </div>
        </div>
        @endcan

        @php $nt = $notificationTest ?? ['state' => 'idle']; @endphp

        @if($nt['state'] === 'idle')
            <div class="diag-section">
                <div class="diag-section-body" style="padding: 1.5rem; color:#94a3b8;">
                    Choose a category and (optionally) a target corp, then click <em>Fire Test Notification</em>. The embed sent is labelled clearly as a test so recipients will not mistake it for a real alert or report.
                </div>
            </div>
        @elseif($nt['state'] === 'no_subscribers')
            <div class="diag-section">
                <div class="diag-section-header">Last fired: {{ $nt['category_label'] ?? '' }} @ {{ $nt['fired_at'] ?? '' }}</div>
                <div class="diag-section-body" style="padding: 1.5rem; color:#fbbf24;">
                    <i class="fas fa-exclamation-triangle"></i>
                    No enabled webhook is subscribed to <strong>{{ $nt['category_label'] ?? '' }}</strong>
                    @if($nt['corporation_id'])
                        for corp <code>{{ $nt['corporation_id'] }}</code> (and no global webhook covers this category)
                    @else
                        across any scope
                    @endif
                    . Nothing was delivered. Configure a webhook on Settings &rarr; Discord Webhooks with this category's flag enabled.
                </div>
            </div>
        @else
            @php
                $sent = collect($nt['outcomes'] ?? [])->where('status', 'success')->count();
                $failed = collect($nt['outcomes'] ?? [])->where('status', 'failure')->count();
            @endphp
            <div class="diag-section">
                <div class="diag-section-header">
                    Last fired: {{ $nt['category_label'] ?? '' }} @ {{ $nt['fired_at'] ?? '' }}
                    <span class="diag-badge {{ $failed === 0 ? 'ok' : ($sent === 0 ? 'fail' : 'warn') }}">
                        {{ $sent }} delivered / {{ $failed }} failed
                    </span>
                </div>
                <div class="diag-section-body" style="padding: 0;">
                    <table class="diag-data-table">
                        <thead>
                            <tr>
                                <th class="nowrap">Webhook</th>
                                <th class="nowrap">Scope</th>
                                <th>Role mention</th>
                                <th class="nowrap">Outcome</th>
                                <th>Detail</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($nt['outcomes'] as $oc)
                                <tr>
                                    <td class="nowrap"><strong>{{ $oc['webhook_name'] }}</strong> <span class="text-muted">[id {{ $oc['webhook_id'] }}]</span></td>
                                    <td class="nowrap">
                                        @if($oc['corporation_id'])
                                            <span class="badge badge-info">Corp {{ $oc['corporation_id'] }}</span>
                                        @else
                                            <span class="badge badge-secondary">Global</span>
                                        @endif
                                    </td>
                                    <td>
                                        @include('corpwalletmanager::_role_pill', ['desc' => $oc['role_desc']])
                                    </td>
                                    <td class="nowrap">
                                        @if($oc['status'] === 'success')
                                            <span style="color:#34d399;"><i class="fas fa-check"></i> Delivered</span>
                                        @elseif($oc['status'] === 'failure')
                                            <span style="color:#f87171;"><i class="fas fa-times"></i> Failed</span>
                                        @else
                                            <span class="text-muted">{{ $oc['status'] }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $oc['message'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if($failed > 0)
                <div class="alert alert-warning mt-2" style="font-size:0.85rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                    One or more deliveries failed. Common causes: the webhook URL was rotated in Discord (404), the
                    channel was deleted, or Discord throttled the request (429). Each row's <em>Detail</em> column
                    shows the precise error. The webhook's success/failure counter is updated regardless.
                </div>
            @endif
        @endif
    </div>

        </div>{{-- /.card-body --}}
    </div>{{-- /.card.card-dark wrapping tabs + content --}}

</div> {{-- /.corp-wallet-wrapper.diagnostic-page --}}
@stop

@push('javascript')
<script>
(function () {
    // Tab switching - vanilla JS, no localStorage restore per the diagnostic
    // standard. Default landing tab is always Health Checks (set server-side
    // via the active class on the matching .diag-tab + .diag-tab-pane).
    function activate(target) {
        document.querySelectorAll('.corp-wallet-wrapper.diagnostic-page .diag-tab').forEach(function (t) {
            t.classList.toggle('active', t.dataset.target === target);
        });
        document.querySelectorAll('.corp-wallet-wrapper.diagnostic-page .diag-tab-pane').forEach(function (p) {
            p.classList.toggle('active', p.id === target);
        });
        try { history.replaceState(null, '', '#' + target); } catch (e) {}
    }

    document.querySelectorAll('.corp-wallet-wrapper.diagnostic-page .diag-tab').forEach(function (tab) {
        tab.addEventListener('click', function () { activate(this.dataset.target); });
    });

    // Hash-based initial activation overrides the server-rendered active
    // tab only when the page is opened with a #tab in the URL (e.g. after
    // clicking Refresh which preserves the hash).
    var initialHash = window.location.hash.replace('#', '');
    if (initialHash) {
        var t = document.querySelector('.corp-wallet-wrapper.diagnostic-page .diag-tab[data-target="' + initialHash + '"]');
        if (t) { activate(initialHash); }
    }
})();
</script>
@endpush
