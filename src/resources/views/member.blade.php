@extends('web::layouts.app')

@section('title', 'CorpWallet Manager - Member View')

@push('head')
<link rel="stylesheet" href="{{ asset('vendor/corp-wallet-manager/css/corp-wallet-manager.css') }}?v=2">
@endpush

@section('content')
<div class="corp-wallet-wrapper">
<div class="row">
    <div class="col-12">
        @if(session('error'))
            <div class="alert alert-danger">
                <i class="fas fa-times"></i> {{ session('error') }}
            </div>
        @endif

        <!-- Corporation Info -->
        <div class="alert alert-info mb-3">
            <i class="fas fa-info-circle"></i> 
            <span id="current-corp-display">Loading corporation settings...</span>
        </div>

        {{-- Three-tab restructure (v3.0.0 follow-up). Corp Wallet is the
             default-active tab so existing user muscle memory of "open the
             page, see corp" stays intact. My Contribution and My Personal
             Wallet defer their first load to a `shown.bs.tab` hook so the
             page doesn't pay for the personal-wallet aggregation cost
             unless the viewer actually opens the tab. --}}
        <div class="card card-dark card-tabs">
            <div class="card-header p-0 pt-1">
        <ul class="nav nav-tabs" id="memberViewTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="mv-corp-wallet-tab" data-toggle="tab"
                   href="#mv-corp-wallet" role="tab" aria-controls="mv-corp-wallet" aria-selected="true">
                    <i class="fas fa-building"></i> Corp Wallet
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="mv-my-contribution-tab" data-toggle="tab"
                   href="#mv-my-contribution" role="tab" aria-controls="mv-my-contribution" aria-selected="false">
                    <i class="fas fa-user-shield"></i> My Contribution
                </a>
            </li>
            @if(!empty($settings['member_show_personal_wallet']))
            <li class="nav-item">
                <a class="nav-link" id="mv-personal-wallet-tab" data-toggle="tab"
                   href="#mv-personal-wallet" role="tab" aria-controls="mv-personal-wallet" aria-selected="false">
                    <i class="fas fa-wallet"></i> My Personal Wallet
                </a>
            </li>
            @endif
            <li class="nav-item ml-auto">
                <button type="button" class="btn btn-tool" onclick="refreshData()" title="Refresh the active tab">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </li>
        </ul>
            </div>
            <div class="card-body">

        <div class="tab-content" id="memberViewTabsContent">

        {{-- ============================================================ --}}
        {{-- TAB 1: Corp Wallet (default active)                           --}}
        {{-- ============================================================ --}}
        <div class="tab-pane fade show active" id="mv-corp-wallet"
             role="tabpanel" aria-labelledby="mv-corp-wallet-tab">

        <!-- Health Status Cards Row -->
        <div class="row" id="health-section">
            <div class="col-md-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon" id="health-icon">
                        <i class="fas fa-heartbeat"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Corporation Health</span>
                        <span class="info-box-number" id="health-status">
                            <i class="fas fa-spinner fa-spin"></i>
                        </span>
                        <div class="progress">
                            <div class="progress-bar" id="health-bar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-info">
                        <i class="fas fa-chart-line"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Monthly Trend</span>
                        <span class="info-box-number" id="trend-indicator">
                            <i class="fas fa-spinner fa-spin"></i>
                        </span>
                        <span class="progress-description" id="trend-description">
                            Calculating...
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-warning">
                        <i class="fas fa-exchange-alt"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Activity Level</span>
                        <span class="info-box-number" id="activity-level">Loading...</span>
                        <span class="progress-description" id="transaction-count">
                            -- transactions
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-purple">
                        <i class="fas fa-trophy"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">Performance Score</span>
                        <span class="info-box-number" id="performance-score">--</span>
                        <span class="progress-description" id="performance-comparison">
                            vs. last month
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Goals Section -->
        <div class="row" id="goals-section">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bullseye"></i> Corporation Goals
                        </h3>
                        <div class="card-tools">
                            <span class="badge badge-primary" id="goals-period">This Month</span>
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- Savings Goal -->
                            <div class="col-md-4">
                                <div class="goal-item">
                                    <h6><i class="fas fa-piggy-bank text-success"></i> Savings Target</h6>
                                    <div class="progress mb-2" style="height: 20px;">
                                        <div class="progress-bar bg-success progress-bar-striped"
                                             id="savings-progress"
                                             role="progressbar"
                                             style="width: 0%">
                                            0%
                                        </div>
                                    </div>
                                    <small class="text-muted" id="savings-details">
                                        0 ISK / Target: Loading...
                                    </small>
                                </div>
                            </div>

                            <!-- Activity Goal -->
                            <div class="col-md-4">
                                <div class="goal-item">
                                    <h6><i class="fas fa-chart-bar text-info"></i> Activity Target</h6>
                                    <div class="progress mb-2" style="height: 20px;">
                                        <div class="progress-bar bg-info progress-bar-striped"
                                             id="activity-progress"
                                             role="progressbar"
                                             style="width: 0%">
                                            0%
                                        </div>
                                    </div>
                                    <small class="text-muted" id="activity-details">
                                        0 / 1000 transactions
                                    </small>
                                </div>
                            </div>

                            <!-- Growth Goal -->
                            <div class="col-md-4">
                                <div class="goal-item">
                                    <h6><i class="fas fa-rocket text-warning"></i> Growth Target</h6>
                                    <div class="progress mb-2" style="height: 20px;">
                                        <div class="progress-bar bg-warning progress-bar-striped"
                                             id="growth-progress"
                                             role="progressbar"
                                             style="width: 0%">
                                            0%
                                        </div>
                                    </div>
                                    <small class="text-muted" id="growth-details">
                                        Current: 0% / Target: 10%
                                    </small>
                                </div>
                            </div>
                        </div>

                        <!-- Stretch Goals -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="text-muted">Stretch Goals</h6>
                                <div id="stretch-goals-list">
                                    <span class="badge badge-outline-secondary mr-2">
                                        <i class="far fa-square"></i> 30 Days Positive Cash Flow
                                    </span>
                                    <span class="badge badge-outline-secondary mr-2">
                                        <i class="far fa-square"></i> Zero Days Below Safety Threshold
                                    </span>
                                    <span class="badge badge-outline-secondary">
                                        <i class="far fa-square"></i> All Divisions Profitable
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <!-- Balance Trend Chart -->
            <div class="col-md-8" id="trend-chart-section">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Balance Trend</h3>
                        <div class="card-tools">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" onclick="updateChartMonths(3)">3M</button>
                                <button type="button" class="btn btn-secondary active" onclick="updateChartMonths(6)">6M</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="updateChartMonths(12)">12M</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="balanceTrendChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <!-- Performance Radar Chart -->
            <div class="col-md-4" id="performance-chart-section">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Performance Metrics</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="performanceRadarChart" height="150"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weekly Activity Pattern + Upcoming Corp Events -->
        <div class="row">
            <div class="col-md-6" id="activity-chart-section">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Weekly Activity Pattern</h3>
                    </div>
                    <div class="card-body">
                        <div style="height: 200px; position: relative;">
                            <canvas id="activityPatternChart"></canvas>
                        </div>
                        <div class="mt-2 text-center">
                            <small class="text-muted">
                                Most Active: <span id="most-active-day" class="font-weight-bold">--</span> |
                                Least Active: <span id="least-active-day" class="font-weight-bold">--</span>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6" id="milestones-section">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="far fa-calendar"></i> Upcoming Corp Events
                        </h3>
                    </div>
                    <div class="card-body">
                        <div id="upcoming-events">
                            <div class="event-item">
                                <i class="far fa-calendar text-muted"></i>
                                <span>Loading events...</span>
                            </div>
                        </div>
                        {{-- milestones-list kept as a hidden anchor for
                             backwards-compat with loadMilestones(); the
                             personal version lives in the My Contribution
                             tab. --}}
                        <div id="milestones-list" style="display:none;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Summary Card -->
        <div class="row" id="summary-section">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Monthly Summary</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="description-block border-right">
                                    <span class="description-percentage" id="balance-change-percent">
                                        <i class="fas fa-caret-up"></i> 0%
                                    </span>
                                    <h5 class="description-header" id="current-balance">0 ISK</h5>
                                    <span class="description-text">CURRENT BALANCE</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="description-block border-right">
                                    <span class="description-percentage" id="activity-change-percent">
                                        <i class="fas fa-caret-left"></i> 0%
                                    </span>
                                    <h5 class="description-header" id="monthly-transactions">0</h5>
                                    <span class="description-text">TRANSACTIONS</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="description-block border-right">
                                    <h5 class="description-header" id="days-positive">0</h5>
                                    <span class="description-text">DAYS POSITIVE</span>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="description-block">
                                    <h5 class="description-header" id="stability-index">0%</h5>
                                    <span class="description-text">STABILITY INDEX</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Contributors Leaderboard (corp-wide ranking lives in the
             Corp Wallet tab so the My Contribution tab can stay focused on
             the viewer's own numbers) -->
        <div class="row" id="leaderboard-section">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-trophy"></i> Top Contributors
                        </h3>
                        <div class="card-tools">
                            <span id="leaderboard-mode-badge" class="badge badge-secondary mr-2">Loading mode</span>
                            <select id="leaderboard-period-select" class="form-control form-control-sm d-inline-block" style="width:auto;">
                                <option value="">This Month</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover" id="leaderboard-table">
                                <thead id="leaderboard-thead">
                                    <tr>
                                        <th style="width: 60px;">Rank</th>
                                        <th>Member</th>
                                        <th class="text-right">ISK</th>
                                        <th class="text-right">% of Corp</th>
                                    </tr>
                                </thead>
                                <tbody id="leaderboard-tbody">
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">
                                            <i class="fas fa-spinner fa-spin"></i> Loading leaderboard...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted" id="leaderboard-mode-explainer">
                            The display mode is set by your corp's administrators.
                        </small>
                    </div>
                </div>
            </div>
        </div>

        </div> {{-- /#mv-corp-wallet --}}

        {{-- ============================================================ --}}
        {{-- TAB 2: My Contribution                                        --}}
        {{-- ============================================================ --}}
        <div class="tab-pane fade" id="mv-my-contribution"
             role="tabpanel" aria-labelledby="mv-my-contribution-tab">

        <!-- My Contribution -->
        <div class="row" id="personal-contribution-section">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-user-shield"></i> My Contribution
                        </h3>
                        <div class="card-tools d-flex align-items-center" style="gap: 8px;">
                            <label for="personal-period-select" class="mb-0 text-muted small">
                                Period:
                            </label>
                            <select id="personal-period-select" class="form-control form-control-sm"
                                    style="width: auto; display: inline-block;">
                                <option value="">This Month</option>
                                <option value="prev">Last Month</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row" id="personal-contribution-body">
                            <div class="col-md-7 personal-headline-col">
                                <div class="personal-headline">
                                    <div class="personal-headline-meta mb-2">
                                        <span class="badge badge-info" id="personal-alt-count">1 character</span>
                                        <span class="text-muted ml-2" id="personal-period-label">This Month</span>
                                    </div>
                                    <h4 class="mb-1" id="personal-main-name">
                                        <i class="fas fa-spinner fa-spin"></i>
                                    </h4>
                                    <p class="text-muted small mb-3" id="personal-aggregation-note">
                                        Aggregated across your character.
                                    </p>
                                    <h2 class="personal-total mb-2" id="personal-total-amount">0 ISK</h2>
                                    <div class="mb-3">
                                        <span id="personal-trend-pill" class="badge badge-secondary">no prior period</span>
                                    </div>
                                    <div id="personal-empty-note" class="text-muted small mb-2" style="display:none;">
                                        <i class="fas fa-info-circle"></i>
                                        No contribution recorded for this period.
                                    </div>
                                </div>
                                <hr>
                                <h6 class="text-muted">Contribution by activity</h6>
                                <div id="personal-bucket-strip" class="d-flex flex-wrap" style="gap: 12px;">
                                    <span class="text-muted">Loading...</span>
                                </div>
                                <p class="mt-2 mb-0 text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    Rolled up across every character you own (including alts in other corps with contribution history here).
                                </p>
                            </div>
                            <div class="col-md-5">
                                <div class="description-block border-left">
                                    <h4 class="description-header text-info mb-1" id="personal-rank-pill" style="font-size: 1.6rem;">Rank --</h4>
                                    <span class="description-text">YOUR RANK</span>
                                    <div id="personal-percentile-wrap" class="mt-1" style="display:none;">
                                        <span class="badge badge-success" id="personal-percentile-badge">--</span>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-6">
                                        <div class="description-block">
                                            <h5 class="description-header" id="personal-lifetime">0 ISK</h5>
                                            <span class="description-text">LIFETIME</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="description-block">
                                            <h5 class="description-header" id="personal-months-active">0</h5>
                                            <span class="description-text">MONTHS ACTIVE</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- MM Tax Compliance + Personal Milestones row.

             The MM card hides at the Blade level when Mining Manager isn't
             installed (`mm_available` false) or when the operator turned
             off `member_show_mm_compliance`. In that case the Personal
             Milestones card expands to col-md-12 so the row doesn't render
             with an empty half. JS-level visibility is still kept as
             defence-in-depth so a operator toggle without a page refresh
             doesn't try to fetch into a missing DOM node. --}}
        @php
            $mmCardVisible = !empty($settings['mm_available']) && !empty($settings['member_show_mm_compliance']);
            $milestoneColClass = $mmCardVisible ? 'col-md-6' : 'col-md-12';
        @endphp
        <div class="row">
            @if($mmCardVisible)
            <div class="col-md-6" id="mm-compliance-section">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-balance-scale"></i> My Tax Compliance
                        </h3>
                        <div class="card-tools">
                            <span class="badge badge-info">Mining Manager</span>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <div id="mm-compliance-loading">
                            <i class="fas fa-spinner fa-spin"></i> Loading compliance...
                        </div>
                        <div id="mm-compliance-empty" style="display:none;">
                            <p class="text-muted mb-0">No mining tax owed this month.</p>
                        </div>
                        <div id="mm-compliance-body" style="display:none;">
                            <div class="progress" style="height: 28px;">
                                <div id="mm-compliance-bar" class="progress-bar bg-success" style="width: 0%;">0%</div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-6">
                                    <div class="description-block">
                                        <h5 class="description-header" id="mm-compliance-owed">0 ISK</h5>
                                        <span class="description-text">OWED</span>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="description-block">
                                        <h5 class="description-header" id="mm-compliance-paid">0 ISK</h5>
                                        <span class="description-text">PAID</span>
                                    </div>
                                </div>
                            </div>
                            <div id="mm-compliance-overdue-warning" class="alert alert-warning mt-2" style="display:none;">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span id="mm-compliance-overdue-text"></span>
                            </div>
                            <button type="button" class="btn btn-link btn-sm mt-2" id="mm-compliance-alt-toggle" style="display:none;">
                                <i class="fas fa-users"></i> Show per-character breakdown
                            </button>
                            <div id="mm-compliance-alt-breakdown" class="text-left mt-2" style="display:none;"></div>
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="{{ $milestoneColClass }}" id="personal-milestones-section">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-medal"></i> My Milestones
                        </h3>
                    </div>
                    <div class="card-body">
                        <div id="personal-next-milestone" class="mb-3">
                            <h6 class="text-muted">Next milestone</h6>
                            <div id="personal-next-milestone-body" class="text-muted">
                                <i class="fas fa-spinner fa-spin"></i> Loading milestones...
                            </div>
                        </div>
                        <h6 class="text-muted">Reached</h6>
                        <div id="personal-milestones-list">
                            <div class="milestone-item">
                                <span class="text-muted">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        </div> {{-- /#mv-my-contribution --}}

        {{-- ============================================================ --}}
        {{-- TAB 3: My Personal Wallet (v3.0.0 follow-up) - new            --}}
        {{-- ============================================================ --}}
        @if(!empty($settings['member_show_personal_wallet']))
        <div class="tab-pane fade" id="mv-personal-wallet"
             role="tabpanel" aria-labelledby="mv-personal-wallet-tab">

        {{-- Period selector + scope note --}}
        <div class="row mb-2">
            <div class="col-12">
                <div class="alert alert-secondary mb-2">
                    <i class="fas fa-info-circle"></i>
                    Aggregates your SeAT character wallets across every character you own (including alts in other corps). Personal wallet only, never corp wallet.
                </div>
                <div class="form-inline">
                    <label for="pw-period-select" class="mr-2 mb-0">Period:</label>
                    <select id="pw-period-select" class="form-control form-control-sm" style="width:auto;">
                        <option value="">This Month</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Top row: 4 KPI info-boxes --}}
        <div class="row" id="pw-kpi-row">
            <div class="col-md-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-arrow-down"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Income</span>
                        <span class="info-box-number" id="pw-income-total"><i class="fas fa-spinner fa-spin"></i></span>
                        <span class="progress-description" id="pw-income-trend">vs. prior period</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-danger"><i class="fas fa-arrow-up"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Expense</span>
                        <span class="info-box-number" id="pw-expense-total"><i class="fas fa-spinner fa-spin"></i></span>
                        <span class="progress-description" id="pw-expense-trend">vs. prior period</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-balance-scale"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Net Flow</span>
                        <span class="info-box-number" id="pw-net-flow"><i class="fas fa-spinner fa-spin"></i></span>
                        <span class="progress-description" id="pw-net-trend">vs. prior period</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-list-ol"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Transactions</span>
                        <span class="info-box-number" id="pw-transaction-count">0</span>
                        <span class="progress-description">in selected period</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Second row: top income sources + top expense sources --}}
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-piggy-bank text-success"></i> Top Income Sources</h3>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0" id="pw-income-sources-table">
                                <thead>
                                    <tr>
                                        <th>Source</th>
                                        <th class="text-right" style="width:120px;">Amount</th>
                                        <th class="text-right" style="width:70px;">Txns</th>
                                    </tr>
                                </thead>
                                <tbody id="pw-income-sources-tbody">
                                    <tr><td colspan="3" class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-receipt text-danger"></i> Top Expense Sources</h3>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0" id="pw-expense-sources-table">
                                <thead>
                                    <tr>
                                        <th>Source</th>
                                        <th class="text-right" style="width:120px;">Amount</th>
                                        <th class="text-right" style="width:70px;">Txns</th>
                                    </tr>
                                </thead>
                                <tbody id="pw-expense-sources-tbody">
                                    <tr><td colspan="3" class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Third row: 6-month balance sparkline --}}
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">6-Month Balance Trend</h3>
                        <div class="card-tools">
                            <small class="text-muted">End-of-month balance summed across your characters</small>
                        </div>
                    </div>
                    <div class="card-body">
                        <div style="height: 220px; position: relative;">
                            <canvas id="pw-balance-sparkline"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Fourth row: biggest single transactions --}}
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-arrow-circle-down text-success"></i> Biggest Income Transactions</h3>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Character</th>
                                        <th>Type</th>
                                        <th class="text-right" style="width:120px;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="pw-income-txns-tbody">
                                    <tr><td colspan="4" class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-arrow-circle-up text-danger"></i> Biggest Expense Transactions</h3>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Character</th>
                                        <th>Type</th>
                                        <th class="text-right" style="width:120px;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody id="pw-expense-txns-tbody">
                                    <tr><td colspan="4" class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Bottom: per-character breakdown --}}
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-users"></i> Per-Character Breakdown</h3>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Character</th>
                                        <th class="text-right" style="width:140px;">Income</th>
                                        <th class="text-right" style="width:140px;">Expense</th>
                                        <th class="text-right" style="width:140px;">Net</th>
                                        <th class="text-right" style="width:90px;">Txns</th>
                                    </tr>
                                </thead>
                                <tbody id="pw-by-char-tbody">
                                    <tr><td colspan="5" class="text-center text-muted"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        </div> {{-- /#mv-personal-wallet --}}
        @endif

        </div> {{-- /.tab-content --}}
            </div> {{-- /.card-body --}}
        </div> {{-- /.card.card-tabs --}}
    </div>
</div>
</div> {{-- /.corp-wallet-wrapper --}}
@stop

@push('javascript')
{{-- Load Chart.js from plugin assets to avoid CSP issues --}}
<script src="{{ asset('corpwalletmanager/js/chart.min.js') }}"></script>
<script>
// Verify Chart.js loaded
if (typeof Chart === 'undefined') {
    console.error('Chart.js failed to load. Assets may not be published correctly.');
}
// Helper function to build URLs - respects current protocol
function buildUrl(path) {
    // Use window.location.origin which includes protocol, host, and port
    // This automatically matches HTTP or HTTPS based on how the user accessed the page
    return window.location.origin + path;
}
    
// Configuration
let config = {
    decimals: {{ config('corpwalletmanager.decimals', 2) }},
    colorActual: "{{ config('corpwalletmanager.color_actual', '#4cafef') }}",
    colorPredicted: "{{ config('corpwalletmanager.color_predicted', '#ef4444') }}",
    corporationId: null,
    refreshInterval: null,
    refreshTimer: null,
    showHealth: {{ $settings['member_show_health'] ? 'true' : 'false' }},
    showBalance: {{ $settings['member_show_balance'] ? 'true' : 'false' }},
    showTrends: {{ $settings['member_show_trends'] ? 'true' : 'false' }},
    showActivity: {{ $settings['member_show_activity'] ? 'true' : 'false' }},
    showGoals: {{ $settings['member_show_goals'] ? 'true' : 'false' }},
    showMilestones: {{ $settings['member_show_milestones'] ? 'true' : 'false' }},
    showPerformance: {{ $settings['member_show_performance'] ? 'true' : 'false' }},
    // Personal contribution + leaderboard surface (v3.0.0)
    showPersonalContribution: {{ $settings['member_show_personal_contribution'] ? 'true' : 'false' }},
    showLeaderboard: {{ $settings['member_show_leaderboard'] ? 'true' : 'false' }},
    showMmCompliance: {{ $settings['member_show_mm_compliance'] ? 'true' : 'false' }},
    showPersonalWallet: {{ ($settings['member_show_personal_wallet'] ?? true) ? 'true' : 'false' }},
    mmAvailable: {{ ($settings['mm_available'] ?? false) ? 'true' : 'false' }},
    leaderboardSize: {{ (int) ($settings['member_leaderboard_size'] ?? 10) }},
};

// Chart instances
let balanceTrendChart = null;
let performanceRadarChart = null;
let activityPatternChart = null;
let personalWalletSparkChart = null;
let currentMonths = 6;

// Per-tab init flag - loaders run on first tab show then again only on
// explicit refresh, so flipping back to a tab doesn't refetch.
let tabInitState = {
    'mv-corp-wallet': false,
    'mv-my-contribution': false,
    'mv-personal-wallet': false,
};

// Load corporation settings
function loadCorporationSettings() {
    fetch(buildUrl('/corp-wallet-manager/api/selected-corporation'))
        .then(response => response.json())
        .then(data => {
            config.corporationId = data.corporation_id;
            config.refreshInterval = data.refresh_interval;

            // Update display with corporation name (FIXED - same as director)
            if (config.corporationId) {
                // Fetch corporation name from the API
                fetch(buildUrl('/corp-wallet-manager/api/corporation-info?corporation_id=' + config.corporationId))
                    .then(response => response.json())
                    .then(corpData => {
                        const displayText = corpData.name 
                            ? `Viewing data for ${corpData.name}`
                            : `Viewing data for Corporation ID: ${config.corporationId}`;
                        document.getElementById('current-corp-display').textContent = displayText;
                    })
                    .catch(error => {
                        // Fallback to ID if name fetch fails
                        document.getElementById('current-corp-display').textContent = 
                            `Viewing data for Corporation ID: ${config.corporationId}`;
                    });
            } else {
                document.getElementById('current-corp-display').textContent = 'Viewing data for all corporations';
            }

            // Setup auto-refresh if enabled
            setupAutoRefresh(data.refresh_minutes);

            // Load data with the correct corporation
            refreshData();
        })
        .catch(error => {
            console.error('Error loading corporation settings:', error);
            document.getElementById('current-corp-display').textContent = 'Error loading corporation settings';
            // Load data anyway with no specific corporation
            refreshData();
        });
}

// Setup auto-refresh
function setupAutoRefresh(refreshMinutes) {
    if (config.refreshTimer) {
        clearInterval(config.refreshTimer);
        config.refreshTimer = null;
    }
    
    if (refreshMinutes && refreshMinutes !== '0') {
        const intervalMs = parseInt(refreshMinutes) * 60 * 1000;
        config.refreshTimer = setInterval(refreshData, intervalMs);
        console.log(`Auto-refresh enabled: every ${refreshMinutes} minutes`);
    }
}

// Add corporation parameter to API calls
function addCorpParam(url) {
    if (config.corporationId) {
        const separator = url.includes('?') ? '&' : '?';
        return url + separator + 'corporation_id=' + config.corporationId;
    }
    return url;
}

// Format ISK values (optionally hide actual amounts)
function formatISK(value, forceShow = false) {
    if (!config.showBalance && !forceShow) {
        return 'Protected';
    }
    
    if (!isFinite(value) || isNaN(value)) {
        return '0 ISK';
    }
    
    const absValue = Math.abs(value);
    if (absValue >= 1000000000000) {
        return (value / 1000000000000).toFixed(1) + 'T ISK';
    } else if (absValue >= 1000000000) {
        return (value / 1000000000).toFixed(1) + 'B ISK';
    } else if (absValue >= 1000000) {
        return (value / 1000000).toFixed(1) + 'M ISK';
    }
    
    return new Intl.NumberFormat('en-US', {
        style: 'decimal',
        minimumFractionDigits: 0,
        maximumFractionDigits: config.decimals
    }).format(value) + ' ISK';
}

// Load Health Status - USING REAL API
function loadHealthStatus() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/member/health')))
        .then(response => response.json())
        .then(data => {
            const healthEl = document.getElementById('health-status');
            const healthIcon = document.getElementById('health-icon');
            const healthBar = document.getElementById('health-bar');
            
            let iconClass = 'bg-success';
            let barClass = 'bg-success';
            let statusText = 'Healthy';
            let barWidth = 80;
            
            if (data.health_score < 40) {
                iconClass = 'bg-danger';
                barClass = 'bg-danger';
                statusText = 'Needs Attention';
                barWidth = data.health_score;
            } else if (data.health_score < 70) {
                iconClass = 'bg-warning';
                barClass = 'bg-warning';
                statusText = 'Stable';
                barWidth = data.health_score;
            } else {
                barWidth = data.health_score;
            }
            
            healthIcon.className = 'info-box-icon ' + iconClass;
            healthBar.className = 'progress-bar ' + barClass;
            healthBar.style.width = barWidth + '%';
            healthEl.innerHTML = `<strong>${statusText}</strong>`;
        })
        .catch(error => {
            console.error('Error loading health status:', error);
            loadBasicHealthFromSummary();
        });
}

// Fallback health calculation from summary
function loadBasicHealthFromSummary() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/summary')))
        .then(response => response.json())
        .then(data => {
            const change = data.change.percent;
            const healthEl = document.getElementById('health-status');
            const healthIcon = document.getElementById('health-icon');
            const healthBar = document.getElementById('health-bar');
            
            let status, iconClass, barClass, barWidth;
            
            if (change > 10) {
                status = 'Excellent';
                iconClass = 'bg-success';
                barClass = 'bg-success';
                barWidth = 90;
            } else if (change > 0) {
                status = 'Healthy';
                iconClass = 'bg-success';
                barClass = 'bg-success';
                barWidth = 70;
            } else if (change > -10) {
                status = 'Stable';
                iconClass = 'bg-warning';
                barClass = 'bg-warning';
                barWidth = 50;
            } else {
                status = 'Needs Attention';
                iconClass = 'bg-danger';
                barClass = 'bg-danger';
                barWidth = 30;
            }
            
            healthIcon.className = 'info-box-icon ' + iconClass;
            healthBar.className = 'progress-bar ' + barClass;
            healthBar.style.width = barWidth + '%';
            healthEl.innerHTML = `<strong>${status}</strong>`;
        });
}

