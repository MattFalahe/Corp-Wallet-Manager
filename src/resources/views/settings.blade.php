@extends('web::layouts.app')

@section('title', 'CorpWallet Manager - Settings')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/corp-wallet-manager/css/corp-wallet-manager.css') }}?v=1">
<style>
    .cwm-settings-wrapper { display: flex; gap: 20px; align-items: flex-start; }
    .cwm-settings-sidebar { flex: 0 0 240px; position: sticky; top: 70px; }
    .cwm-settings-content { flex: 1; min-width: 0; }
    .cwm-settings-section { display: none; }
    .cwm-settings-section.active { display: block; }
    .cwm-settings-sidebar .nav-pills .nav-link {
        color: #cbd5e1;
        border-radius: 4px;
        margin: 2px 8px;
        padding: 8px 12px;
        transition: all 0.2s;
    }
    .cwm-settings-sidebar .nav-pills .nav-link:hover {
        background: rgba(102, 126, 234, 0.18);
        color: #f1f5f9;
    }
    .cwm-settings-sidebar .nav-pills .nav-link.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #ffffff;
    }
    .cwm-settings-sidebar .nav-pills .nav-link i {
        width: 18px;
        text-align: center;
        margin-right: 8px;
    }
    .cwm-settings-sidebar .nav-header {
        padding: 8px 16px 4px;
        color: #94a3b8;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    @media (max-width: 820px) {
        .cwm-settings-wrapper { flex-direction: column; }
        .cwm-settings-sidebar { flex: 1; position: static; }
    }
    /* Recipient picker */
    .cwm-recipient-picker { max-height: 220px; overflow-y: auto; border: 1px solid rgba(148,163,184,0.2); border-radius: 4px; }
    .cwm-recipient-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 10px; border-bottom: 1px solid rgba(148,163,184,0.08); cursor: pointer; transition: background 0.15s; }
    .cwm-recipient-row:last-child { border-bottom: none; }
    .cwm-recipient-row:hover { background: rgba(102,126,234,0.12); }
    .cwm-recipient-row.added { opacity: 0.45; cursor: default; }
    .cwm-recipient-meta { color: #94a3b8; font-size: 0.85rem; }
    .cwm-recipient-type {
        display: inline-block; padding: 1px 7px; border-radius: 3px; font-size: 0.7rem;
        margin-right: 6px; vertical-align: middle;
    }
    .cwm-recipient-type-character { background: #1e40af; color: #dbeafe; }
    .cwm-recipient-type-corporation { background: #065f46; color: #d1fae5; }
    .cwm-recipient-type-alliance { background: #7c2d12; color: #fed7aa; }
    .cwm-recipient-type-unknown { background: #475569; color: #e2e8f0; }
    /* Discord role pill (mirrors SM's _role_pill.blade.php styling) */
    .cwm-role-pill {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 3px 9px; border-radius: 11px;
        background: rgba(99, 102, 241, 0.18); color: #e0e7ff;
        font-size: 0.82rem; line-height: 1.3;
    }
    .cwm-role-pill.cwm-role-user {
        background: rgba(20, 184, 166, 0.18); color: #ccfbf1;
    }
    .cwm-role-pill.cwm-role-unknown {
        background: rgba(248, 113, 113, 0.18); color: #fecaca;
    }
    .cwm-role-color-dot {
        display: inline-block; width: 10px; height: 10px; border-radius: 50%;
        box-shadow: 0 0 0 1px rgba(255,255,255,0.18);
    }
    /* Inline role picker on the webhook form */
    .cwm-role-picker {
        max-height: 260px; overflow-y: auto;
        border: 1px solid rgba(148,163,184,0.2); border-radius: 4px;
        background: rgba(15,23,42,0.4); margin-bottom: 8px;
    }
    .cwm-role-picker-row {
        display: flex; align-items: center; gap: 10px;
        padding: 6px 10px; cursor: pointer; border-bottom: 1px solid rgba(148,163,184,0.06);
        transition: background 0.15s;
    }
    .cwm-role-picker-row:last-child { border-bottom: none; }
    .cwm-role-picker-row:hover { background: rgba(102,126,234,0.14); }
    .cwm-role-picker-row .cwm-role-meta { margin-left: auto; color: #94a3b8; font-size: 0.75rem; }
    /* Notification Routing Map - mirrors SM's _routing_map.blade.php pattern */
    .cwm-routing-map .routing-map-summary {
        display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 0.8rem 0 1.1rem;
    }
    .cwm-routing-map .routing-stat {
        background: #1e222b; border: 1px solid #3a4049; border-radius: 6px;
        padding: 0.45rem 0.8rem; font-size: 0.8rem; color: #c2c7d0;
    }
    .cwm-routing-map .routing-stat strong { color: #fff; font-size: 1.05rem; margin-right: 4px; }
    .cwm-routing-map .routing-stat.warn { background: #3a2e16; border-color: #6b5424; color: #d4c69a; }
    .cwm-routing-map .routing-stat.warn strong { color: #ffd96a; }
    .cwm-routing-map .routing-ns-label {
        font-size: 0.74rem; text-transform: uppercase; letter-spacing: 0.6px;
        color: #8b95a5; font-weight: 600; margin: 1.1rem 0 0.4rem;
    }
    .cwm-routing-map .routing-table {
        width: 100%; border-collapse: collapse;
        font-size: 0.83rem; margin-bottom: 0.4rem;
    }
    .cwm-routing-map .routing-table th {
        text-align: left; color: #8b95a5; font-weight: 500;
        font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;
        padding: 0.35rem 0.6rem; border-bottom: 1px solid #3a4049;
    }
    .cwm-routing-map .routing-table td {
        padding: 0.5rem 0.6rem; border-bottom: 1px solid #2a2f3a;
        vertical-align: top;
    }
    .cwm-routing-map .routing-table tr:last-child td { border-bottom: none; }
    .cwm-routing-map .routing-cat-cell {
        background: #20242e; border-right: 1px solid #313845; min-width: 180px;
    }
    .cwm-routing-map .routing-cat-name { color: #fff; font-weight: 600; }
    .cwm-routing-map .routing-cat-desc {
        font-size: 0.72rem; color: #8b95a5; margin-top: 3px; line-height: 1.3;
    }
    .cwm-routing-map .routing-row-disabled { opacity: 0.55; }
    .cwm-routing-map .routing-dest { color: #e2e8f0; }
    .cwm-routing-map .routing-arrow { color: #667eea; margin-right: 5px; }
    .cwm-routing-map .routing-none { color: #666c76; font-style: italic; }
    .cwm-routing-map .routing-unrouted { color: #d4c69a; }
    .cwm-routing-map .routing-empty { color: #8b95a5; font-style: italic; padding: 0.6rem 0; }
    .cwm-routing-map .routing-kind-badge {
        display: inline-block; font-size: 0.66rem;
        text-transform: uppercase; letter-spacing: 0.4px; font-weight: 600;
        padding: 1px 6px; border-radius: 8px; margin-left: 6px; white-space: nowrap;
    }
    .cwm-routing-map .routing-kind-badge.kind-report { background: #1c6f3e; color: #d4f4e2; }
    .cwm-routing-map .routing-kind-badge.kind-alert  { background: #7a5a0f; color: #fff1c7; }
    .cwm-routing-map .routing-alliance-warn {
        background: #3a2e16; border: 1px solid #6b5424;
        border-left: 3px solid #f59e0b; padding: 0.7rem 0.9rem;
        border-radius: 5px; color: #d4c69a; margin: 0.8rem 0 1.1rem;
        font-size: 0.85rem;
    }
    .cwm-routing-map .routing-alliance-warn i { color: #f59e0b; margin-right: 6px; }
    .cwm-routing-map .routing-alliance-warn strong { color: #ffd96a; }
</style>
@endpush

@section('content')
<div class="corp-wallet-wrapper">
<div class="row">
    <div class="col-12">
        @if(session('success'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">
                <i class="fas fa-times-circle"></i> {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="cwm-settings-wrapper">
            <div class="cwm-settings-sidebar">
                <div class="card card-dark">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-cog"></i> Settings</h3>
                    </div>
                    <div class="card-body p-0">
                        <ul class="nav nav-pills flex-column">
                            <li class="nav-header"><i class="fas fa-sliders-h"></i> Configuration</li>
                            <li class="nav-item"><a href="#" class="nav-link active" data-cwm-section="general"><i class="fas fa-cogs"></i> General</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-cwm-section="member-view"><i class="fas fa-user"></i> Member View</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-cwm-section="alerts"><i class="fas fa-bell"></i> Alert Thresholds</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-cwm-section="alliance-tax"><i class="fas fa-balance-scale"></i> Alliance Tax</a></li>
                            <li class="nav-header mt-2"><i class="fas fa-plug"></i> Integrations</li>
                            <li class="nav-item"><a href="#" class="nav-link" data-cwm-section="webhooks"><i class="fab fa-discord"></i> Discord Webhooks</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-cwm-section="routing-map"><i class="fas fa-project-diagram"></i> Notification Routing</a></li>
                            <li class="nav-header mt-2"><i class="fas fa-wrench"></i> Operations</li>
                            <li class="nav-item"><a href="#" class="nav-link" data-cwm-section="scheduled-reports"><i class="fas fa-calendar-alt"></i> Scheduled Reports</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-cwm-section="data-export"><i class="fas fa-file-export"></i> Data Export</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-cwm-section="maintenance"><i class="fas fa-tools"></i> Maintenance</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-cwm-section="job-status"><i class="fas fa-tasks"></i> Job Status</a></li>
                            <li class="nav-item"><a href="#" class="nav-link" data-cwm-section="access-logs"><i class="fas fa-history"></i> Access Logs</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="cwm-settings-content">
        <form action="{{ route('corpwalletmanager.settings.update') }}" method="POST">
            @csrf

            <!-- Main Settings Card -->
            <div class="cwm-settings-section active" data-cwm-section-content="general">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">CorpWallet Manager Settings</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Display Settings</h5>

                            <div class="form-group">
                                <label for="selected_corporation_id">Corporation</label>
                                <select class="form-control" id="selected_corporation_id" name="selected_corporation_id">
                                    <option value="">All Corporations</option>
                                    @foreach($corporations as $corp)
                                        <option value="{{ $corp->corporation_id }}"
                                                {{ $settings['selected_corporation_id'] == $corp->corporation_id ? 'selected' : '' }}>
                                            {{ $corp->name }} ({{ $corp->corporation_id }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Select which corporation to display data for</small>
                            </div>

                            <div class="form-group">
                                <label for="refresh_minutes">Chart Refresh Interval</label>
                                <select class="form-control" id="refresh_minutes" name="refresh_minutes">
                                    <option value="0" {{ $settings['refresh_minutes'] == '0' ? 'selected' : '' }}>No Auto Refresh</option>
                                    <option value="5" {{ $settings['refresh_minutes'] == '5' ? 'selected' : '' }}>5 Minutes</option>
                                    <option value="15" {{ $settings['refresh_minutes'] == '15' ? 'selected' : '' }}>15 Minutes</option>
                                    <option value="30" {{ $settings['refresh_minutes'] == '30' ? 'selected' : '' }}>30 Minutes</option>
                                    <option value="60" {{ $settings['refresh_minutes'] == '60' ? 'selected' : '' }}>60 Minutes</option>
                                </select>
                                <small class="text-muted">How often charts update automatically</small>
                            </div>

                            <div class="form-group">
                                <label for="decimals">Decimal Places</label>
                                <input type="number" class="form-control" id="decimals"
                                       name="decimals" value="{{ $settings['decimals'] }}"
                                       min="0" max="8">
                                <small class="text-muted">Number of decimal places for ISK values</small>
                            </div>

                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="color_actual">Actual Balance Color</label>
                                        <input type="color" class="form-control" id="color_actual"
                                               name="color_actual" value="{{ $settings['color_actual'] }}">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="color_predicted">Predicted Balance Color</label>
                                        <input type="color" class="form-control" id="color_predicted"
                                               name="color_predicted" value="{{ $settings['color_predicted'] }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h5>Performance Settings</h5>

                            <div class="form-group">
                                <div class="form-check">
                                    <input type="hidden" name="use_precomputed_predictions" value="0">
                                    <input type="checkbox" class="form-check-input" id="use_precomputed_predictions"
                                           name="use_precomputed_predictions" value="1"
                                           {{ $settings['use_precomputed_predictions'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="use_precomputed_predictions">
                                        Use Precomputed Predictions
                                    </label>
                                    <small class="form-text text-muted d-block">
                                        Use cached predictions instead of calculating on-the-fly
                                    </small>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input type="hidden" name="use_precomputed_monthly_balances" value="0">
                                    <input type="checkbox" class="form-check-input" id="use_precomputed_monthly_balances"
                                           name="use_precomputed_monthly_balances" value="1"
                                           {{ $settings['use_precomputed_monthly_balances'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="use_precomputed_monthly_balances">
                                        Use Precomputed Monthly Balances
                                    </label>
                                    <small class="form-text text-muted d-block">
                                        Use cached monthly balances instead of calculating on-the-fly
                                    </small>
                                </div>
                            </div>

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> <strong>Corporation Selection:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Select a specific corporation to view only their data</li>
                                    <li>Choose "All Corporations" to see aggregate data</li>
                                    <li>This affects all views and maintenance jobs</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            </div>{{-- /.cwm-settings-section general --}}

            <!-- Member View Settings Card -->
            <div class="cwm-settings-section" data-cwm-section-content="member-view">
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">Member View Settings</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Section Visibility</h5>
                            <p class="text-muted">Control which sections are visible in the member view</p>

                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_health"
                                           name="member_show_health" value="1"
                                           {{ ($settings['member_show_health'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_health">
                                        Show Health Status
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_trends"
                                           name="member_show_trends" value="1"
                                           {{ ($settings['member_show_trends'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_trends">
                                        Show Trend Charts
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_activity"
                                           name="member_show_activity" value="1"
                                           {{ ($settings['member_show_activity'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_activity">
                                        Show Activity Metrics
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_goals"
                                           name="member_show_goals" value="1"
                                           {{ ($settings['member_show_goals'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_goals">
                                        Show Corporation Goals
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_milestones"
                                           name="member_show_milestones" value="1"
                                           {{ ($settings['member_show_milestones'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_milestones">
                                        Show Milestones & Events
                                    </label>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_balance"
                                           name="member_show_balance" value="1"
                                           {{ ($settings['member_show_balance'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_balance">
                                        Show Actual ISK Values
                                    </label>
                                    <small class="form-text text-muted d-block">
                                        Uncheck to show normalized trends instead of actual amounts
                                    </small>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_performance"
                                           name="member_show_performance" value="1"
                                           {{ ($settings['member_show_performance'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_performance">
                                        Show Performance Metrics
                                    </label>
                                </div>
                            </div>

                            <hr>
                            <h5>Personal Contribution &amp; Leaderboard</h5>
                            <p class="text-muted">
                                Toggles for the personal-contribution panel, top-contributors leaderboard,
                                personal Mining Manager tax compliance, and personal milestones.
                            </p>

                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_personal_contribution"
                                           name="member_show_personal_contribution" value="1"
                                           {{ ($settings['member_show_personal_contribution'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_personal_contribution">
                                        Show My Contribution Card
                                    </label>
                                    <small class="form-text text-muted d-block">
                                        Logged-in user sees their own monthly contribution, rank, percentile, lifetime, and per-bucket strip.
                                    </small>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_leaderboard"
                                           name="member_show_leaderboard" value="1"
                                           {{ ($settings['member_show_leaderboard'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_leaderboard">
                                        Show Top Contributors Leaderboard
                                    </label>
                                    <small class="form-text text-muted d-block">
                                        Members see a ranked list of their corp's top contributors with the privacy mode you choose below.
                                    </small>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_mm_compliance"
                                           name="member_show_mm_compliance" value="1"
                                           {{ ($settings['member_show_mm_compliance'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_mm_compliance">
                                        Show My Mining Manager Tax Compliance
                                    </label>
                                    <small class="form-text text-muted d-block">
                                        Only renders when Mining Manager is installed. Shows the viewer's owed / paid / compliance percentage for the month.
                                    </small>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_personal_wallet"
                                           name="member_show_personal_wallet" value="1"
                                           {{ ($settings['member_show_personal_wallet'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_personal_wallet">
                                        Show My Personal Wallet Tab
                                    </label>
                                    <small class="form-text text-muted d-block">
                                        Adds a third tab to the member view aggregating the viewer's personal SeAT wallet across every character they own (no corp filter). Income / expense / net flow for the month, top sources, biggest transactions, a 6-month balance sparkline, and a per-character breakdown.
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h5>Goal Settings</h5>
                            <p class="text-muted">Set targets for corporation goals</p>

                            <div class="form-group">
                                <label for="goal_savings_target">Savings Target (ISK)</label>
                                <input type="number" class="form-control" id="goal_savings_target"
                                       name="goal_savings_target"
                                       value="{{ $settings['goal_savings_target'] ?? 1000000000 }}"
                                       min="0" step="1000000">
                                <small class="text-muted">Monthly savings goal in ISK</small>
                            </div>

                            <div class="form-group">
                                <label for="goal_activity_target">Activity Target</label>
                                <input type="number" class="form-control" id="goal_activity_target"
                                       name="goal_activity_target"
                                       value="{{ $settings['goal_activity_target'] ?? 1000 }}"
                                       min="0">
                                <small class="text-muted">Target number of monthly transactions</small>
                            </div>

                            <div class="form-group">
                                <label for="goal_growth_target">Growth Target (%)</label>
                                <input type="number" class="form-control" id="goal_growth_target"
                                       name="goal_growth_target"
                                       value="{{ $settings['goal_growth_target'] ?? 10 }}"
                                       min="0" max="100" step="0.1">
                                <small class="text-muted">Monthly growth percentage target</small>
                            </div>

                            <hr>
                            <h5>Leaderboard Privacy</h5>
                            <p class="text-muted">
                                The privacy mode is enforced server-side, so a member opening devtools cannot
                                reveal hidden values. Pick whichever fits how transparent your corp wants to be.
                            </p>

                            <div class="form-group">
                                <label class="d-block">Leaderboard Display Mode</label>

                                <div class="form-check">
                                    <input type="radio" class="form-check-input" id="member_leaderboard_mode_isk_visible"
                                           name="member_leaderboard_mode" value="isk_visible"
                                           {{ ($settings['member_leaderboard_mode'] ?? 'isk_visible') === 'isk_visible' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_leaderboard_mode_isk_visible">
                                        <strong>ISK Visible</strong>
                                    </label>
                                    <small class="form-text text-muted d-block ml-4">
                                        Members see actual ISK contribution amounts. Best for transparent corps.
                                    </small>
                                </div>

                                <div class="form-check mt-2">
                                    <input type="radio" class="form-check-input" id="member_leaderboard_mode_percentage"
                                           name="member_leaderboard_mode" value="percentage"
                                           {{ ($settings['member_leaderboard_mode'] ?? 'isk_visible') === 'percentage' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_leaderboard_mode_percentage">
                                        <strong>Percentage</strong>
                                    </label>
                                    <small class="form-text text-muted d-block ml-4">
                                        Members see each contributor's share as a % of corp total. Less revealing than raw ISK.
                                    </small>
                                </div>

                                <div class="form-check mt-2">
                                    <input type="radio" class="form-check-input" id="member_leaderboard_mode_rank_only"
                                           name="member_leaderboard_mode" value="rank_only"
                                           {{ ($settings['member_leaderboard_mode'] ?? 'isk_visible') === 'rank_only' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_leaderboard_mode_rank_only">
                                        <strong>Rank Only</strong>
                                    </label>
                                    <small class="form-text text-muted d-block ml-4">
                                        Members see ranks and names but no amounts. Most private. Useful for big corps.
                                    </small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="member_leaderboard_size">Leaderboard Size</label>
                                <select class="form-control" id="member_leaderboard_size" name="member_leaderboard_size">
                                    <option value="5"  {{ ((string)($settings['member_leaderboard_size'] ?? '10')) === '5'  ? 'selected' : '' }}>Top 5</option>
                                    <option value="10" {{ ((string)($settings['member_leaderboard_size'] ?? '10')) === '10' ? 'selected' : '' }}>Top 10</option>
                                    <option value="20" {{ ((string)($settings['member_leaderboard_size'] ?? '10')) === '20' ? 'selected' : '' }}>Top 20</option>
                                </select>
                                <small class="text-muted">
                                    Number of top entries shown. The viewer's own row is always appended below the top N when they sit outside it.
                                </small>
                            </div>

                            <div class="form-group">
                                <label for="member_data_delay">Data Delay</label>
                                <select class="form-control" id="member_data_delay" name="member_data_delay">
                                    <option value="0" {{ ($settings['member_data_delay'] ?? '0') == '0' ? 'selected' : '' }}>
                                        Real-time
                                    </option>
                                    <option value="24" {{ ($settings['member_data_delay'] ?? '0') == '24' ? 'selected' : '' }}>
                                        24 hours delayed
                                    </option>
                                    <option value="48" {{ ($settings['member_data_delay'] ?? '0') == '48' ? 'selected' : '' }}>
                                        48 hours delayed
                                    </option>
                                    <option value="168" {{ ($settings['member_data_delay'] ?? '0') == '168' ? 'selected' : '' }}>
                                        1 week delayed
                                    </option>
                                </select>
                                <small class="text-muted">Delay data shown to members for operational security</small>
                            </div>

                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> <strong>Member View Note:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>The member view permission controls access to this view</li>
                                    <li>You can disable entire sections to customize what members see</li>
                                    <li>Data delay helps protect operational security</li>
                                    <li>Goals encourage member engagement</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            </div>{{-- /.cwm-settings-section member-view --}}

            <!-- Alert Thresholds Card -->
            <div class="cwm-settings-section" data-cwm-section-content="alerts">
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">Alert Thresholds</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Set the ISK levels that trigger wallet alerts. Set a value to <strong>0</strong> to
                        disable that alert. Alerts are delivered to the Discord webhooks below (and, when
                        Manager Core is installed, published to its cross-plugin event bus).
                    </p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="alert_large_transaction_threshold">Large Transaction Threshold (ISK)</label>
                                <input type="number" class="form-control" id="alert_large_transaction_threshold"
                                       name="alert_large_transaction_threshold" min="0" step="1000000"
                                       value="{{ $settings['alert_large_transaction_threshold'] ?? '0' }}">
                                <small class="text-muted">Alert when a single wallet transaction (in or out) meets or exceeds this amount.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="alert_low_balance_threshold">Low Balance Threshold (ISK)</label>
                                <input type="number" class="form-control" id="alert_low_balance_threshold"
                                       name="alert_low_balance_threshold" min="0" step="1000000"
                                       value="{{ $settings['alert_low_balance_threshold'] ?? '0' }}">
                                <small class="text-muted">Alert when a corporation total balance drops below this amount. Fires once per crossing.</small>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <h5 class="mt-2">Anomaly Detection</h5>
                    <p class="text-muted">
                        Catch patterns the simple ISK-amount alerts above miss. A contribution drop flags a member whose
                        recent 3-month average collapses to less than 20% of the prior 3-month window (only counted when
                        the prior window cleared the floor below). An unusual recipient flags a corporation withdrawal
                        above the threshold sent to a recipient with no prior payout history. Both fire once per crossing.
                    </p>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="anomaly_contribution_threshold">Contribution Drop Floor (ISK)</label>
                                <input type="number" class="form-control" id="anomaly_contribution_threshold"
                                       name="anomaly_contribution_threshold" min="0" step="1000000"
                                       value="{{ $settings['anomaly_contribution_threshold'] ?? '0' }}">
                                <small class="text-muted">Only flag a member whose prior 3-month average was at or above this amount. Recommended starting point: <code>100000000</code> (100M). Latch clears on recovery to 50% of prior.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="anomaly_unusual_recipient_threshold">Unusual Recipient Threshold (ISK)</label>
                                <input type="number" class="form-control" id="anomaly_unusual_recipient_threshold"
                                       name="anomaly_unusual_recipient_threshold" min="0" step="1000000"
                                       value="{{ $settings['anomaly_unusual_recipient_threshold'] ?? '0' }}">
                                <small class="text-muted">Alert when a <code>corporation_account_withdrawal</code> at or above this amount targets a recipient this corp has never paid before. Recommended starting point: <code>500000000</code> (500M).</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            </div>{{-- /.cwm-settings-section alerts --}}

            <!-- Alliance Tax Card -->
            <div class="cwm-settings-section" data-cwm-section-content="alliance-tax">
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">Alliance Tax</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Set the share of each contribution category that the corporation forwards to the alliance.
                        The <em>Top Contributors</em> leaderboard displays before-tax (gross contribution) and after-tax
                        (what the corp keeps) side by side when any rate is above zero. Leave every field at
                        <strong>0</strong> if the corp pays no alliance tax (or is not in an alliance) and the columns
                        stay hidden. Fractional rates like <code>7.5</code> are supported.
                    </p>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="alliance_tax_ratting_pct">Ratting Tax (%)</label>
                                <input type="number" class="form-control" id="alliance_tax_ratting_pct"
                                       name="alliance_tax_ratting_pct" min="0" max="100" step="0.1"
                                       value="{{ $settings['alliance_tax_ratting_pct'] ?? '0' }}">
                                <small class="text-muted">Applied to bounty prizes.</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="alliance_tax_mission_pct">Mission Tax (%)</label>
                                <input type="number" class="form-control" id="alliance_tax_mission_pct"
                                       name="alliance_tax_mission_pct" min="0" max="100" step="0.1"
                                       value="{{ $settings['alliance_tax_mission_pct'] ?? '0' }}">
                                <small class="text-muted">Applied to mission rewards.</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="alliance_tax_tax_payment_pct">Tax Payment (%)</label>
                                <input type="number" class="form-control" id="alliance_tax_tax_payment_pct"
                                       name="alliance_tax_tax_payment_pct" min="0" max="100" step="0.1"
                                       value="{{ $settings['alliance_tax_tax_payment_pct'] ?? '0' }}">
                                <small class="text-muted">Applied to Mining Manager tax payments.</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="alliance_tax_donation_voluntary_pct">Voluntary Donation (%)</label>
                                <input type="number" class="form-control" id="alliance_tax_donation_voluntary_pct"
                                       name="alliance_tax_donation_voluntary_pct" min="0" max="100" step="0.1"
                                       value="{{ $settings['alliance_tax_donation_voluntary_pct'] ?? '0' }}">
                                <small class="text-muted">Applied to general player donations.</small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="alliance_tax_industry_pct">Industry (%)</label>
                                <input type="number" class="form-control" id="alliance_tax_industry_pct"
                                       name="alliance_tax_industry_pct" min="0" max="100" step="0.1"
                                       value="{{ $settings['alliance_tax_industry_pct'] ?? '0' }}">
                                <small class="text-muted">Applied to industry facility tax (member jobs on corp structures).</small>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="alliance_tax_recipient_ids">Alliance Tax Recipients (party IDs)</label>
                                <input type="text" class="form-control" id="alliance_tax_recipient_ids"
                                       name="alliance_tax_recipient_ids"
                                       placeholder="e.g. 99005338, 2114650365"
                                       value="{{ $settings['alliance_tax_recipient_ids'] ?? '' }}">
                                <small class="text-muted">
                                    Comma-separated list of the party IDs the corp pays its monthly alliance tax to (the alliance master character, a holding corp, or the alliance entity itself).
                                    Outgoing <code>corporation_account_withdrawal</code> and <code>player_donation</code> rows whose recipient matches any of these IDs are summed each month and surfaced on the Alliance Tax tab as the corp's actual alliance remit, compared against the expected total from the per-bucket rates above.
                                </small>
                            </div>
                            <div class="form-group">
                                <label class="d-block">
                                    Suggestions from recent outgoing payments
                                    <button type="button" class="btn btn-sm btn-link p-0 ml-2" id="cwm-recipient-refresh" title="Reload suggestions">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </label>
                                <div id="cwm-recipient-picker" class="cwm-recipient-picker">
                                    <div class="p-2 text-muted text-center"><i class="fas fa-spinner fa-spin"></i> Loading recent recipients...</div>
                                </div>
                                <small class="text-muted">
                                    Top recipients of outgoing <code>corporation_account_withdrawal</code> + <code>player_donation</code> rows from the last 6 months. Click a row to add its ID to the field above. Suggestions are sourced from the currently selected corporation's wallet history.
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="form-group">
                                <label for="alliance_tax_description_keywords">Alliance Tax Description Keywords</label>
                                <input type="text" class="form-control" id="alliance_tax_description_keywords"
                                       name="alliance_tax_description_keywords"
                                       placeholder="e.g. MINC-TAX, alliance tax, monthly fee"
                                       value="{{ $settings['alliance_tax_description_keywords'] ?? '' }}">
                                <small class="text-muted">
                                    Comma-separated keywords. Any outgoing <code>corporation_account_withdrawal</code> or <code>player_donation</code> whose description contains one of these strings is counted as alliance tax in addition to the recipient-id matches above.
                                    Useful when you tag remits with a distinctive memo in-game (e.g. <code>MINC-TAX</code>) so reconciliation works even if the recipient party rotates between months.
                                    Matching is case-insensitive and contains-based; keep keywords distinctive (a short tag like <code>MINC-TAX</code> beats a common phrase like <code>tax</code> which would false-match unrelated payments).
                                    Either field can be empty; if both are empty the Alliance Tax tab only shows the calculated expected amounts with no actual-paid comparison.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            </div>{{-- /.cwm-settings-section alliance-tax --}}

            <!-- Save Button Row (visible only when a form-bound section is active) -->
            <div class="cwm-settings-save-row" data-cwm-save-row="1">
                <div class="card mt-3">
                    <div class="card-body">
                        <button type="submit" class="btn btn-cwm-primary">
                            <i class="fa fa-save"></i> Save All Settings
                        </button>

                        <button type="button" class="btn btn-warning" onclick="resetSettings()">
                            <i class="fa fa-refresh"></i> Reset to Defaults
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Discord Webhooks Card -->
        <div class="cwm-settings-section" data-cwm-section-content="webhooks">
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fab fa-discord"></i> Discord Webhooks</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-cwm-primary" id="cwm-wh-add-btn">
                        <i class="fas fa-plus"></i> Add Webhook
                    </button>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Generated reports are delivered to the Discord webhooks below. A webhook scoped to a
                    corporation receives that corporation's reports; a global webhook receives every
                    corporation's reports. Each webhook chooses which report types it wants.
                </p>

                <div id="cwm-wh-test-result" class="mt-2 mb-2"></div>

                <!-- Add / edit form (hidden until Add or Edit is used) -->
                <div id="cwm-webhook-form-wrap" class="card" style="display: none;">
                    <div class="card-header">
                        <h3 class="card-title" id="cwm-wh-form-title">Add Webhook</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('corpwalletmanager.webhooks.save') }}">
                            @csrf
                            <input type="hidden" name="webhook_id" id="cwm-wh-id" value="">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="cwm-wh-name">Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="cwm-wh-name" name="name"
                                               maxlength="100" placeholder="e.g. Leadership Channel" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="cwm-wh-corp">Corporation</label>
                                        <select class="form-control" id="cwm-wh-corp" name="corporation_id">
                                            <option value="">All corporations (global)</option>
                                            @foreach($corporations as $corp)
                                                <option value="{{ $corp->corporation_id }}">{{ $corp->name }} ({{ $corp->corporation_id }})</option>
                                            @endforeach
                                        </select>
                                        <small class="text-muted">Which corporation's reports this webhook receives.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="cwm-wh-url">Discord Webhook URL <span class="text-danger">*</span></label>
                                <input type="url" class="form-control" id="cwm-wh-url" name="webhook_url"
                                       placeholder="https://discord.com/api/webhooks/..." required>
                                <small class="text-muted">Discord &rarr; Server Settings &rarr; Integrations &rarr; Webhooks.</small>
                            </div>

                            <div class="form-group">
                                <label for="cwm-wh-role">Role Mention <span class="text-muted">(optional)</span></label>
                                @if($roleProviderAvailable)
                                    {{-- Show the Pick from Discord button whenever a provider TABLE is
                                         detected, even if its role list is empty right now (e.g. SeAT
                                         Broadcast installed but no roles synced yet). Operators get
                                         feedback that the integration is wired; the empty-state lives
                                         inside the expanded picker. --}}
                                    <div class="d-flex align-items-center mb-2" style="gap: 8px;">
                                        <button type="button" class="btn btn-sm btn-secondary" id="cwm-wh-role-toggle">
                                            <i class="fas fa-tag"></i> Pick from Discord
                                        </button>
                                        <small class="text-muted">Roles detected from: {{ $discordRoleProvider }}</small>
                                    </div>
                                    <div id="cwm-wh-role-picker" class="cwm-role-picker" style="display:none;">
                                        @if(empty($discordRoles))
                                            <div class="text-muted p-2">
                                                <i class="fas fa-info-circle"></i>
                                                No roles found in the detected provider yet. If you use SeAT Broadcast, add roles under <strong>SeAT Broadcast &rarr; Discord Roles</strong> first. If you use SeAT Connector, wait for the next role sync.
                                            </div>
                                        @else
                                            @foreach($discordRoles as $role)
                                                <div class="cwm-role-picker-row" data-role-id="{{ $role['id'] }}">
                                                    @if(! empty($role['color']) && preg_match('/^#[0-9a-f]{6}$/i', $role['color']))
                                                        <span class="cwm-role-color-dot" style="background:{{ $role['color'] }};"></span>
                                                    @else
                                                        <span class="cwm-role-color-dot" style="background:#64748b;"></span>
                                                    @endif
                                                    <strong>{{ $role['name'] }}</strong>
                                                    <span class="cwm-role-meta">id {{ $role['id'] }}</span>
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                @endif
                                <input type="text" class="form-control" id="cwm-wh-role" name="discord_role_id"
                                       maxlength="32" placeholder="Discord role ID (optional)">
                                <small class="text-muted">Pinged when a report is delivered. Format: <code>123456789</code> (bare id) or <code>&lt;@&amp;123456789&gt;</code> (full mention). Leave blank for no mention.</small>
                                <div id="cwm-wh-role-preview" class="mt-2"></div>
                            </div>

                            <div class="form-group">
                                <label>Deliver these reports</label>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="cwm-wh-weekly" name="notify_weekly_report" value="1" checked>
                                    <label class="form-check-label" for="cwm-wh-weekly">Weekly summary (scheduled, Mondays)</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="cwm-wh-monthly" name="notify_monthly_report" value="1" checked>
                                    <label class="form-check-label" for="cwm-wh-monthly">Monthly summary (scheduled, 1st of month)</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="cwm-wh-ondemand" name="notify_on_demand_report" value="1" checked>
                                    <label class="form-check-label" for="cwm-wh-ondemand">On-demand reports (generated from the Director view)</label>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Deliver these alerts</label>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="cwm-wh-large-transfer" name="notify_large_transfer" value="1" checked>
                                    <label class="form-check-label" for="cwm-wh-large-transfer">Large transactions</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="cwm-wh-low-balance" name="notify_low_balance" value="1" checked>
                                    <label class="form-check-label" for="cwm-wh-low-balance">Low balance</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="cwm-wh-contribution-drop" name="notify_contribution_drop" value="1" checked>
                                    <label class="form-check-label" for="cwm-wh-contribution-drop">Member contribution drop</label>
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="cwm-wh-unusual-recipient" name="notify_unusual_recipient" value="1" checked>
                                    <label class="form-check-label" for="cwm-wh-unusual-recipient">Unusual recipient (first-time payout)</label>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="cwm-wh-enabled" name="is_enabled" value="1" checked>
                                    <label class="form-check-label" for="cwm-wh-enabled">Enabled</label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-cwm-primary">
                                <i class="fa fa-save"></i> Save Webhook
                            </button>
                            <button type="button" class="btn btn-secondary" id="cwm-wh-cancel-btn">Cancel</button>
                        </form>
                    </div>
                </div>

                <!-- Webhook list -->
                @if($webhooks->isEmpty())
                    <div class="empty-state">
                        <i class="fab fa-discord"></i>
                        <p>No webhooks configured. Reports will not be delivered to Discord.</p>
                    </div>
                @else
                    @php
                        // Build (corporation_id => name) lookup once so each
                        // webhook row can resolve its scope corp name without
                        // running a query per row. $corporations is already
                        // provided to the view by the controller.
                        $corpNameMap = [];
                        foreach ($corporations as $__corp) {
                            $corpNameMap[(int) $__corp->corporation_id] = $__corp->name;
                        }
                    @endphp
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Scope</th>
                                    <th>Role Mention</th>
                                    <th>Subscribed To</th>
                                    <th>Status</th>
                                    <th>Health</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($webhooks as $wh)
                                    @php
                                        $whTotal = (int) $wh->success_count + (int) $wh->failure_count;
                                        $whPct = $whTotal > 0 ? round($wh->success_count / $whTotal * 100, 1) : null;
                                        $whData = [
                                            'id' => $wh->id,
                                            'name' => $wh->name,
                                            'corporation_id' => $wh->corporation_id,
                                            'discord_role_id' => $wh->discord_role_id,
                                            'is_enabled' => $wh->is_enabled,
                                            'notify_weekly_report' => $wh->notify_weekly_report,
                                            'notify_monthly_report' => $wh->notify_monthly_report,
                                            'notify_on_demand_report' => $wh->notify_on_demand_report,
                                            'notify_large_transfer' => $wh->notify_large_transfer,
                                            'notify_low_balance' => $wh->notify_low_balance,
                                            'notify_contribution_drop' => $wh->notify_contribution_drop,
                                            'notify_unusual_recipient' => $wh->notify_unusual_recipient,
                                        ];
                                    @endphp
                                    <tr>
                                        <td><strong>{{ $wh->name }}</strong></td>
                                        <td>
                                            @if($wh->corporation_id)
                                                @php
                                                    $__corpName = $corpNameMap[(int) $wh->corporation_id] ?? null;
                                                @endphp
                                                <span class="badge badge-info" title="Corp ID {{ $wh->corporation_id }}">
                                                    {{ $__corpName ?? ('Corp ' . $wh->corporation_id) }}
                                                </span>
                                            @else
                                                <span class="badge badge-secondary">Global</span>
                                            @endif
                                        </td>
                                        <td>
                                            @include('corpwalletmanager::_role_pill', ['desc' => $webhookRoleDescriptions[$wh->id] ?? null])
                                        </td>
                                        <td>
                                            @if($wh->notify_weekly_report)<span class="badge badge-secondary">Weekly</span> @endif
                                            @if($wh->notify_monthly_report)<span class="badge badge-secondary">Monthly</span> @endif
                                            @if($wh->notify_on_demand_report)<span class="badge badge-secondary">On-demand</span> @endif
                                            @if($wh->notify_large_transfer)<span class="badge badge-warning">Large TX</span> @endif
                                            @if($wh->notify_low_balance)<span class="badge badge-warning">Low balance</span> @endif
                                            @if($wh->notify_contribution_drop)<span class="badge badge-warning">Contribution drop</span> @endif
                                            @if($wh->notify_unusual_recipient)<span class="badge badge-warning">Unusual recipient</span> @endif
                                        </td>
                                        <td>
                                            @if($wh->is_enabled)
                                                <span class="badge badge-success">Enabled</span>
                                            @else
                                                <span class="badge badge-secondary">Disabled</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($whPct === null)
                                                <span class="text-muted">Not tested</span>
                                            @elseif($whPct >= 90)
                                                <span class="text-success">{{ $whPct }}%</span>
                                            @elseif($whPct >= 70)
                                                <span class="text-warning">{{ $whPct }}%</span>
                                            @else
                                                <span class="text-danger">{{ $whPct }}%</span>
                                            @endif
                                            @if($wh->last_error)
                                                <i class="fas fa-info-circle text-warning" title="{{ $wh->last_error }}"></i>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <button type="button" class="btn btn-sm btn-info cwm-wh-edit"
                                                    data-webhook='@json($whData, JSON_HEX_APOS|JSON_HEX_TAG|JSON_HEX_AMP)'>
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-secondary cwm-wh-test" data-id="{{ $wh->id }}">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                            <form method="POST" action="{{ route('corpwalletmanager.webhooks.delete', $wh->id) }}"
                                                  class="d-inline" onsubmit="return confirm('Delete this webhook?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        </div>{{-- /.cwm-settings-section webhooks --}}

        <!-- Notification Routing Map -->
        <div class="cwm-settings-section" data-cwm-section-content="routing-map">
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-project-diagram"></i> Notification Routing</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Read-only snapshot of every notification CWM can deliver and which Discord channels would receive it.
                    Each row shows a category, the enabled webhook(s) currently subscribed to it, the scope (Global vs
                    a specific corp), and the Discord role each would mention. Configure routing on the
                    <a href="#" data-cwm-section="webhooks" style="color:#a5b4fc;">Discord Webhooks</a> tab.
                </p>

                <div class="cwm-routing-map">
                    <div class="routing-map-summary">
                        <div class="routing-stat"><strong>{{ $routingMap['summary']['total'] }}</strong> categories</div>
                        <div class="routing-stat"><strong>{{ $routingMap['summary']['covered'] }}</strong> delivering</div>
                        @if($routingMap['summary']['silent'] > 0)
                            <div class="routing-stat warn">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>{{ $routingMap['summary']['silent'] }}</strong> with no enabled subscriber
                            </div>
                        @endif
                    </div>

                    @if(! empty($routingMap['alliance_tax_warning']))
                        <div class="routing-alliance-warn">
                            <i class="fas fa-balance-scale"></i>
                            <strong>Alliance Tax delivery gap:</strong>
                            {{ $routingMap['alliance_tax_warning'] }}
                        </div>
                    @endif

                    <table class="routing-table">
                        <thead>
                            <tr>
                                <th style="width:28%;">Category</th>
                                <th style="width:28%;">Delivers to</th>
                                <th style="width:18%;">Scope</th>
                                <th style="width:26%;">Will mention</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($routingMap['categories'] as $rcat)
                                @if(empty($rcat['webhooks']))
                                    <tr>
                                        <td class="routing-cat-cell">
                                            <div class="routing-cat-name">
                                                <i class="fas {{ $rcat['icon'] }}"></i>
                                                {{ $rcat['label'] }}
                                                <span class="routing-kind-badge kind-{{ $rcat['kind'] }}">{{ $rcat['kind'] }}</span>
                                            </div>
                                            <div class="routing-cat-desc">{{ $rcat['desc'] }}</div>
                                        </td>
                                        <td colspan="3">
                                            <span class="routing-unrouted">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                No webhooks subscribed (this category fires nowhere).
                                            </span>
                                        </td>
                                    </tr>
                                @else
                                    @foreach($rcat['webhooks'] as $ri => $rsub)
                                        @php($rwh = $rsub['webhook'])
                                        <tr>
                                            @if($ri === 0)
                                                <td class="routing-cat-cell" rowspan="{{ count($rcat['webhooks']) }}">
                                                    <div class="routing-cat-name">
                                                        <i class="fas {{ $rcat['icon'] }}"></i>
                                                        {{ $rcat['label'] }}
                                                        <span class="routing-kind-badge kind-{{ $rcat['kind'] }}">{{ $rcat['kind'] }}</span>
                                                    </div>
                                                    <div class="routing-cat-desc">{{ $rcat['desc'] }}</div>
                                                </td>
                                            @endif
                                            <td class="routing-dest">
                                                <i class="fas fa-arrow-right routing-arrow"></i>{{ $rwh->name }}
                                            </td>
                                            <td>
                                                @if($rsub['corp_label'])
                                                    <span class="badge badge-info">{{ $rsub['corp_label'] }}</span>
                                                @else
                                                    <span class="badge badge-secondary">Global</span>
                                                @endif
                                            </td>
                                            <td>
                                                @include('corpwalletmanager::_role_pill', ['desc' => $rsub['role']])
                                            </td>
                                        </tr>
                                    @endforeach
                                @endif
                            @empty
                                <tr>
                                    <td colspan="4" class="routing-empty">No notification categories registered.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="alert alert-info mt-3" style="font-size:0.85rem;">
                        <i class="fas fa-info-circle"></i>
                        <strong>How routing resolves:</strong> a corp-scoped webhook receives that corporation's
                        notifications PLUS every <em>global</em> webhook's traffic. Disabled webhooks are excluded
                        from this map entirely. Role mention is per-webhook (the same role pings for every
                        category that webhook subscribes to).
                    </div>
                </div>
            </div>
        </div>
        </div>{{-- /.cwm-settings-section routing-map --}}

        <!-- Scheduled Reports Card -->
        <div class="cwm-settings-section" data-cwm-section-content="scheduled-reports">
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-calendar-alt"></i> Scheduled Reports</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-cwm-primary" id="cwm-sched-add-btn">
                        <i class="fas fa-plus"></i> Add Schedule
                    </button>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Schedules drive WHEN reports run. Each schedule is per-corporation and per-cadence (daily / weekly / monthly / quarterly / annual). The dispatcher cron checks every 5 minutes for due schedules, so a schedule set for 03:00 fires within a 5-minute window of 03:00.
                    Webhook delivery routing is configured separately under <a href="#" data-cwm-section="webhooks" style="color:#a5b4fc;">Discord Webhooks</a>: each webhook chooses which report types it wants (weekly / monthly / on-demand). All times below are <strong>UTC</strong>.
                </p>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="cwm-sched-corp-filter" class="d-block mb-1">
                            <i class="fas fa-filter"></i> Filter by corporation
                        </label>
                        <select class="form-control" id="cwm-sched-corp-filter">
                            <option value="">All corporations</option>
                            @foreach($corporations as $corp)
                                <option value="{{ $corp->corporation_id }}"
                                        {{ ($settings['selected_corporation_id'] ?? '') == $corp->corporation_id ? 'selected' : '' }}>
                                    {{ $corp->name }} ({{ $corp->corporation_id }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div id="cwm-sched-message" class="mb-2"></div>

                <div class="table-responsive">
                    <table class="table table-striped" id="cwm-sched-table">
                        <thead>
                            <tr>
                                <th>Corporation</th>
                                <th>Cadence</th>
                                <th>Enabled</th>
                                <th>Day / Time</th>
                                <th>Next Run</th>
                                <th>Last Run</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="cwm-sched-tbody">
                            <tr><td colspan="8" class="text-center text-muted">Loading schedules...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-info mt-3" style="font-size:0.85rem;">
                    <i class="fas fa-info-circle"></i>
                    <strong>First install:</strong> weekly schedules (Monday 03:30 UTC) and monthly schedules (day 1 at 03:00 UTC) are auto-created for every corporation with wallet history, so existing operators keep their pre-3.0 delivery cadence without re-configuring. Edit or delete any of them here, or add new daily / quarterly / annual schedules.
                </div>
            </div>
        </div>

        <!-- Add / Edit schedule modal (vanilla overlay; matches the settings styling) -->
        <div id="cwm-sched-modal" class="modal" tabindex="-1" role="dialog" style="display:none; background: rgba(15,23,42,0.75);">
            <div class="modal-dialog" role="document">
                <div class="modal-content" style="background:#1f242e; border:1px solid #3a4049; color:#e2e8f0;">
                    <div class="modal-header" style="border-bottom:1px solid #3a4049;">
                        <h5 class="modal-title" id="cwm-sched-modal-title">Add Schedule</h5>
                        <button type="button" class="close" id="cwm-sched-modal-close" aria-label="Close" style="color:#cbd5e1;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="cwm-sched-id" value="">

                        <div class="form-group">
                            <label for="cwm-sched-corp">Corporation <span class="text-danger">*</span></label>
                            <select class="form-control" id="cwm-sched-corp" required>
                                <option value="">Select a corporation...</option>
                                @foreach($corporations as $corp)
                                    <option value="{{ $corp->corporation_id }}">{{ $corp->name }} ({{ $corp->corporation_id }})</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Reports cover only this corporation's wallet data.</small>
                        </div>

                        <div class="form-group">
                            <label for="cwm-sched-cadence">Cadence <span class="text-danger">*</span></label>
                            <select class="form-control" id="cwm-sched-cadence" required>
                                <option value="daily">Daily</option>
                                <option value="weekly" selected>Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>

                        <div class="form-group cwm-sched-field" data-cwm-sched-show-for="weekly">
                            <label for="cwm-sched-dow">Day of week</label>
                            <select class="form-control" id="cwm-sched-dow">
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                                <option value="7">Sunday</option>
                            </select>
                        </div>

                        <div class="form-group cwm-sched-field" data-cwm-sched-show-for="monthly,quarterly,annual">
                            <label for="cwm-sched-dom">Day of month (1-28)</label>
                            <input type="number" class="form-control" id="cwm-sched-dom" min="1" max="28" value="1">
                            <small class="text-muted">Capped at 28 so the schedule fires every month (including February).</small>
                        </div>

                        <div class="form-group cwm-sched-field" data-cwm-sched-show-for="annual">
                            <label for="cwm-sched-moy">Month</label>
                            <select class="form-control" id="cwm-sched-moy">
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>

                        <div class="form-group cwm-sched-field" data-cwm-sched-show-for="quarterly">
                            <div class="alert alert-info" style="font-size:0.85rem;">
                                <i class="fas fa-info-circle"></i> Quarterly schedules fire on the same day-of-month within the first month of each quarter (Jan / Apr / Jul / Oct).
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="cwm-sched-hour">Hour (UTC, 0-23)</label>
                                    <input type="number" class="form-control" id="cwm-sched-hour" min="0" max="23" value="3">
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label for="cwm-sched-minute">Minute (0-59)</label>
                                    <input type="number" class="form-control" id="cwm-sched-minute" min="0" max="59" value="0">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="cwm-sched-enabled" checked>
                                <label class="form-check-label" for="cwm-sched-enabled">Enabled</label>
                                <small class="form-text text-muted">Disable to keep the row for reference without firing.</small>
                            </div>
                        </div>

                        <div id="cwm-sched-modal-error" class="alert alert-danger" style="display:none;"></div>
                    </div>
                    <div class="modal-footer" style="border-top:1px solid #3a4049;">
                        <button type="button" class="btn btn-secondary" id="cwm-sched-modal-cancel">Cancel</button>
                        <button type="button" class="btn btn-cwm-primary" id="cwm-sched-modal-save">
                            <i class="fas fa-save"></i> Save Schedule
                        </button>
                    </div>
                </div>
            </div>
        </div>

        </div>{{-- /.cwm-settings-section scheduled-reports --}}

        {{-- Data Export panel (v3.0.0)
             Mirrors Mining Manager's bulk CSV pattern: pick sections,
             pick a date range, pick a format, queue the export. Recent
             Exports table below shows the last five generated files
             with one-click download (signed URL valid for 24h) and
             delete. The generated file lands under
             storage/app/cwm-exports/{corp_id}/...

             All UI state lives in JS - no form post round-trip - and
             the operator can keep working in other Settings tabs while
             the export job runs in the background. --}}
        <div class="cwm-settings-section" data-cwm-section-content="data-export">
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-export"></i> Data Export</h3>
                <div class="card-tools">
                    <span class="badge badge-info" id="cwm-export-corp-badge">
                        @if($settings['selected_corporation_id'])
                            Corp ID: {{ $settings['selected_corporation_id'] }}
                        @else
                            No corporation selected
                        @endif
                    </span>
                </div>
            </div>
            <div class="card-body">
                <p>
                    Bulk CSV export for offline analysis or archival. Pick the sections you want, choose a date range, and the export runs in the background. Files land under
                    <code>storage/app/cwm-exports/{corp_id}/...</code> and the download link is valid for 24 hours.
                </p>

                @if(empty($settings['selected_corporation_id']))
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Pick a corporation under Settings &rarr; General before running an export.
                    </div>
                @endif

                <form id="cwm-data-export-form" onsubmit="return false;">
                    <div class="form-group">
                        <label><strong>Sections to include</strong></label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="cwm-section-wallet_journal" value="wallet_journal" checked>
                                    <label class="form-check-label" for="cwm-section-wallet_journal">
                                        <strong>Wallet Journal Entries</strong>
                                        <br>
                                        <small class="text-muted">Raw rows from <code>corporation_wallet_journals</code> with inter-division transfers filtered out and party names resolved.</small>
                                    </label>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="cwm-section-contributions" value="contributions" checked>
                                    <label class="form-check-label" for="cwm-section-contributions">
                                        <strong>Contribution Records</strong>
                                        <br>
                                        <small class="text-muted">Per-character bucket aggregates from <code>corpwalletmanager_character_contributions</code>.</small>
                                    </label>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="cwm-section-reports" value="reports">
                                    <label class="form-check-label" for="cwm-section-reports">
                                        <strong>Report Metadata</strong>
                                        <br>
                                        <small class="text-muted">List of generated reports (metadata only, not the PDF files themselves).</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="cwm-section-alerts" value="alerts">
                                    <label class="form-check-label" for="cwm-section-alerts">
                                        <strong>Alert History</strong>
                                        <br>
                                        <small class="text-muted">Low-balance + contribution-drop crossings derived from the latch tables.</small>
                                    </label>
                                </div>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="cwm-section-anomaly_state" value="anomaly_state">
                                    <label class="form-check-label" for="cwm-section-anomaly_state">
                                        <strong>Anomaly State Snapshot</strong>
                                        <br>
                                        <small class="text-muted">Current state of every (corp, character) row in the anomaly-state latch table.</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="cwm-export-date-from"><strong>From</strong></label>
                                <input type="date" id="cwm-export-date-from" class="form-control" value="{{ now()->subDays(30)->toDateString() }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="cwm-export-date-to"><strong>To</strong></label>
                                <input type="date" id="cwm-export-date-to" class="form-control" value="{{ now()->toDateString() }}">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="cwm-export-format"><strong>Format</strong></label>
                                <select id="cwm-export-format" class="form-control">
                                    <option value="zip" selected>ZIP of CSVs (one file per section)</option>
                                    <option value="csv">Single multi-section CSV</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-cwm-primary" id="cwm-export-generate">
                        <i class="fas fa-cogs"></i> Generate Export
                    </button>
                    <span id="cwm-export-form-status" class="ml-2" style="vertical-align: middle;"></span>
                </form>

                <hr>

                <h5>Recent Exports</h5>
                <p class="text-muted small">
                    Up to the last 5 exports for this corp. Download links are signed and valid for 24 hours.
                </p>
                <div class="table-responsive">
                    <table class="table table-striped" id="cwm-export-recent-table">
                        <thead>
                            <tr>
                                <th>Requested</th>
                                <th>Status</th>
                                <th>Sections</th>
                                <th>Period</th>
                                <th>Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="cwm-export-recent-body">
                            <tr><td colspan="6" class="text-center text-muted"><em>Loading...</em></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        </div>{{-- /.cwm-settings-section data-export --}}

        <!-- Maintenance Card -->
        <div class="cwm-settings-section" data-cwm-section-content="maintenance">
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Maintenance</h3>
                <div class="card-tools">
                    <span class="badge badge-info" id="selected-corp-badge">
                        @if($settings['selected_corporation_id'])
                            Corp ID: {{ $settings['selected_corporation_id'] }}
                        @else
                            All Corporations
                        @endif
                    </span>
                </div>
            </div>
            <div class="card-body">
                <p>Use these tools to manually trigger data processing jobs.
                   @if($settings['selected_corporation_id'])
                       <strong>Jobs will run for Corporation ID: {{ $settings['selected_corporation_id'] }}</strong>
                   @else
                       <strong>Jobs will run for all corporations.</strong>
                   @endif
                </p>

                <div class="row">
                    <div class="col-md-6">
                        <h5>Basic Jobs</h5>
                        <form action="{{ route('corpwalletmanager.settings.backfill') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-info mb-2">
                                <i class="fa fa-database"></i> Wallet Backfill
                            </button>
                        </form>

                        <form action="{{ route('corpwalletmanager.settings.prediction') }}" method="POST" class="d-inline ml-2">
                            @csrf
                            <button type="submit" class="btn btn-success mb-2">
                                <i class="fa fa-calculator"></i> Compute Predictions
                            </button>
                        </form>
                    </div>

                    <div class="col-md-6">
                        <h5>Division Jobs</h5>
                        <form action="{{ route('corpwalletmanager.settings.division-backfill') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-info mb-2">
                                <i class="fa fa-th"></i> Division Backfill
                            </button>
                        </form>

                        <form action="{{ route('corpwalletmanager.settings.division-prediction') }}" method="POST" class="d-inline ml-2">
                            @csrf
                            <button type="submit" class="btn btn-success mb-2">
                                <i class="fa fa-chart-bar"></i> Division Predictions
                            </button>
                        </form>
                    </div>
                </div>

                <hr>

                <div class="row">
                    <div class="col-md-12">
                        <h5>Contribution Cache</h5>
                        <p class="text-muted">
                            Rebuilds the per-character contribution cache (<code>corpwalletmanager_character_contributions</code>) from journal history. Run this after:
                        </p>
                        <ul class="text-muted">
                            <li>First-time setup, to populate Top Contributors with historical data (the hourly job otherwise starts from now and never replays).</li>
                            <li>Configuring or changing alliance tax rates / recipients, so the Alliance Tax reconciliation tab reflects past months.</li>
                            <li>Installing or updating Mining Manager, so historical tax-coded donations re-split from the voluntary bucket into the tax_payment bucket.</li>
                            <li>After major plugin upgrades that change classifier logic (industry / ESS / corp-tax variants).</li>
                        </ul>
                        <form action="{{ route('corpwalletmanager.settings.contribution-backfill') }}" method="POST" class="form-inline">
                            @csrf
                            <label class="mr-2 mb-0" for="cwm-contrib-backfill-months">Months to rebuild:</label>
                            <select name="months" id="cwm-contrib-backfill-months" class="form-control mr-2 mb-0" style="width: auto;">
                                <option value="1">1 month</option>
                                <option value="3">3 months</option>
                                <option value="6" selected>6 months</option>
                                <option value="12">12 months</option>
                                <option value="24">24 months</option>
                            </select>
                            <button type="submit" class="btn btn-warning">
                                <i class="fa fa-users"></i> Backfill Contributions
                            </button>
                        </form>
                        <small class="text-muted mt-2 d-block">
                            Runs in the background. Existing cache rows for the selected periods are deleted and rebuilt from scratch, so this is safe to run repeatedly. On busy corps a 12-month backfill can take several minutes; refresh the Top Contributors / Alliance Tax tabs to see results.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        </div>{{-- /.cwm-settings-section maintenance --}}

        <!-- Job Status Card -->
        <div class="cwm-settings-section" data-cwm-section-content="job-status">
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Job Status</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-tool" onclick="refreshJobStatus()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="running-jobs-alert" class="alert alert-warning d-none">
                    <i class="fas fa-spinner fa-spin"></i> <span id="running-count">0</span> job(s) currently running...
                </div>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Job Type</th>
                                <th>Status</th>
                                <th>Started</th>
                                <th>Duration</th>
                                <th>Records</th>
                                <th>Corporation</th>
                            </tr>
                        </thead>
                        <tbody id="job-status-table">
                            @forelse($recentLogs as $log)
                                <tr>
                                    <td>{{ $log->job_type_display }}</td>
                                    <td>
                                        <span class="badge {{ $log->status_badge_class }}">
                                            {{ ucfirst($log->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $log->started_at->format('Y-m-d H:i:s') }}</td>
                                    <td>{{ $log->formatted_duration }}</td>
                                    <td>{{ number_format($log->records_processed) }}</td>
                                    <td>
                                        @if($log->corporation)
                                            {{ $log->corporation->name ?? 'N/A' }}
                                        @else
                                            All
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No jobs found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        </div>{{-- /.cwm-settings-section job-status --}}

        <!-- Access Logs Card -->
        <div class="cwm-settings-section" data-cwm-section-content="access-logs">
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Access Logs</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-tool" onclick="loadAccessLogs()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>View</th>
                                <th>Corporation</th>
                                <th>Accessed</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody id="access-logs-table">
                            <tr>
                                <td colspan="5" class="text-center text-muted">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2">
                    <small class="text-muted">Showing last 50 access logs</small>
                </div>
            </div>
        </div>
        </div>{{-- /.cwm-settings-section access-logs --}}

            </div>{{-- /.cwm-settings-content --}}
        </div>{{-- /.cwm-settings-wrapper --}}
    </div>
</div>
</div> {{-- /.corp-wallet-wrapper --}}

<script>

// Helper function to build URLs - respects current protocol
function buildUrl(path) {
    // Use window.location.origin which includes protocol, host, and port
    // This automatically matches HTTP or HTTPS based on how the user accessed the page
    return window.location.origin + path;
}

function resetSettings() {
    if (confirm('Are you sure you want to reset all settings to defaults? This cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("corpwalletmanager.settings.reset") }}';

        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';

        form.appendChild(csrfToken);
        document.body.appendChild(form);
        form.submit();
    }
}

function refreshJobStatus() {
    fetch('{{ route("corpwalletmanager.settings.job-status") }}')
        .then(response => response.json())
        .then(data => {
            // Update running jobs alert
            const alertDiv = document.getElementById('running-jobs-alert');
            const runningCount = document.getElementById('running-count');

            if (data.running_jobs > 0) {
                alertDiv.classList.remove('d-none');
                runningCount.textContent = data.running_jobs;
            } else {
                alertDiv.classList.add('d-none');
            }

            // Update job table if we have recent jobs
            if (data.recent_jobs && data.recent_jobs.length > 0) {
                const tbody = document.getElementById('job-status-table');
                let html = '';

                data.recent_jobs.forEach(job => {
                    let badgeClass = 'badge-secondary';
                    if (job.status === 'running') badgeClass = 'badge-warning';
                    else if (job.status === 'completed') badgeClass = 'badge-success';
                    else if (job.status === 'failed') badgeClass = 'badge-danger';

                    html += `
                        <tr>
                            <td>${job.job_type}</td>
                            <td><span class="badge ${badgeClass}">${job.status}</span></td>
                            <td>${job.started_at}</td>
                            <td>${job.duration}</td>
                            <td>${job.records_processed.toLocaleString()}</td>
                            <td>${job.corporation_id || 'All'}</td>
                        </tr>
                    `;
                });

                tbody.innerHTML = html;
            }
        })
        .catch(error => {
            console.error('Error fetching job status:', error);
        });
}

function loadAccessLogs() {
    // Build URL with proper protocol
    const url = window.location.protocol + '//' + window.location.host + '/corp-wallet-manager/settings/access-logs';

    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const tbody = document.getElementById('access-logs-table');

            if (data.logs && data.logs.length > 0) {
                let html = '';
                data.logs.forEach(log => {
                    html += `
                        <tr>
                            <td>${log.user}</td>
                            <td><span class="badge badge-info">${log.view}</span></td>
                            <td>${log.corporation}</td>
                            <td>${log.accessed_at}</td>
                            <td><small>${log.ip_address}</small></td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No access logs found</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading access logs:', error);
            document.getElementById('access-logs-table').innerHTML =
                '<tr><td colspan="5" class="text-center text-muted">Access logs not available yet. Run migrations if needed.</td></tr>';
        });
}

// ---- Discord webhook management ----
// JS mirror of DiscordRoleResolver::describeRoleMention(), built on
// page load from the picker rows in the DOM. Keeps the role-mention
// input's preview pill in sync with what the saved webhook list will
// show.
var cwmRoleLookup = (function () {
    var map = {};
    document.querySelectorAll('#cwm-wh-role-picker .cwm-role-picker-row').forEach(function (row) {
        var id = row.getAttribute('data-role-id');
        if (!id) return;
        var name = row.querySelector('strong');
        var dot = row.querySelector('.cwm-role-color-dot');
        var color = dot ? (dot.style.background || dot.style.backgroundColor) : null;
        map[String(id)] = {
            name: name ? name.textContent : 'Role ' + id,
            color: color || null,
        };
    });
    return map;
})();

function cwmExtractSnowflake(raw) {
    if (!raw) return null;
    var m = String(raw).trim().match(/(\d{2,})/);
    return m ? m[1] : null;
}

function cwmUpdateRolePreview() {
    var preview = document.getElementById('cwm-wh-role-preview');
    if (!preview) return;
    var raw = (document.getElementById('cwm-wh-role').value || '').trim();
    if (raw === '') {
        preview.innerHTML = '<span class="text-muted" style="font-style:italic;font-size:0.85rem;">No mention (this webhook will not @-ping anyone)</span>';
        return;
    }
    var kind = 'unknown';
    if (/^<@&\d+>$/.test(raw) || /^\d+$/.test(raw)) {
        kind = 'role';
    } else if (/^<@!?\d+>$/.test(raw)) {
        kind = 'user';
    }
    var id = cwmExtractSnowflake(raw);
    if (kind === 'role' && id && cwmRoleLookup[id]) {
        var role = cwmRoleLookup[id];
        var dot = role.color
            ? '<span class="cwm-role-color-dot" style="background:' + role.color + ';"></span>'
            : '';
        preview.innerHTML = '<span class="cwm-role-pill" title="Discord role id ' + id + '">' + dot + ' <span>@' + role.name + '</span></span>';
    } else if (kind === 'role' && id) {
        preview.innerHTML = '<span class="cwm-role-pill cwm-role-unknown"><i class="fas fa-question-circle"></i> <span>Role ' + id + ' (not in any installed list)</span></span>';
    } else if (kind === 'user') {
        preview.innerHTML = '<span class="cwm-role-pill cwm-role-user"><i class="fas fa-user"></i> <span>User mention' + (id ? ' (' + id + ')' : '') + '</span></span>';
    } else {
        preview.innerHTML = '<span class="cwm-role-pill cwm-role-unknown"><i class="fas fa-exclamation-triangle"></i> <span>Unrecognized (will not ping)</span></span>';
    }
}

function cwmResetWebhookForm() {
    document.getElementById('cwm-wh-id').value = '';
    document.getElementById('cwm-wh-name').value = '';
    document.getElementById('cwm-wh-corp').value = '';
    document.getElementById('cwm-wh-url').value = '';
    document.getElementById('cwm-wh-role').value = '';
    var picker = document.getElementById('cwm-wh-role-picker');
    if (picker) { picker.style.display = 'none'; }
    cwmUpdateRolePreview();
    document.getElementById('cwm-wh-weekly').checked = true;
    document.getElementById('cwm-wh-monthly').checked = true;
    document.getElementById('cwm-wh-ondemand').checked = true;
    document.getElementById('cwm-wh-large-transfer').checked = true;
    document.getElementById('cwm-wh-low-balance').checked = true;
    document.getElementById('cwm-wh-contribution-drop').checked = true;
    document.getElementById('cwm-wh-unusual-recipient').checked = true;
    document.getElementById('cwm-wh-enabled').checked = true;
}

function cwmShowWebhookForm() {
    cwmResetWebhookForm();
    document.getElementById('cwm-wh-form-title').textContent = 'Add Webhook';
    var url = document.getElementById('cwm-wh-url');
    url.setAttribute('required', 'required');
    url.placeholder = 'https://discord.com/api/webhooks/...';
    var wrap = document.getElementById('cwm-webhook-form-wrap');
    wrap.style.display = '';
    wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function cwmHideWebhookForm() {
    document.getElementById('cwm-webhook-form-wrap').style.display = 'none';
}

function cwmEditWebhook(wh) {
    cwmResetWebhookForm();
    document.getElementById('cwm-wh-form-title').textContent = 'Edit Webhook';
    document.getElementById('cwm-wh-id').value = wh.id;
    document.getElementById('cwm-wh-name').value = wh.name || '';
    document.getElementById('cwm-wh-corp').value = wh.corporation_id || '';
    document.getElementById('cwm-wh-role').value = wh.discord_role_id || '';
    cwmUpdateRolePreview();
    document.getElementById('cwm-wh-weekly').checked = !!wh.notify_weekly_report;
    document.getElementById('cwm-wh-monthly').checked = !!wh.notify_monthly_report;
    document.getElementById('cwm-wh-ondemand').checked = !!wh.notify_on_demand_report;
    document.getElementById('cwm-wh-large-transfer').checked = !!wh.notify_large_transfer;
    document.getElementById('cwm-wh-low-balance').checked = !!wh.notify_low_balance;
    document.getElementById('cwm-wh-contribution-drop').checked = !!wh.notify_contribution_drop;
    document.getElementById('cwm-wh-unusual-recipient').checked = !!wh.notify_unusual_recipient;
    document.getElementById('cwm-wh-enabled').checked = !!wh.is_enabled;
    var url = document.getElementById('cwm-wh-url');
    url.removeAttribute('required');
    url.value = '';
    url.placeholder = 'Leave blank to keep the current webhook URL';
    var wrap = document.getElementById('cwm-webhook-form-wrap');
    wrap.style.display = '';
    wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

(function () {
    var addBtn = document.getElementById('cwm-wh-add-btn');
    if (addBtn) { addBtn.addEventListener('click', cwmShowWebhookForm); }

    var cancelBtn = document.getElementById('cwm-wh-cancel-btn');
    if (cancelBtn) { cancelBtn.addEventListener('click', cwmHideWebhookForm); }

    // Discord role picker: toggle button + per-row click writes the id
    // into the input. Pre-resolved role data is in the DOM (data-role-id
    // on each row) so no AJAX needed; same merged + deduped list as the
    // _role_pill partial uses for the webhook list summary.
    var pickerToggle = document.getElementById('cwm-wh-role-toggle');
    var picker = document.getElementById('cwm-wh-role-picker');
    if (pickerToggle && picker) {
        pickerToggle.addEventListener('click', function () {
            picker.style.display = picker.style.display === 'none' ? '' : 'none';
        });
        picker.querySelectorAll('.cwm-role-picker-row').forEach(function (row) {
            row.addEventListener('click', function () {
                document.getElementById('cwm-wh-role').value = this.getAttribute('data-role-id');
                cwmUpdateRolePreview();
                picker.style.display = 'none';
            });
        });
    }

    var roleInput = document.getElementById('cwm-wh-role');
    if (roleInput) {
        roleInput.addEventListener('input', cwmUpdateRolePreview);
        cwmUpdateRolePreview();
    }

    document.querySelectorAll('.cwm-wh-edit').forEach(function (btn) {
        btn.addEventListener('click', function () {
            try {
                cwmEditWebhook(JSON.parse(this.getAttribute('data-webhook')));
            } catch (e) {
                console.error('Could not parse webhook data', e);
            }
        });
    });

    document.querySelectorAll('.cwm-wh-test').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = this.getAttribute('data-id');
            var result = document.getElementById('cwm-wh-test-result');
            result.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending test message...';
            fetch(buildUrl('/corp-wallet-manager/settings/webhooks/' + id + '/test'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                result.innerHTML = '<div class="alert alert-' + (d.success ? 'success' : 'danger') + '">' +
                    '<i class="fas fa-' + (d.success ? 'check' : 'times') + '"></i> ' + d.message + '</div>';
            })
            .catch(function () {
                result.innerHTML = '<div class="alert alert-danger"><i class="fas fa-times"></i> Test request failed.</div>';
            });
        });
    });
})();

// Auto-refresh job status every 30 seconds
setInterval(refreshJobStatus, 30000);

// Auto-refresh access logs every 60 seconds
setInterval(loadAccessLogs, 60000);

// ---------------------------------------------------------------
// Settings sidebar section switching
// ---------------------------------------------------------------

(function () {
    var FORM_SECTIONS = ['general', 'member-view', 'alerts', 'alliance-tax'];

    function activateSection(name) {
        if (!name) return;
        document.querySelectorAll('.cwm-settings-section').forEach(function (s) {
            s.classList.toggle('active', s.getAttribute('data-cwm-section-content') === name);
        });
        document.querySelectorAll('[data-cwm-section]').forEach(function (l) {
            l.classList.toggle('active', l.getAttribute('data-cwm-section') === name);
        });
        // The Save All Settings row is form-bound; only show it on form sections.
        var saveRow = document.querySelector('[data-cwm-save-row]');
        if (saveRow) {
            saveRow.style.display = FORM_SECTIONS.indexOf(name) >= 0 ? '' : 'none';
        }
        // Preserve selection across reloads via URL hash.
        try {
            if (history && history.replaceState) {
                history.replaceState(null, '', '#' + name);
            }
        } catch (e) { /* ignore */ }
    }

    function initSidebar() {
        document.querySelectorAll('[data-cwm-section]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                activateSection(this.getAttribute('data-cwm-section'));
            });
        });

        // Hash-based restoration so the operator's last tab survives a reload
        // / save redirect. Default to general.
        var hash = (window.location.hash || '').replace(/^#/, '');
        var validSections = ['general', 'member-view', 'alerts', 'alliance-tax', 'webhooks', 'routing-map', 'scheduled-reports', 'maintenance', 'job-status', 'access-logs'];
        if (hash && validSections.indexOf(hash) >= 0) {
            activateSection(hash);
        } else {
            // Still apply the save-row visibility logic for the default.
            activateSection('general');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSidebar);
    } else {
        initSidebar();
    }
})();

// ---------------------------------------------------------------
// Alliance tax recipient picker
// ---------------------------------------------------------------

(function () {
    function formatISK(n) {
        n = +n || 0;
        if (n >= 1e12) return (n / 1e12).toFixed(2) + 'T';
        if (n >= 1e9)  return (n / 1e9).toFixed(2) + 'B';
        if (n >= 1e6)  return (n / 1e6).toFixed(2) + 'M';
        if (n >= 1e3)  return (n / 1e3).toFixed(2) + 'K';
        return n.toFixed(0);
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function currentRecipientIds() {
        var input = document.getElementById('alliance_tax_recipient_ids');
        if (!input) return [];
        return input.value.split(/[\s,;]+/).map(function (p) { return p.trim(); }).filter(Boolean);
    }

    function addRecipientId(id) {
        var input = document.getElementById('alliance_tax_recipient_ids');
        if (!input) return;
        var ids = currentRecipientIds();
        if (ids.indexOf(String(id)) >= 0) return;
        ids.push(String(id));
        input.value = ids.join(', ');
        renderPicker(window.__cwmRecipientCache || []);
    }

    function renderPicker(recipients) {
        var picker = document.getElementById('cwm-recipient-picker');
        if (!picker) return;
        if (!recipients.length) {
            picker.innerHTML = '<div class="p-2 text-muted text-center">No outgoing payments found in the last 6 months. Suggestions will appear once the corp starts making outgoing payments.</div>';
            return;
        }
        var alreadyAdded = currentRecipientIds();
        picker.innerHTML = recipients.map(function (r) {
            var added = alreadyAdded.indexOf(String(r.id)) >= 0;
            var typeClass = 'cwm-recipient-type-' + escapeHtml(r.type || 'unknown');
            return ''
                + '<div class="cwm-recipient-row' + (added ? ' added' : '') + '" data-id="' + r.id + '">'
                + '  <div>'
                + '    <span class="cwm-recipient-type ' + typeClass + '">' + escapeHtml(r.type || 'unknown') + '</span>'
                + '    <strong>' + escapeHtml(r.name) + '</strong>'
                + '    <span class="cwm-recipient-meta">[' + r.id + ']</span>'
                + '  </div>'
                + '  <div class="cwm-recipient-meta text-right">'
                + '    ' + formatISK(r.total_sent) + ' ISK'
                + '    <span class="ml-2">' + r.count + 'x</span>'
                + (added ? ' <span class="ml-2 badge badge-secondary">added</span>' : '')
                + '  </div>'
                + '</div>';
        }).join('');

        picker.querySelectorAll('.cwm-recipient-row').forEach(function (row) {
            if (row.classList.contains('added')) return;
            row.addEventListener('click', function () {
                addRecipientId(this.getAttribute('data-id'));
            });
        });
    }

    function loadRecipients() {
        var picker = document.getElementById('cwm-recipient-picker');
        if (!picker) return;
        picker.innerHTML = '<div class="p-2 text-muted text-center"><i class="fas fa-spinner fa-spin"></i> Loading recent recipients...</div>';
        fetch(window.location.origin + '/corp-wallet-manager/settings/recent-outgoing-recipients', {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                picker.innerHTML = '<div class="p-2 text-muted text-center">' + escapeHtml(data.message || 'Could not load recipients.') + '</div>';
                return;
            }
            window.__cwmRecipientCache = data.recipients || [];
            renderPicker(window.__cwmRecipientCache);
        })
        .catch(function () {
            picker.innerHTML = '<div class="p-2 text-danger text-center">Failed to load recipients.</div>';
        });
    }

    function init() {
        // Re-render on text-input changes so the "added" indicator stays in sync.
        var input = document.getElementById('alliance_tax_recipient_ids');
        if (input) {
            input.addEventListener('input', function () {
                if (window.__cwmRecipientCache) {
                    renderPicker(window.__cwmRecipientCache);
                }
            });
        }
        var refresh = document.getElementById('cwm-recipient-refresh');
        if (refresh) {
            refresh.addEventListener('click', function (e) {
                e.preventDefault();
                window.__cwmRecipientCache = null;
                loadRecipients();
            });
        }

        // Lazy: only fetch when the Alliance Tax section is first opened,
        // so the page load doesn't hit the journal scan for everyone.
        var loaded = false;
        document.querySelectorAll('[data-cwm-section="alliance-tax"]').forEach(function (link) {
            link.addEventListener('click', function () {
                if (!loaded) {
                    loaded = true;
                    loadRecipients();
                }
            });
        });
        // If the user landed directly on the alliance-tax hash, load now.
        if ((window.location.hash || '').replace(/^#/, '') === 'alliance-tax') {
            loaded = true;
            loadRecipients();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

// ---------------------------------------------------------------
// Scheduled Reports (Settings -> Scheduled Reports)
// ---------------------------------------------------------------
(function () {
    var CADENCE_LABELS = {
        daily: 'Daily', weekly: 'Weekly', monthly: 'Monthly',
        quarterly: 'Quarterly', annual: 'Annual'
    };
    var CADENCE_BADGES = {
        daily: 'badge-info', weekly: 'badge-primary', monthly: 'badge-success',
        quarterly: 'badge-warning', annual: 'badge-secondary'
    };

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content');
        return '{{ csrf_token() }}';
    }

    function showMessage(kind, text) {
        var box = document.getElementById('cwm-sched-message');
        if (!box) return;
        var cls = kind === 'success' ? 'alert-success' : (kind === 'warning' ? 'alert-warning' : 'alert-danger');
        var icon = kind === 'success' ? 'check-circle' : (kind === 'warning' ? 'exclamation-triangle' : 'times-circle');
        box.innerHTML = '<div class="alert ' + cls + '"><i class="fas fa-' + icon + '"></i> ' + escapeHtml(text) + '</div>';
        setTimeout(function () { box.innerHTML = ''; }, 6000);
    }

    function fmtIso(iso) {
        if (!iso) return '-';
        try {
            var d = new Date(iso);
            if (isNaN(d.getTime())) return iso;
            var pad = function (n) { return n < 10 ? '0' + n : '' + n; };
            return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-' + pad(d.getUTCDate())
                + ' ' + pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) + ' UTC';
        } catch (e) {
            return iso;
        }
    }

    function loadSchedules() {
        var tbody = document.getElementById('cwm-sched-tbody');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading schedules...</td></tr>';
        var filter = document.getElementById('cwm-sched-corp-filter');
        var corpId = filter ? filter.value : '';
        var qs = corpId ? ('?corporation_id=' + encodeURIComponent(corpId)) : '';
        fetch(buildUrl('/corp-wallet-manager/api/report-schedules' + qs), {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">' + escapeHtml(data.message || 'Could not load schedules.') + '</td></tr>';
                return;
            }
            renderSchedules(data.schedules || []);
        })
        .catch(function () {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger">Failed to load schedules.</td></tr>';
        });
    }

    function renderSchedules(rows) {
        var tbody = document.getElementById('cwm-sched-tbody');
        if (!tbody) return;
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">'
                + '<i class="fas fa-info-circle"></i> No schedules configured for this corporation. '
                + 'Click <strong>Add Schedule</strong> to create one.'
                + '</td></tr>';
            return;
        }
        var html = '';
        rows.forEach(function (s) {
            var badgeCls = CADENCE_BADGES[s.report_type] || 'badge-secondary';
            var enabledLbl = s.enabled
                ? '<span class="badge badge-success">Enabled</span>'
                : '<span class="badge badge-secondary">Disabled</span>';
            var statusLbl = '-';
            if (s.last_status === 'ok') {
                statusLbl = '<span class="badge badge-success">OK</span>';
            } else if (s.last_status === 'failed') {
                statusLbl = '<span class="badge badge-danger" title="' + escapeHtml(s.last_error || '') + '">Failed</span>';
            }
            html += '<tr>'
                + '<td>' + escapeHtml(s.corporation) + ' <span class="text-muted">[' + s.corporation_id + ']</span></td>'
                + '<td><span class="badge ' + badgeCls + '">' + escapeHtml(CADENCE_LABELS[s.report_type] || s.report_type) + '</span></td>'
                + '<td>' + enabledLbl + '</td>'
                + '<td>' + escapeHtml(s.human || '') + '</td>'
                + '<td>' + escapeHtml(fmtIso(s.next_run_at)) + '</td>'
                + '<td>' + escapeHtml(fmtIso(s.last_run_at)) + '</td>'
                + '<td>' + statusLbl + '</td>'
                + '<td class="text-right">'
                + '  <button type="button" class="btn btn-sm btn-info cwm-sched-edit" data-id="' + s.id + '"><i class="fas fa-edit"></i></button> '
                + '  <button type="button" class="btn btn-sm btn-danger cwm-sched-delete" data-id="' + s.id + '"><i class="fas fa-trash"></i></button>'
                + '</td>'
                + '</tr>';
        });
        tbody.innerHTML = html;
        window.__cwmScheduleCache = rows;

        tbody.querySelectorAll('.cwm-sched-edit').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModalForEdit(parseInt(this.getAttribute('data-id'), 10));
            });
        });
        tbody.querySelectorAll('.cwm-sched-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                deleteSchedule(parseInt(this.getAttribute('data-id'), 10));
            });
        });
    }

    function showModal() {
        document.getElementById('cwm-sched-modal').style.display = 'block';
        document.body.classList.add('modal-open');
        updateConditionalFields();
        document.getElementById('cwm-sched-modal-error').style.display = 'none';
    }

    function hideModal() {
        document.getElementById('cwm-sched-modal').style.display = 'none';
        document.body.classList.remove('modal-open');
    }

    function resetModal() {
        document.getElementById('cwm-sched-id').value = '';
        document.getElementById('cwm-sched-corp').value = '';
        document.getElementById('cwm-sched-cadence').value = 'weekly';
        document.getElementById('cwm-sched-dow').value = '1';
        document.getElementById('cwm-sched-dom').value = '1';
        document.getElementById('cwm-sched-moy').value = '1';
        document.getElementById('cwm-sched-hour').value = '3';
        document.getElementById('cwm-sched-minute').value = '0';
        document.getElementById('cwm-sched-enabled').checked = true;
        document.getElementById('cwm-sched-modal-error').style.display = 'none';
    }

    function updateConditionalFields() {
        var cadence = document.getElementById('cwm-sched-cadence').value;
        document.querySelectorAll('.cwm-sched-field').forEach(function (field) {
            var shownFor = (field.getAttribute('data-cwm-sched-show-for') || '').split(',');
            field.style.display = shownFor.indexOf(cadence) >= 0 ? '' : 'none';
        });
    }

    function openModalForAdd() {
        resetModal();
        document.getElementById('cwm-sched-modal-title').textContent = 'Add Schedule';
        // Pre-select the filter-bar corp if one is chosen, otherwise leave
        // it empty so the operator picks.
        var filter = document.getElementById('cwm-sched-corp-filter');
        if (filter && filter.value) {
            document.getElementById('cwm-sched-corp').value = filter.value;
        }
        // Default to Monday 03:30 weekly so adding a schedule lands on the
        // canonical pre-3.0 cadence with no further input.
        document.getElementById('cwm-sched-cadence').value = 'weekly';
        document.getElementById('cwm-sched-dow').value = '1';
        document.getElementById('cwm-sched-hour').value = '3';
        document.getElementById('cwm-sched-minute').value = '30';
        showModal();
    }

    function openModalForEdit(id) {
        var rows = window.__cwmScheduleCache || [];
        var s = rows.find(function (r) { return r.id === id; });
        if (!s) return;
        resetModal();
        document.getElementById('cwm-sched-modal-title').textContent = 'Edit Schedule';
        document.getElementById('cwm-sched-id').value = s.id;
        document.getElementById('cwm-sched-corp').value = s.corporation_id;
        document.getElementById('cwm-sched-cadence').value = s.report_type;
        if (s.day_of_week !== null && s.day_of_week !== undefined) document.getElementById('cwm-sched-dow').value = s.day_of_week;
        if (s.day_of_month !== null && s.day_of_month !== undefined) document.getElementById('cwm-sched-dom').value = s.day_of_month;
        if (s.month_of_year !== null && s.month_of_year !== undefined) document.getElementById('cwm-sched-moy').value = s.month_of_year;
        document.getElementById('cwm-sched-hour').value = s.hour;
        document.getElementById('cwm-sched-minute').value = s.minute;
        document.getElementById('cwm-sched-enabled').checked = !!s.enabled;
        showModal();
    }

    function collectPayload() {
        var cadence = document.getElementById('cwm-sched-cadence').value;
        var payload = {
            corporation_id: parseInt(document.getElementById('cwm-sched-corp').value, 10) || 0,
            report_type:    cadence,
            enabled:        document.getElementById('cwm-sched-enabled').checked ? 1 : 0,
            minute:         parseInt(document.getElementById('cwm-sched-minute').value, 10) || 0,
            hour:           parseInt(document.getElementById('cwm-sched-hour').value, 10) || 0,
            day_of_week:    null,
            day_of_month:   null,
            month_of_year:  null,
        };
        if (cadence === 'weekly') {
            payload.day_of_week = parseInt(document.getElementById('cwm-sched-dow').value, 10);
        }
        if (cadence === 'monthly' || cadence === 'quarterly' || cadence === 'annual') {
            payload.day_of_month = parseInt(document.getElementById('cwm-sched-dom').value, 10);
        }
        if (cadence === 'annual') {
            payload.month_of_year = parseInt(document.getElementById('cwm-sched-moy').value, 10);
        }
        return payload;
    }

    function saveSchedule() {
        var payload = collectPayload();
        var id = document.getElementById('cwm-sched-id').value;
        var isUpdate = id !== '' && id !== '0';
        var url = buildUrl('/corp-wallet-manager/api/report-schedules' + (isUpdate ? ('/' + encodeURIComponent(id)) : ''));
        var method = isUpdate ? 'PUT' : 'POST';

        var errBox = document.getElementById('cwm-sched-modal-error');
        errBox.style.display = 'none';

        if (!payload.corporation_id) {
            errBox.textContent = 'Please select a corporation.';
            errBox.style.display = '';
            return;
        }

        fetch(url, {
            method: method,
            headers: {
                'X-CSRF-TOKEN': csrf(),
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
        .then(function (r) {
            return r.json().then(function (data) { return { status: r.status, data: data }; });
        })
        .then(function (res) {
            var data = res.data || {};
            if (res.status >= 200 && res.status < 300 && data.success) {
                hideModal();
                showMessage('success', isUpdate ? 'Schedule updated.' : 'Schedule created.');
                loadSchedules();
                return;
            }
            var msg = data.message || 'Save failed.';
            if (data.errors) {
                // Surface first validation message in a friendly form.
                Object.keys(data.errors).forEach(function (k) {
                    if (Array.isArray(data.errors[k]) && data.errors[k].length) {
                        msg = data.errors[k][0];
                    }
                });
            }
            errBox.textContent = msg;
            errBox.style.display = '';
        })
        .catch(function () {
            errBox.textContent = 'Save request failed.';
            errBox.style.display = '';
        });
    }

    function deleteSchedule(id) {
        if (!confirm('Delete this schedule? Reports for this cadence will stop firing for this corp.')) {
            return;
        }
        fetch(buildUrl('/corp-wallet-manager/api/report-schedules/' + encodeURIComponent(id)), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrf(),
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (data) {
            if (data && data.success) {
                showMessage('success', 'Schedule deleted.');
                loadSchedules();
            } else {
                showMessage('error', (data && data.message) || 'Delete failed.');
            }
        })
        .catch(function () {
            showMessage('error', 'Delete request failed.');
        });
    }

    function init() {
        var addBtn = document.getElementById('cwm-sched-add-btn');
        if (addBtn) addBtn.addEventListener('click', openModalForAdd);
        var closeBtn = document.getElementById('cwm-sched-modal-close');
        if (closeBtn) closeBtn.addEventListener('click', hideModal);
        var cancelBtn = document.getElementById('cwm-sched-modal-cancel');
        if (cancelBtn) cancelBtn.addEventListener('click', hideModal);
        var saveBtn = document.getElementById('cwm-sched-modal-save');
        if (saveBtn) saveBtn.addEventListener('click', saveSchedule);
        var cadenceSelect = document.getElementById('cwm-sched-cadence');
        if (cadenceSelect) cadenceSelect.addEventListener('change', updateConditionalFields);
        var filter = document.getElementById('cwm-sched-corp-filter');
        if (filter) filter.addEventListener('change', loadSchedules);

        // Lazy: only fetch when the Scheduled Reports section is first opened.
        var loaded = false;
        document.querySelectorAll('[data-cwm-section="scheduled-reports"]').forEach(function (link) {
            link.addEventListener('click', function () {
                if (!loaded) {
                    loaded = true;
                    loadSchedules();
                }
            });
        });
        // If the user landed directly on the scheduled-reports hash, load now.
        if ((window.location.hash || '').replace(/^#/, '') === 'scheduled-reports') {
            loaded = true;
            loadSchedules();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

// Initial load
document.addEventListener('DOMContentLoaded', function() {
    refreshJobStatus();
    loadAccessLogs();
});

// ---- Data Export (v3.0.0) ----
//
// Mirrors Mining Manager's bulk CSV pattern: pick sections, pick a date
// range, pick a format, the controller queues a job and the Recent
// Exports table re-fetches to show the new row. The download links
// are signed (24h validity) so a click on the row works without
// re-auth.
(function () {
    var selectedCorpId = @json($settings['selected_corporation_id'] ?? null);
    var refreshTimer = null;

    function $cwm(id) { return document.getElementById(id); }

    function formatCwmExportDate(iso) {
        if (!iso) return '';
        var d = new Date(iso);
        if (isNaN(d.getTime())) return iso;
        return d.toISOString().slice(0, 16).replace('T', ' ') + ' UTC';
    }

    function exportStatusBadge(status) {
        switch (status) {
            case 'pending':    return '<span class="badge badge-secondary">Pending</span>';
            case 'processing': return '<span class="badge badge-warning"><i class="fas fa-spinner fa-spin"></i> Processing</span>';
            case 'complete':   return '<span class="badge badge-success">Complete</span>';
            case 'failed':     return '<span class="badge badge-danger">Failed</span>';
            default:           return '<span class="badge badge-secondary">' + (status || 'Unknown') + '</span>';
        }
    }

    function renderExportRow(row) {
        var sections = (row.sections || []).map(function (s) {
            return s.replace(/_/g, ' ');
        }).join(', ');

        var actions = '';
        if (row.status === 'complete' && row.download_url) {
            actions += '<a href="' + row.download_url + '" class="btn btn-sm btn-success" title="Download (signed link valid 24h)">'
                + '<i class="fas fa-download"></i></a> ';
        }
        if (row.status === 'failed' && row.error) {
            actions += '<button type="button" class="btn btn-sm btn-outline-warning" '
                + 'onclick="alert(\'Export error:\\n\\n\' + ' + JSON.stringify(row.error) + ')" title="View error detail">'
                + '<i class="fas fa-exclamation-triangle"></i></button> ';
        }
        actions += '<button type="button" class="btn btn-sm btn-outline-danger" data-cwm-export-delete="' + row.id + '" title="Delete">'
            + '<i class="fas fa-trash"></i></button>';

        var period = (row.date_from || '?') + ' to ' + (row.date_to || '?');

        return '<tr>'
            + '<td>' + formatCwmExportDate(row.requested_at) + '</td>'
            + '<td>' + exportStatusBadge(row.status) + '</td>'
            + '<td>' + sections + '</td>'
            + '<td>' + period + '</td>'
            + '<td>' + (row.file_size_human || '') + '</td>'
            + '<td>' + actions + '</td>'
            + '</tr>';
    }

    function reloadExports() {
        var url = '/corp-wallet-manager/api/data-exports';
        if (selectedCorpId) url += '?corporation_id=' + encodeURIComponent(selectedCorpId);

        fetch(url, { headers: { 'Accept': 'application/json' }})
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var body = $cwm('cwm-export-recent-body');
                if (!body) return;
                if (!data.success || !data.exports || data.exports.length === 0) {
                    body.innerHTML = '<tr><td colspan="6" class="text-center text-muted"><em>No recent exports.</em></td></tr>';
                    return;
                }
                body.innerHTML = data.exports.map(renderExportRow).join('');

                // If any row is pending or processing, poll briefly so
                // the UI flips to complete without the operator hitting
                // refresh manually.
                var anyInFlight = data.exports.some(function (e) { return e.status === 'pending' || e.status === 'processing'; });
                if (anyInFlight && !refreshTimer) {
                    refreshTimer = setInterval(reloadExports, 5000);
                } else if (!anyInFlight && refreshTimer) {
                    clearInterval(refreshTimer);
                    refreshTimer = null;
                }
            })
            .catch(function (e) {
                var body = $cwm('cwm-export-recent-body');
                if (body) body.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Failed to load recent exports.</td></tr>';
            });
    }

    function generateExport() {
        var sections = [];
        ['wallet_journal', 'contributions', 'reports', 'alerts', 'anomaly_state'].forEach(function (key) {
            var el = $cwm('cwm-section-' + key);
            if (el && el.checked) sections.push(key);
        });
        if (sections.length === 0) {
            $cwm('cwm-export-form-status').innerHTML = '<span class="text-warning">Pick at least one section.</span>';
            return;
        }
        if (!selectedCorpId) {
            $cwm('cwm-export-form-status').innerHTML = '<span class="text-warning">Pick a corporation under Settings &rarr; General first.</span>';
            return;
        }

        var payload = {
            corporation_id: selectedCorpId,
            sections: sections,
            format: $cwm('cwm-export-format').value || 'zip',
            date_from: $cwm('cwm-export-date-from').value || null,
            date_to:   $cwm('cwm-export-date-to').value || null,
        };

        $cwm('cwm-export-generate').disabled = true;
        $cwm('cwm-export-form-status').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Queueing...';

        fetch('/corp-wallet-manager/api/data-exports', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '',
            },
            body: JSON.stringify(payload),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                $cwm('cwm-export-generate').disabled = false;
                if (!data.success) {
                    $cwm('cwm-export-form-status').innerHTML = '<span class="text-danger">' + (data.message || 'Failed.') + '</span>';
                    return;
                }
                $cwm('cwm-export-form-status').innerHTML = '<span class="text-success">' + (data.message || 'Queued.') + '</span>';
                reloadExports();
            })
            .catch(function (e) {
                $cwm('cwm-export-generate').disabled = false;
                $cwm('cwm-export-form-status').innerHTML = '<span class="text-danger">Network error.</span>';
            });
    }

    function deleteExport(id) {
        if (!confirm('Delete this export?')) return;
        fetch('/corp-wallet-manager/api/data-exports/' + encodeURIComponent(id), {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '',
            },
        })
            .then(function (r) { return r.json(); })
            .then(function () { reloadExports(); })
            .catch(function () { /* swallow */ });
    }

    function wire() {
        var gen = $cwm('cwm-export-generate');
        if (gen) gen.addEventListener('click', generateExport);

        // Event delegation for the dynamically-rendered delete buttons.
        var body = $cwm('cwm-export-recent-body');
        if (body) {
            body.addEventListener('click', function (e) {
                var t = e.target.closest('[data-cwm-export-delete]');
                if (t) deleteExport(t.getAttribute('data-cwm-export-delete'));
            });
        }

        // Lazy-load recent exports when the Data Export tab is opened
        // for the first time, so the API call doesn't fire on every
        // Settings page load.
        var navLink = document.querySelector('[data-cwm-section="data-export"]');
        if (navLink) {
            var loaded = false;
            navLink.addEventListener('click', function () {
                if (!loaded) {
                    reloadExports();
                    loaded = true;
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wire);
    } else {
        wire();
    }
})();
</script>
@endsection