// Load Trend Indicator
function loadTrendIndicator() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/summary')))
        .then(response => response.json())
        .then(data => {
            const trendEl = document.getElementById('trend-indicator');
            const trendDesc = document.getElementById('trend-description');
            const change = data.change.percent;
            
            let icon, color, description;
            
            if (change > 5) {
                icon = '<i class="fas fa-arrow-up"></i>';
                color = 'text-success';
                description = 'Strong Growth';
            } else if (change > 0) {
                icon = '<i class="fas fa-arrow-up"></i>';
                color = 'text-info';
                description = 'Growing';
            } else if (change > -5) {
                icon = '<i class="fas fa-minus"></i>';
                color = 'text-warning';
                description = 'Stable';
            } else {
                icon = '<i class="fas fa-arrow-down"></i>';
                color = 'text-danger';
                description = 'Declining';
            }
            
            trendEl.innerHTML = `<span class="${color}">${icon} ${Math.abs(change).toFixed(1)}%</span>`;
            trendDesc.textContent = description;
        });
}

// Load Activity Level - USING REAL API
function loadActivityLevel() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/member/activity')))
        .then(response => response.json())
        .then(data => {
            document.getElementById('activity-level').textContent = data.level;
            document.getElementById('transaction-count').textContent = `${data.transactions} transactions`;
        })
        .catch(error => {
            console.error('Error loading activity level:', error);
            document.getElementById('activity-level').textContent = 'Unknown';
            document.getElementById('transaction-count').textContent = '-- transactions';
        });
}

// Load Performance Score
function loadPerformanceScore() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/summary')))
        .then(response => response.json())
        .then(data => {
            const current = data.current_month.balance;
            const last = data.last_month.balance;
            const performance = last > 0 ? ((current - last) / last * 100) : 0;
            
            const scoreEl = document.getElementById('performance-score');
            const compEl = document.getElementById('performance-comparison');
            
            if (performance > 0) {
                scoreEl.innerHTML = `<span class="text-success">+${performance.toFixed(1)}%</span>`;
            } else {
                scoreEl.innerHTML = `<span class="text-danger">${performance.toFixed(1)}%</span>`;
            }
            
            compEl.textContent = 'vs. last month';
        });
}

// Load Goals - USING REAL API
function loadGoals() {
    if (!config.showGoals) {
        document.getElementById('goals-section').style.display = 'none';
        return;
    }
    
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/member/goals')))
        .then(response => response.json())
        .then(data => {
            // Update Savings Goal
            const savingsBar = document.getElementById('savings-progress');
            savingsBar.style.width = data.savings.percentage + '%';
            savingsBar.textContent = data.savings.percentage + '%';
            document.getElementById('savings-details').textContent = 
                `${formatISK(data.savings.current)} / Target: ${formatISK(data.savings.target)}`;
            
            // Update Activity Goal
            const activityBar = document.getElementById('activity-progress');
            activityBar.style.width = data.activity.percentage + '%';
            activityBar.textContent = data.activity.percentage + '%';
            document.getElementById('activity-details').textContent = 
                `${data.activity.current} / ${data.activity.target} transactions`;
            
            // Update Growth Goal
            const growthBar = document.getElementById('growth-progress');
            growthBar.style.width = data.growth.percentage + '%';
            growthBar.textContent = data.growth.percentage + '%';
            document.getElementById('growth-details').textContent = 
                `Current: ${data.growth.current}% / Target: ${data.growth.target}%`;
            
            // Update Stretch Goals
            updateStretchGoals(data.stretch_goals);
        })
        .catch(error => {
            console.error('Error loading goals:', error);
        });
}

// Update Stretch Goals
function updateStretchGoals(stretchGoals) {
    const goals = [
        { 
            achieved: stretchGoals.positive_cashflow_30_days, 
            text: '30 Days Positive Cash Flow' 
        },
        { 
            achieved: stretchGoals.zero_days_below_threshold, 
            text: 'Zero Days Below Safety Threshold' 
        },
        { 
            achieved: stretchGoals.all_divisions_profitable, 
            text: 'All Divisions Profitable' 
        }
    ];
    
    let html = '';
    goals.forEach(goal => {
        const icon = goal.achieved ? 'fa-check-square' : 'far fa-square';
        const badgeClass = goal.achieved ? 'badge-success' : 'badge-outline-secondary';
        html += `<span class="badge ${badgeClass} mr-2">
                    <i class="${icon}"></i> ${goal.text}
                </span>`;
    });
    
    document.getElementById('stretch-goals-list').innerHTML = html;
}

// Load Balance Trend Chart
function loadBalanceTrendChart(months = 6) {
    if (!config.showTrends) {
        document.getElementById('trend-chart-section').style.display = 'none';
        return;
    }
    
    fetch(buildUrl(addCorpParam(`/corp-wallet-manager/api/monthly-comparison?months=${months}`)))
        .then(response => response.json())
        .then(data => {
            const canvas = document.getElementById('balanceTrendChart');
            const ctx = canvas.getContext('2d');
            
            if (balanceTrendChart) {
                balanceTrendChart.destroy();
            }

            // Explicit height control to prevent chart growth
            canvas.parentNode.style.height = '400px';
            canvas.parentNode.style.width = '100%';
            
            let displayData = data.data || [];
            if (!config.showBalance) {
                const max = Math.max(...displayData);
                const min = Math.min(...displayData);
                displayData = displayData.map(val => 
                    ((val - min) / (max - min)) * 100
                );
            }
            
            balanceTrendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: config.showBalance ? 'Balance' : 'Trend',
                        data: displayData,
                        borderColor: config.colorActual,
                        backgroundColor: config.colorActual + '20',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (config.showBalance) {
                                        return 'Balance: ' + formatISK(data.data[context.dataIndex]);
                                    } else {
                                        return 'Trend: ' + context.parsed.y.toFixed(1) + '%';
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    if (config.showBalance) {
                                        return formatISK(value);
                                    } else {
                                        return value.toFixed(0) + '%';
                                    }
                                }
                            }
                        }
                    }
                }
            });
        });
}

// Load Performance Radar Chart - USING REAL API
function loadPerformanceRadar() {
    if (!config.showPerformance) {
        document.getElementById('performance-chart-section').style.display = 'none';
        return;
    }
    
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/member/performance-metrics')))
        .then(response => response.json())
        .then(data => {
            const canvas = document.getElementById('performanceRadarChart');
            const ctx = canvas.getContext('2d');
            
            if (performanceRadarChart) {
                performanceRadarChart.destroy();
            }

            // Explicit height control to prevent chart growth
            canvas.parentNode.style.height = '400px';
            canvas.parentNode.style.width = '100%';
            
            performanceRadarChart = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Current',
                        data: Object.values(data.metrics),
                        borderColor: config.colorActual,
                        backgroundColor: config.colorActual + '40',
                        pointBackgroundColor: config.colorActual,
                        pointBorderColor: '#fff',
                        pointHoverBackgroundColor: '#fff',
                        pointHoverBorderColor: config.colorActual
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                stepSize: 25
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading performance radar:', error);
        });
}

// Load Activity Pattern Chart - USING REAL API
function loadActivityPattern() {
    if (!config.showActivity) {
        document.getElementById('activity-chart-section').style.display = 'none';
        return;
    }
    
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/member/weekly-pattern')))
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('activityPatternChart').getContext('2d');
            
            if (activityPatternChart) {
                activityPatternChart.destroy();
            }
            
            activityPatternChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Activity Level',
                        data: data.activity,
                        backgroundColor: data.activity.map(val => 
                            val > 80 ? '#10b981' : val > 50 ? '#3b82f6' : '#6b7280'
                        ),
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Activity: ' + context.parsed.y + '%';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
            
            // Update most/least active days
            document.getElementById('most-active-day').textContent = data.best_day;
            document.getElementById('least-active-day').textContent = data.worst_day;
        })
        .catch(error => {
            console.error('Error loading activity pattern:', error);
        });
}

// Load Milestones - USING REAL API
function loadMilestones() {
    if (!config.showMilestones) {
        document.getElementById('milestones-section').style.display = 'none';
        return;
    }
    
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/member/milestones')))
        .then(response => response.json())
        .then(data => {
            // Update milestones
            let milestonesHtml = '';
            if (data.milestones.length > 0) {
                data.milestones.forEach(milestone => {
                    milestonesHtml += `
                        <div class="milestone-item mb-2">
                            <i class="fas ${milestone.icon}"></i>
                            <span>${milestone.text}</span>
                        </div>
                    `;
                });
            } else {
                milestonesHtml = `
                    <div class="milestone-item mb-2">
                        <i class="fas fa-info-circle text-muted"></i>
                        <span>No recent achievements</span>
                    </div>
                `;
            }
            document.getElementById('milestones-list').innerHTML = milestonesHtml;
            
            // Update events
            let eventsHtml = '';
            if (data.events.length > 0) {
                data.events.forEach(event => {
                    eventsHtml += `
                        <div class="event-item mb-2">
                            <i class="far ${event.icon} text-muted"></i>
                            <span>${event.text}</span>
                        </div>
                    `;
                });
            } else {
                eventsHtml = `
                    <div class="event-item mb-2">
                        <i class="far fa-calendar text-muted"></i>
                        <span>No upcoming events</span>
                    </div>
                `;
            }
            document.getElementById('upcoming-events').innerHTML = eventsHtml;
        })
        .catch(error => {
            console.error('Error loading milestones:', error);
        });
}

// Load Monthly Summary - USING REAL API
function loadMonthlySummary() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/member/monthly-summary')))
        .then(response => response.json())
        .then(data => {
            // Balance
            const balanceEl = document.getElementById('current-balance');
            const balanceChangeEl = document.getElementById('balance-change-percent');
            
            if (config.showBalance) {
                balanceEl.textContent = formatISK(data.current_balance);
            } else {
                balanceEl.textContent = 'Protected';
            }
            
            const balanceChange = data.balance_change_percent;
            if (balanceChange > 0) {
                balanceChangeEl.innerHTML = `<i class="fas fa-caret-up text-success"></i> ${Math.abs(balanceChange).toFixed(1)}%`;
                balanceChangeEl.className = 'description-percentage text-success';
            } else if (balanceChange < 0) {
                balanceChangeEl.innerHTML = `<i class="fas fa-caret-down text-danger"></i> ${Math.abs(balanceChange).toFixed(1)}%`;
                balanceChangeEl.className = 'description-percentage text-danger';
            } else {
                balanceChangeEl.innerHTML = `<i class="fas fa-caret-left text-muted"></i> 0%`;
                balanceChangeEl.className = 'description-percentage text-muted';
            }
            
            // Transactions
            document.getElementById('monthly-transactions').textContent = data.monthly_transactions.toLocaleString();
            
            // Activity change
            const activityChange = data.activity_change_percent;
            const activityChangeEl = document.getElementById('activity-change-percent');
            if (activityChange > 0) {
                activityChangeEl.innerHTML = `<i class="fas fa-caret-up text-success"></i> ${Math.abs(activityChange).toFixed(1)}%`;
            } else if (activityChange < 0) {
                activityChangeEl.innerHTML = `<i class="fas fa-caret-down text-danger"></i> ${Math.abs(activityChange).toFixed(1)}%`;
            } else {
                activityChangeEl.innerHTML = `<i class="fas fa-caret-left text-muted"></i> 0%`;
            }
            
            // Days positive
            document.getElementById('days-positive').textContent = data.days_positive;
            
            // Stability index
            document.getElementById('stability-index').textContent = data.stability_index + '%';
        })
        .catch(error => {
            console.error('Error loading monthly summary:', error);
            // Fallback to basic summary if enhanced endpoint fails
            loadBasicMonthlySummary();
        });
}

// Fallback for monthly summary
function loadBasicMonthlySummary() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/summary')))
        .then(response => response.json())
        .then(data => {
            const balance = data.current_month.balance;
            const balanceChange = data.change.percent;
            
            const balanceEl = document.getElementById('current-balance');
            const balanceChangeEl = document.getElementById('balance-change-percent');
            
            if (config.showBalance) {
                balanceEl.textContent = formatISK(balance);
            } else {
                balanceEl.textContent = 'Protected';
            }
            
            if (balanceChange > 0) {
                balanceChangeEl.innerHTML = `<i class="fas fa-caret-up text-success"></i> ${Math.abs(balanceChange).toFixed(1)}%`;
                balanceChangeEl.className = 'description-percentage text-success';
            } else if (balanceChange < 0) {
                balanceChangeEl.innerHTML = `<i class="fas fa-caret-down text-danger"></i> ${Math.abs(balanceChange).toFixed(1)}%`;
                balanceChangeEl.className = 'description-percentage text-danger';
            } else {
                balanceChangeEl.innerHTML = `<i class="fas fa-caret-left text-muted"></i> 0%`;
                balanceChangeEl.className = 'description-percentage text-muted';
            }
        });
}

// Update chart time range
function updateChartMonths(months) {
    currentMonths = months;
    
    document.querySelectorAll('.btn-group .btn').forEach(btn => {
        btn.classList.remove('btn-secondary', 'active');
        btn.classList.add('btn-outline-secondary');
    });
    event.target.classList.remove('btn-outline-secondary');
    event.target.classList.add('btn-secondary', 'active');
    
    loadBalanceTrendChart(months);
}

// Hide sections based on settings
function applySectionVisibility() {
    if (!config.showHealth) {
        document.getElementById('health-section').style.display = 'none';
    }
    if (!config.showGoals) {
        document.getElementById('goals-section').style.display = 'none';
    }
    if (!config.showMilestones) {
        const corpMs = document.getElementById('milestones-section');
        if (corpMs) corpMs.style.display = 'none';
        const persMs = document.getElementById('personal-milestones-section');
        if (persMs) persMs.style.display = 'none';
    }
    if (!config.showActivity) {
        document.getElementById('activity-chart-section').style.display = 'none';
    }
    if (!config.showTrends) {
        document.getElementById('trend-chart-section').style.display = 'none';
    }
    if (!config.showPerformance) {
        document.getElementById('performance-chart-section').style.display = 'none';
    }

    // Personal contribution + leaderboard + MM compliance (v3.0.0).
    if (!config.showPersonalContribution) {
        const sec = document.getElementById('personal-contribution-section');
        if (sec) sec.style.display = 'none';
    }
    if (!config.showLeaderboard) {
        const sec = document.getElementById('leaderboard-section');
        if (sec) sec.style.display = 'none';
    }
    // MM compliance card is now hidden at the Blade level when MM is
    // absent or the toggle is off; this JS guard is defence-in-depth so
    // a flipped setting without a page reload doesn't try to fetch into
    // a missing DOM node. The Blade also expands the milestones col to
    // col-md-12 when MM hides, so we don't have to touch the layout here.
    if (!config.showMmCompliance || !config.mmAvailable) {
        const sec = document.getElementById('mm-compliance-section');
        if (sec) sec.style.display = 'none';
    }

    if (!config.showBalance) {
        const balanceElements = document.querySelectorAll('[id*="balance"], [id*="Balance"]');
        balanceElements.forEach(el => {
            if (el.textContent.includes('ISK')) {
                el.textContent = 'Protected';
            }
        });
    }
}

// Populate the leaderboard period dropdown with the trailing 6 months
// (current month first). Hooks the change event to reload the table.
function setupLeaderboardPeriodSelect() {
    const sel = document.getElementById('leaderboard-period-select');
    if (!sel) return;
    // Wipe + populate.
    sel.innerHTML = '';
    const now = new Date();
    for (let i = 0; i < 6; i++) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const ym = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        const label = d.toLocaleString('en-US', { month: 'short', year: 'numeric' }) +
            (i === 0 ? ' (this month)' : '');
        const opt = document.createElement('option');
        opt.value = ym;
        opt.textContent = label;
        sel.appendChild(opt);
    }
    sel.addEventListener('change', loadMemberLeaderboard);
}

// Log access (for tracking)
function logAccess() {
    fetch(buildUrl('/corp-wallet-manager/api/member/log-access'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({
            corporation_id: config.corporationId,
            view: 'member',
            timestamp: new Date().toISOString()
        })
    }).catch(error => {
        console.log('Access logging failed:', error);
    });
}

// ============================================================
// Personal contribution + leaderboard + MM compliance + personal
// milestones (v3.0.0)
// ============================================================

function formatPct(value, decimals = 1) {
    if (value === null || value === undefined || !isFinite(value)) {
        return '--';
    }
    return value.toFixed(decimals) + '%';
}

function ordinalRank(rank) {
    if (rank === null || rank === undefined) return '--';
    return '#' + rank;
}

function ensureMuted(el, text) {
    if (!el) return;
    el.innerHTML = '<span class="text-muted">' + text + '</span>';
}

// Translate the period selector's value into a YYYY-MM string the
// /api/personal-contribution endpoint accepts. Empty string = current
// month (server default), 'prev' = last calendar month. Future
// values can be added without touching the endpoint.
function personalPeriodValue() {
    const sel = document.getElementById('personal-period-select');
    const raw = sel && sel.value ? sel.value : '';
    if (!raw) return '';
    if (raw === 'prev') {
        const now = new Date();
        const d = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    }
    // Caller passed a literal YYYY-MM (kept as an escape hatch).
    return raw;
}

// Human label for the chip beside the character-count badge. Mirrors
// the dropdown so the eye doesn't have to look twice.
function personalPeriodLabel() {
    const sel = document.getElementById('personal-period-select');
    const raw = sel && sel.value ? sel.value : '';
    if (!raw) return 'This Month';
    if (raw === 'prev') return 'Last Month';
    return raw;
}

// Bind the period selector once. Subsequent changes call
// loadPersonalContribution which re-fetches with the new period.
function setupPersonalPeriodSelect() {
    const sel = document.getElementById('personal-period-select');
    if (!sel || sel.dataset.bound === '1') return;
    sel.dataset.bound = '1';
    sel.addEventListener('change', loadPersonalContribution);
}

function loadPersonalContribution(force) {
    if (!config.showPersonalContribution) {
        const sec = document.getElementById('personal-contribution-section');
        if (sec) sec.style.display = 'none';
        return;
    }

    // Resolve every node up-front so the populator can never partially
    // render. Missing nodes (someone trimmed the template) silently
    // no-op instead of throwing.
    const nameEl    = document.getElementById('personal-main-name');
    const totalEl   = document.getElementById('personal-total-amount');
    const trendEl   = document.getElementById('personal-trend-pill');
    const altEl     = document.getElementById('personal-alt-count');
    const noteEl    = document.getElementById('personal-aggregation-note');
    const labelEl   = document.getElementById('personal-period-label');
    const emptyEl   = document.getElementById('personal-empty-note');
    const rankEl    = document.getElementById('personal-rank-pill');
    const pctEl     = document.getElementById('personal-percentile-badge');
    const lifeEl    = document.getElementById('personal-lifetime');
    const monthsEl  = document.getElementById('personal-months-active');
    const bucketEl  = document.getElementById('personal-bucket-strip');

    // Set the period chip immediately so the dropdown change feels
    // responsive even before the fetch returns.
    if (labelEl) labelEl.textContent = personalPeriodLabel();

    let base = addCorpParam('/corp-wallet-manager/api/personal-contribution');
    const period = personalPeriodValue();
    if (period) {
        base += (base.includes('?') ? '&' : '?') + 'period=' + encodeURIComponent(period);
    }
    // Tab-nav refresh button bypasses the server-side Redis cache.
    if (force) {
        base += (base.includes('?') ? '&' : '?') + 'refresh=1';
    }
    const url = buildUrl(base);

    fetch(url)
        .then(r => r.json())
        .then(data => {
            data = data || {};

            // Normalise shape so every branch downstream can read fields
            // without short-circuit checks.
            const main       = data.main_character || null;
            const altCount   = Math.max(0, parseInt(data.alt_count || 0, 10));
            const charCount  = altCount + 1;
            const totalRaw   = parseFloat(data.total_amount || 0);
            const lifeRaw    = parseFloat(data.lifetime_total || 0);
            const monthsRaw  = parseInt(data.months_active || 0, 10);
            const trendPct   = (data.trend_pct === null || data.trend_pct === undefined)
                ? null : parseFloat(data.trend_pct);

            // Each UI block wrapped in try/catch so one bad block doesn't
            // leave the rest of the card stuck on Blade defaults. Without
            // this, an exception in the rank/percentile/lifetime code
            // (further down) would leave the bucket strip permanently
            // stuck on its initial "Loading..." placeholder.
            const safe = (label, fn) => {
                try { fn(); } catch (e) { /* swallow per-block; card stays usable */ }
            };

            // Character count chip - always rendered.
            safe('alt-count chip', () => {
                if (altEl) {
                    altEl.textContent = charCount + (charCount === 1 ? ' character' : ' characters');
                }
            });

            // Main-name h4 - prominent.
            safe('main name', () => {
                if (nameEl) {
                    if (main && main.name) {
                        nameEl.textContent = main.name;
                    } else {
                        nameEl.textContent = 'No linked characters';
                    }
                }
            });

            // Aggregation note.
            safe('aggregation note', () => {
                if (noteEl) {
                    if (altCount > 0) {
                        noteEl.textContent = 'Aggregated across your main character plus ' +
                            altCount + (altCount === 1 ? ' alt.' : ' alts.');
                    } else {
                        noteEl.textContent = 'Aggregated across your character.';
                    }
                }
            });

            // Cumulative headline.
            safe('total headline', () => {
                if (totalEl) totalEl.textContent = formatISK(totalRaw, true);
            });

            // Trend pill.
            safe('trend pill', () => {
                if (trendEl) {
                    if (trendPct === null) {
                        trendEl.className = 'badge badge-secondary';
                        trendEl.textContent = 'no prior period';
                    } else if (trendPct > 0) {
                        trendEl.className = 'badge badge-success';
                        trendEl.innerHTML = '<i class="fas fa-arrow-up"></i> ' +
                            Math.abs(trendPct).toFixed(1) + '% vs prior';
                    } else if (trendPct < 0) {
                        trendEl.className = 'badge badge-danger';
                        trendEl.innerHTML = '<i class="fas fa-arrow-down"></i> ' +
                            Math.abs(trendPct).toFixed(1) + '% vs prior';
                    } else {
                        trendEl.className = 'badge badge-secondary';
                        trendEl.textContent = 'flat vs prior';
                    }
                }
            });

            // Empty-period note.
            safe('empty note', () => {
                if (emptyEl) {
                    emptyEl.style.display = (totalRaw <= 0) ? '' : 'none';
                }
            });

            // Rank headline (large, prominent).
            safe('rank headline', () => {
                if (rankEl) {
                    if (data.rank === null || data.rank === undefined) {
                        rankEl.textContent = 'Unranked';
                    } else {
                        const total = parseInt(data.rank_total || 0, 10);
                        rankEl.textContent = '#' + data.rank + ' of ' + (total > 0 ? total : '--');
                    }
                }
            });
            // Percentile sub-label - only meaningful when the corp has enough
            // contributors that a percentile actually means something. With 3
            // contributors, "Top 1%" is nonsense — the smallest granular
            // bucket is 33%. Threshold of 10 contributors is the cutoff;
            // below that, hide the percentile entirely (clean rank-only
            // display) rather than show misleading math.
            safe('percentile sub-label', () => {
                const wrap = document.getElementById('personal-percentile-wrap');
                if (!wrap) return;
                const total = parseInt(data.rank_total || 0, 10);
                const rank  = parseInt(data.rank || 0, 10);
                if (total < 10 || rank <= 0 || data.percentile === null || data.percentile === undefined) {
                    wrap.style.display = 'none';
                    return;
                }
                // True percentile (rank as % of total). With rank=1/total=20,
                // that's top 5%. Round up so we don't ever claim a fractionally
                // better rank than the math supports.
                const truePct = Math.max(1, Math.ceil((rank / total) * 100));
                if (pctEl) pctEl.textContent = 'Top ' + truePct + '%';
                wrap.style.display = '';
            });
            // Lifetime + months active.
            safe('lifetime + months', () => {
                if (lifeEl) lifeEl.textContent = formatISK(lifeRaw, true);
                if (monthsEl) monthsEl.textContent = String(monthsRaw);
            });

            // Bucket strip - render FIRST so it always updates even if
            // any subsequent field handler throws. Wrap in try/catch as
            // a final safety net.
            try {
                if (bucketEl) {
                    const buckets = data.by_bucket || {};
                    const order = [
                        ['ratting', 'Ratting', '#10b981'],
                        ['mission', 'Mission', '#3b82f6'],
                        ['industry', 'Industry', '#f59e0b'],
                        ['tax_payment', 'Tax', '#a855f7'],
                        ['donation_voluntary', 'Donation', '#06b6d4'],
                        ['withdrawal', 'Withdrawal', '#ef4444'],
                    ];
                    const values = order.map(([k]) => Math.abs(parseFloat(buckets[k] || 0)));
                    const allZero = values.every(v => v <= 0);
                    if (allZero) {
                        bucketEl.innerHTML = '<span class="text-muted">No contribution buckets active this period.</span>';
                    } else {
                        const max = Math.max.apply(null, values.concat([1]));
                        let html = '';
                        order.forEach(([k, label, color], i) => {
                            const v = values[i];
                            const pct = max > 0 ? Math.round((v / max) * 100) : 0;
                            html += '<div style="min-width: 92px;">' +
                                '<small class="text-muted d-block">' + label + '</small>' +
                                '<div style="background:#1f2937; height:6px; border-radius:3px;">' +
                                    '<div style="background:' + color + '; height:6px; width:' + pct + '%; border-radius:3px;"></div>' +
                                '</div>' +
                                '<small class="text-muted">' + formatISK(v, true) + '</small>' +
                            '</div>';
                        });
                        bucketEl.innerHTML = html;
                    }
                }
            } catch (e) {
                if (bucketEl) bucketEl.innerHTML = '<span class="text-muted">Bucket render error.</span>';
            }
        })
        .catch(err => {
            // Don't blow away the whole card - leave the structure in place
            // and surface a muted error inline so the period selector is
            // still usable.
            if (nameEl) nameEl.textContent = 'Unable to load contribution';
            if (totalEl) totalEl.textContent = '--';
            if (bucketEl) {
                bucketEl.innerHTML = '<span class="text-muted">Unable to load. ' +
                    (err && err.message ? '(' + err.message + ')' : '') + '</span>';
            }
        });
}

function loadMemberLeaderboard() {
    if (!config.showLeaderboard) {
        const sec = document.getElementById('leaderboard-section');
        if (sec) sec.style.display = 'none';
        return;
    }
    const periodSel = document.getElementById('leaderboard-period-select');
    const periodVal = periodSel && periodSel.value ? periodSel.value : '';
    let base = addCorpParam('/corp-wallet-manager/api/member-leaderboard');
    if (periodVal) {
        base += (base.includes('?') ? '&' : '?') + 'period=' + encodeURIComponent(periodVal);
    }
    const url = buildUrl(base);

    fetch(url)
        .then(r => r.json())
        .then(data => {
            const modeBadge = document.getElementById('leaderboard-mode-badge');
            const explainer = document.getElementById('leaderboard-mode-explainer');
            const thead = document.getElementById('leaderboard-thead');
            const tbody = document.getElementById('leaderboard-tbody');

            const mode = (data && data.mode) || 'isk_visible';
            if (modeBadge) {
                if (mode === 'isk_visible') modeBadge.textContent = 'Showing ISK';
                else if (mode === 'percentage') modeBadge.textContent = 'Showing %';
                else modeBadge.textContent = 'Ranks only';
                modeBadge.className = 'badge ' + (mode === 'rank_only' ? 'badge-warning' : 'badge-info') + ' mr-2';
            }
            if (explainer) {
                if (mode === 'isk_visible') explainer.textContent = 'Display mode: ISK Visible. Your corp shows actual contribution amounts.';
                else if (mode === 'percentage') explainer.textContent = 'Display mode: Percentage. Your corp shows shares of corp total, not ISK amounts.';
                else explainer.textContent = 'Display mode: Rank Only. Your corp hides contribution amounts.';
            }

            // Build column header set
            if (thead) {
                let head = '<tr><th style="width:60px;">Rank</th><th>Member</th>';
                if (mode === 'isk_visible') {
                    head += '<th class="text-right">ISK</th><th class="text-right">% of Corp</th>';
                } else if (mode === 'percentage') {
                    head += '<th class="text-right">% of Corp</th>';
                }
                head += '</tr>';
                thead.innerHTML = head;
            }

            const renderRow = (row) => {
                let html = '<tr' + (row.is_viewer ? ' style="background:rgba(59,130,246,0.12); border-left: 3px solid #3b82f6;"' : '') + '>';
                html += '<td><strong>#' + row.rank + '</strong></td>';
                html += '<td>' + escapeHtml(row.character_name);
                if (row.alt_count > 0) {
                    html += ' <span class="badge badge-info ml-1">+' + row.alt_count + (row.alt_count === 1 ? ' alt' : ' alts') + '</span>';
                }
                if (row.is_viewer) {
                    html += ' <span class="badge badge-primary ml-1">You</span>';
                }
                html += '</td>';
                if (mode === 'isk_visible') {
                    html += '<td class="text-right">' + (row.total_amount !== null ? formatISK(row.total_amount, true) : 'Hidden') + '</td>';
                    html += '<td class="text-right">' + (row.pct_of_corp !== null ? row.pct_of_corp.toFixed(1) + '%' : '--') + '</td>';
                } else if (mode === 'percentage') {
                    html += '<td class="text-right">' + (row.pct_of_corp !== null ? row.pct_of_corp.toFixed(1) + '%' : '--') + '</td>';
                }
                html += '</tr>';
                return html;
            };

            const top = (data && data.top) || [];
            if (!tbody) return;
            if (top.length === 0) {
                const colspan = mode === 'isk_visible' ? 4 : (mode === 'percentage' ? 3 : 2);
                tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="text-center text-muted">No contributions recorded this period.</td></tr>';
                return;
            }

            let html = '';
            top.forEach(row => { html += renderRow(row); });

            if (data.viewer_row && !data.viewer_in_top) {
                const colspan = mode === 'isk_visible' ? 4 : (mode === 'percentage' ? 3 : 2);
                html += '<tr><td colspan="' + colspan + '" class="text-center text-muted" style="border-top: 2px dashed #374151;">Your position</td></tr>';
                html += renderRow(data.viewer_row);
            }
            tbody.innerHTML = html;
        })
        .catch(err => {
            console.error('Error loading leaderboard:', err);
            const tbody = document.getElementById('leaderboard-tbody');
            if (tbody) tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Unable to load leaderboard.</td></tr>';
        });
}

function loadPersonalMmCompliance() {
    if (!config.showMmCompliance || !config.mmAvailable) {
        const sec = document.getElementById('mm-compliance-section');
        if (sec) sec.style.display = 'none';
        return;
    }
    const url = buildUrl(addCorpParam('/corp-wallet-manager/api/personal-mm-compliance'));
    fetch(url)
        .then(r => r.json())
        .then(data => {
            const loading = document.getElementById('mm-compliance-loading');
            const empty = document.getElementById('mm-compliance-empty');
            const body = document.getElementById('mm-compliance-body');
            const bar = document.getElementById('mm-compliance-bar');
            const owedEl = document.getElementById('mm-compliance-owed');
            const paidEl = document.getElementById('mm-compliance-paid');
            const warnEl = document.getElementById('mm-compliance-overdue-warning');
            const warnText = document.getElementById('mm-compliance-overdue-text');
            const altBtn = document.getElementById('mm-compliance-alt-toggle');
            const altDiv = document.getElementById('mm-compliance-alt-breakdown');

            if (loading) loading.style.display = 'none';
            if (!data || data.mm_available === false) {
                const sec = document.getElementById('mm-compliance-section');
                if (sec) sec.style.display = 'none';
                return;
            }
            const owed = parseFloat(data.amount_owed || 0);
            const paid = parseFloat(data.amount_paid || 0);
            const compliance = data.compliance_pct;

            if (owed <= 0) {
                if (empty) empty.style.display = '';
                if (body) body.style.display = 'none';
                return;
            }
            if (empty) empty.style.display = 'none';
            if (body) body.style.display = '';

            const pct = compliance === null ? 0 : Math.max(0, Math.min(100, compliance));
            if (bar) {
                bar.style.width = pct + '%';
                bar.textContent = pct.toFixed(1) + '%';
                bar.className = 'progress-bar ' + (pct >= 80 ? 'bg-success' : (pct >= 50 ? 'bg-warning' : 'bg-danger'));
            }
            if (owedEl) owedEl.textContent = formatISK(owed, true);
            if (paidEl) paidEl.textContent = formatISK(paid, true);

            const consecutive = data.consecutive_overdue_periods || 0;
            if (warnEl && warnText) {
                if (consecutive > 0) {
                    warnText.textContent = 'You have been short on mining tax for the past ' +
                        consecutive + (consecutive === 1 ? ' period' : ' periods') + '.';
                    warnEl.style.display = '';
                } else {
                    warnEl.style.display = 'none';
                }
            }

            const breakdown = data.breakdown_by_character || [];
            if (altBtn && altDiv) {
                if (breakdown.length > 1) {
                    altBtn.style.display = '';
                    altBtn.onclick = function () {
                        if (altDiv.style.display === 'none') {
                            let h = '<small class="text-muted d-block mb-1">Per-character breakdown:</small><ul class="list-unstyled mb-0">';
                            breakdown.forEach(b => {
                                const pctTxt = b.compliance_pct === null ? '--' : b.compliance_pct.toFixed(0) + '%';
                                h += '<li><strong>' + escapeHtml(b.character_name) + ':</strong> ' +
                                    formatISK(b.amount_paid, true) + ' / ' + formatISK(b.amount_owed, true) +
                                    ' <span class="text-muted">(' + pctTxt + ')</span></li>';
                            });
                            h += '</ul>';
                            altDiv.innerHTML = h;
                            altDiv.style.display = '';
                            altBtn.innerHTML = '<i class="fas fa-users"></i> Hide per-character breakdown';
                        } else {
                            altDiv.style.display = 'none';
                            altBtn.innerHTML = '<i class="fas fa-users"></i> Show per-character breakdown';
                        }
                    };
                } else {
                    altBtn.style.display = 'none';
                    altDiv.style.display = 'none';
                }
            }
        })
        .catch(err => {
            console.error('Error loading MM compliance:', err);
            const sec = document.getElementById('mm-compliance-section');
            if (sec) sec.style.display = 'none';
        });
}

function loadPersonalMilestones() {
    if (!config.showMilestones) {
        const sec = document.getElementById('personal-milestones-section');
        if (sec) sec.style.display = 'none';
        return;
    }
    const url = buildUrl(addCorpParam('/corp-wallet-manager/api/personal-milestones'));
    fetch(url)
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('personal-milestones-list');
            const nextBody = document.getElementById('personal-next-milestone-body');

            if (nextBody) {
                if (!data || !data.next_milestone) {
                    nextBody.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> All ladder rungs reached.</span>';
                } else {
                    const nm = data.next_milestone;
                    const pct = Math.max(0, Math.min(100, nm.pct_to_next || 0));
                    nextBody.innerHTML =
                        '<strong>' + escapeHtml(nm.target) + '</strong> ' +
                        '<span class="text-muted">(' + pct.toFixed(1) + '% of the way there)</span>' +
                        '<div class="progress mt-1" style="height:10px;">' +
                            '<div class="progress-bar bg-info" style="width:' + pct + '%;"></div>' +
                        '</div>';
                }
            }

            if (list) {
                const reached = (data && data.reached) || [];
                if (reached.length === 0) {
                    list.innerHTML = '<div class="milestone-item"><span class="text-muted">No milestones reached yet. Keep contributing.</span></div>';
                } else {
                    let html = '';
                    reached.forEach(m => {
                        html += '<div class="milestone-item mb-1">' +
                            '<i class="fas fa-trophy text-warning"></i> ' +
                            '<strong>' + escapeHtml(m.milestone) + '</strong> ' +
                            '<span class="text-muted">' + escapeHtml(m.character_name) + ' on ' + escapeHtml(m.crossed_at) + '</span>' +
                        '</div>';
                    });
                    list.innerHTML = html;
                }
            }
        })
        .catch(err => {
            console.error('Error loading personal milestones:', err);
            const list = document.getElementById('personal-milestones-list');
            ensureMuted(list, 'Unable to load milestones.');
        });
}

function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ============================================================
// My Personal Wallet tab (v3.0.0 follow-up). Aggregates the
// viewer's SeAT character_wallet_journals across every
// character they own (no corp filter, personal wallet is
// independent of corp affiliation).
// ============================================================

function setupPersonalWalletPeriodSelect() {
    const sel = document.getElementById('pw-period-select');
    if (!sel) return;
    sel.innerHTML = '';
    const now = new Date();
    for (let i = 0; i < 12; i++) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const ym = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        const label = d.toLocaleString('en-US', { month: 'short', year: 'numeric' }) +
            (i === 0 ? ' (this month)' : '');
        const opt = document.createElement('option');
        opt.value = ym;
        opt.textContent = label;
        sel.appendChild(opt);
    }
    sel.addEventListener('change', loadPersonalWallet);
}

function renderTrendBadge(el, pct) {
    if (!el) return;
    if (pct === null || pct === undefined || !isFinite(pct)) {
        el.textContent = 'no prior period';
        el.className = 'progress-description';
        return;
    }
    if (pct > 0) {
        el.innerHTML = '<i class="fas fa-caret-up text-success"></i> ' + Math.abs(pct).toFixed(1) + '% vs prior';
    } else if (pct < 0) {
        el.innerHTML = '<i class="fas fa-caret-down text-danger"></i> ' + Math.abs(pct).toFixed(1) + '% vs prior';
    } else {
        el.innerHTML = '<i class="fas fa-caret-left text-muted"></i> flat vs prior';
    }
}

function renderRefTypeRows(tbody, rows, totalAmount, barClass) {
    if (!tbody) return;
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No activity for this period.</td></tr>';
        return;
    }
    let html = '';
    rows.forEach(row => {
        const pct = totalAmount > 0 ? Math.round((row.amount / totalAmount) * 100) : 0;
        html += '<tr>' +
            '<td>' +
                '<div>' + escapeHtml(row.label) + '</div>' +
                '<div class="progress" style="height:4px; margin-top:2px;">' +
                    '<div class="progress-bar ' + barClass + '" style="width:' + pct + '%;"></div>' +
                '</div>' +
            '</td>' +
            '<td class="text-right">' + formatISK(row.amount, true) + '</td>' +
            '<td class="text-right text-muted">' + (row.count || 0).toLocaleString() + '</td>' +
        '</tr>';
    });
    tbody.innerHTML = html;
}

function renderTxnRows(tbody, rows) {
    if (!tbody) return;
    if (!rows || rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No transactions for this period.</td></tr>';
        return;
    }
    // Helper to truncate a free-text field for table display.
    const trunc = (s, n) => {
        s = String(s || '');
        return s.length > n ? s.substring(0, n) + '...' : s;
    };
    let html = '';
    rows.forEach(row => {
        // CCP's auto-description (e.g. "Matt Falahe deposited cash into
        // Mercurialis Inc.'s account") shows in muted small text.
        const desc = row.description
            ? '<small class="text-muted d-block">' + escapeHtml(trunc(row.description, 80)) + '</small>'
            : '';
        // Player-typed memo (the "reason" field on player_donation etc.).
        // Shown in italic with a quote icon so the operator can tell at a
        // glance whether the donor wrote a note. Empty / same-as-description
        // memos suppress the row.
        let memo = '';
        if (row.reason && String(row.reason).trim() !== '' && String(row.reason).trim() !== String(row.description || '').trim()) {
            memo = '<small class="d-block" style="color:#a78bfa; font-style:italic;">' +
                '<i class="fas fa-quote-left mr-1"></i>' +
                escapeHtml(trunc(row.reason, 80)) +
                '</small>';
        }
        html += '<tr>' +
            '<td><small>' + escapeHtml(row.date) + '</small></td>' +
            '<td>' + escapeHtml(row.character_name) + '</td>' +
            '<td>' + escapeHtml(row.ref_type_label || row.ref_type || '') + desc + memo + '</td>' +
            '<td class="text-right">' + formatISK(row.amount, true) + '</td>' +
        '</tr>';
    });
    tbody.innerHTML = html;
}

function renderByCharacterTable(tbody, byChar, characters) {
    if (!tbody) return;
    // Normalise: byChar comes as an object keyed by character id (or empty
    // object). Combine with the characters meta so the table lists every
    // owned char even if they had zero activity this period.
    const rows = [];
    const lookup = byChar || {};
    (characters || []).forEach(c => {
        const row = lookup[String(c.id)] || lookup[c.id] || null;
        rows.push({
            id:                c.id,
            name:              c.name,
            is_main:           !!c.is_main,
            income_total:      row ? parseFloat(row.income_total || 0) : 0,
            expense_total:     row ? parseFloat(row.expense_total || 0) : 0,
            net_flow:          row ? parseFloat(row.net_flow || 0) : 0,
            transaction_count: row ? parseInt(row.transaction_count || 0, 10) : 0,
        });
    });
    // Sort by net flow descending so the big earner sits at the top.
    rows.sort((a, b) => b.net_flow - a.net_flow);
    if (rows.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No characters on file.</td></tr>';
        return;
    }
    let html = '';
    rows.forEach(r => {
        const mainBadge = r.is_main ? ' <span class="badge badge-primary ml-1">Main</span>' : '';
        const netClass  = r.net_flow > 0 ? 'text-success' : (r.net_flow < 0 ? 'text-danger' : 'text-muted');
        html += '<tr>' +
            '<td>' + escapeHtml(r.name) + mainBadge + '</td>' +
            '<td class="text-right">' + formatISK(r.income_total, true) + '</td>' +
            '<td class="text-right">' + formatISK(r.expense_total, true) + '</td>' +
            '<td class="text-right ' + netClass + '">' + formatISK(r.net_flow, true) + '</td>' +
            '<td class="text-right text-muted">' + r.transaction_count.toLocaleString() + '</td>' +
        '</tr>';
    });
    tbody.innerHTML = html;
}

function renderSparkline(canvasId, sparkline) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (personalWalletSparkChart) {
        personalWalletSparkChart.destroy();
    }
    if (!sparkline || sparkline.length === 0) {
        // Render an empty chart shell with a "no data" notice.
        const parent = canvas.parentNode;
        if (parent) {
            // Don't clobber the canvas; let Chart render with empty data.
        }
        personalWalletSparkChart = new Chart(ctx, {
            type: 'line',
            data: { labels: ['no data'], datasets: [{ label: 'Balance', data: [0], borderColor: '#6b7280', fill: false }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } },
        });
        return;
    }
    const labels = sparkline.map(p => p.period);
    const data   = sparkline.map(p => parseFloat(p.balance || 0));
    personalWalletSparkChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Balance',
                data: data,
                borderColor: config.colorActual,
                backgroundColor: config.colorActual + '20',
                fill: false,
                tension: 0.3,
                pointRadius: 4,
                pointHoverRadius: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return 'Balance: ' + formatISK(ctx.parsed.y, true);
                        }
                    }
                }
            },
            scales: {
                y: {
                    ticks: {
                        callback: function(v) { return formatISK(v, true); }
                    }
                }
            }
        }
    });
}

function loadPersonalWallet(force) {
    if (!config.showPersonalWallet) {
        return;
    }
    let url;
    try {
        const periodSel = document.getElementById('pw-period-select');
        const periodVal = periodSel && periodSel.value ? periodSel.value : '';
        let base = addCorpParam('/corp-wallet-manager/api/personal-wallet-stats');
        if (periodVal) {
            base += (base.includes('?') ? '&' : '?') + 'period=' + encodeURIComponent(periodVal);
        }
        // Tab-nav refresh button bypasses the server-side Redis cache.
        if (force) {
            base += (base.includes('?') ? '&' : '?') + 'refresh=1';
        }
        url = buildUrl(base);
    } catch (e) {
        return;
    }

    fetch(url)
        .then(r => r.json())
        .then(data => {
            const agg = (data && data.aggregate) || {};
            const trend = (data && data.trend) || {};
            const safe = (label, fn) => {
                try { fn(); } catch (e) { /* swallow per-block; tab stays usable */ }
            };

            safe('KPI info-boxes', () => {
                const incomeEl   = document.getElementById('pw-income-total');
                const expenseEl  = document.getElementById('pw-expense-total');
                const netEl      = document.getElementById('pw-net-flow');
                const txnCountEl = document.getElementById('pw-transaction-count');
                if (incomeEl)   incomeEl.textContent   = formatISK(parseFloat(agg.income_total || 0), true);
                if (expenseEl)  expenseEl.textContent  = formatISK(parseFloat(agg.expense_total || 0), true);
                if (netEl) {
                    const netVal = parseFloat(agg.net_flow || 0);
                    netEl.textContent = formatISK(netVal, true);
                    netEl.className = 'info-box-number ' + (netVal >= 0 ? 'text-success' : 'text-danger');
                }
                if (txnCountEl) txnCountEl.textContent = (parseInt(agg.transaction_count || 0, 10)).toLocaleString();
            });

            safe('trend badges', () => {
                renderTrendBadge(document.getElementById('pw-income-trend'), trend.income_pct);
                renderTrendBadge(document.getElementById('pw-expense-trend'), trend.expense_pct);
                renderTrendBadge(document.getElementById('pw-net-trend'), trend.net_pct);
            });

            safe('income sources table', () => {
                renderRefTypeRows(
                    document.getElementById('pw-income-sources-tbody'),
                    agg.top_income_sources || [],
                    parseFloat(agg.income_total || 0),
                    'bg-success'
                );
            });
            safe('expense sources table', () => {
                renderRefTypeRows(
                    document.getElementById('pw-expense-sources-tbody'),
                    agg.top_expense_sources || [],
                    parseFloat(agg.expense_total || 0),
                    'bg-danger'
                );
            });

            safe('sparkline', () => {
                renderSparkline('pw-balance-sparkline', data.sparkline_balance || []);
            });

            safe('income transactions table', () => {
                renderTxnRows(document.getElementById('pw-income-txns-tbody'), agg.top_income_transactions || []);
            });
            safe('expense transactions table', () => {
                renderTxnRows(document.getElementById('pw-expense-txns-tbody'), agg.top_expense_transactions || []);
            });

            safe('per-character breakdown', () => {
                renderByCharacterTable(
                    document.getElementById('pw-by-char-tbody'),
                    data.by_character || {},
                    data.characters || []
                );
            });
        })
        .catch(err => {
            const tbodies = ['pw-income-sources-tbody', 'pw-expense-sources-tbody', 'pw-income-txns-tbody', 'pw-expense-txns-tbody', 'pw-by-char-tbody'];
            tbodies.forEach(id => {
                const t = document.getElementById(id);
                if (t) t.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Unable to load personal wallet data.</td></tr>';
            });
        });
}

// ============================================================
// Per-tab loaders. The Corp Wallet tab is default-active so it
// loads on page load via refreshData(); the other two defer to
// shown.bs.tab so a viewer who never opens them pays nothing.
// ============================================================

// Tab loaders always run on tab show. The previous "set init flag,
// short-circuit on subsequent shows" pattern would deadlock the tab
// forever if the first load threw before any DOM updates: the flag
// got set first, so future tab clicks early-returned without retry.
// Since each loader's fetch is backed by the controller's 5-minute
// Redis cache, repeating the fetch is cheap (cache hit ~1ms) and
// removes the deadlock failure mode.

function loadCorpWalletTab(force) {
    loadHealthStatus();
    loadTrendIndicator();
    loadActivityLevel();
    loadPerformanceScore();
    loadGoals();
    loadBalanceTrendChart(currentMonths);
    loadPerformanceRadar();
    loadActivityPattern();
    loadMilestones();
    loadMonthlySummary();
    loadMemberLeaderboard();
}

function loadMyContributionTab(force) {
    setupPersonalPeriodSelect();
    loadPersonalContribution(force);
    loadPersonalMmCompliance();
    loadPersonalMilestones();
}

function loadPersonalWalletTab(force) {
    // `force` is the tab-nav refresh; forward it so the controller
    // bypasses Redis cache (the cache write happens regardless).
    loadPersonalWallet(force);
}

function activeTabId() {
    const active = document.querySelector('#memberViewTabs .nav-link.active');
    if (!active) return 'mv-corp-wallet';
    const href = active.getAttribute('href') || '';
    return href.replace(/^#/, '') || 'mv-corp-wallet';
}

// Refresh whichever tab is currently visible. The button in the tab
// nav-bar calls this; auto-refresh ticks call this too.
function refreshData() {
    const tab = activeTabId();
    if (tab === 'mv-corp-wallet') {
        loadCorpWalletTab(true);
    } else if (tab === 'mv-my-contribution') {
        loadMyContributionTab(true);
    } else if (tab === 'mv-personal-wallet') {
        loadPersonalWalletTab(true);
    }
    // Log access fires on any refresh so the access log captures tab
    // switches as activity too.
    logAccess();
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    try { applySectionVisibility(); } catch (e) { /* defensive */ }

    try {
        setupLeaderboardPeriodSelect();
        setupPersonalWalletPeriodSelect();
    } catch (e) { /* defensive */ }

    // Wire deferred tab loaders. CRITICAL: Bootstrap 4 fires `shown.bs.tab`
    // as a jQuery custom event via $(...).trigger() — it does NOT propagate
    // through native addEventListener. The previous wiring used
    // node.addEventListener('shown.bs.tab', ...) which silently never
    // fired, leaving the My Contribution and Personal Wallet tabs stuck
    // on their Blade-default "Loading..." placeholders until the user
    // clicked the tab-nav Refresh button. Use jQuery's .on() to actually
    // catch the event.
    try {
        if (typeof jQuery !== 'undefined' || typeof $ !== 'undefined') {
            const $j = (typeof jQuery !== 'undefined') ? jQuery : $;
            $j('#memberViewTabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                const target = e.target.getAttribute('href') || '';
                const id = target.replace(/^#/, '');
                if (id === 'mv-corp-wallet')        loadCorpWalletTab(false);
                else if (id === 'mv-my-contribution') loadMyContributionTab(false);
                else if (id === 'mv-personal-wallet') loadPersonalWalletTab(false);
            });
        }
    } catch (e) { /* defensive */ }

    try { loadCorporationSettings(); } catch (e) { /* defensive */ }
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (config.refreshTimer) {
        clearInterval(config.refreshTimer);
    }
});
</script>
@endpush
