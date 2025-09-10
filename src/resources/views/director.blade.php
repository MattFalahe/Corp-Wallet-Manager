@extends('web::layouts.app')

@section('title', 'CorpWallet Manager - Director')

@section('content')
<div class="row">
    <div class="col-12">
        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        <!-- Corporation Selector -->
        <div class="alert alert-info mb-3" id="corp-selector-info">
            <i class="fas fa-info-circle"></i>
            <span id="current-corp-display">Loading corporation settings...</span>
            <a href="{{ route('corpwalletmanager.settings') }}" class="float-right">
                <i class="fas fa-cog"></i> Change in Settings
            </a>
        </div>

        <!-- Tab Navigation -->
        <div class="nav-tabs-custom">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <a class="nav-link active" href="#overview" data-toggle="tab">
                        <i class="fas fa-chart-line"></i> Overview
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#analytics" data-toggle="tab">
                        <i class="fas fa-heartbeat"></i> Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#trends" data-toggle="tab">
                        <i class="fas fa-chart-area"></i> Trends
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#performance" data-toggle="tab">
                        <i class="fas fa-trophy"></i> Performance
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#cashflow" data-toggle="tab">
                        <i class="fas fa-money-bill-wave"></i> Cash Flow
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#reports" data-toggle="tab">
                        <i class="fas fa-file-alt"></i> Reports
                    </a>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Overview Tab -->
                <div class="tab-pane active" id="overview">
                    <!-- Current Wallet Status Row -->
                    <div class="row mb-3 mt-3">
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-primary"><i class="fas fa-wallet"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Actual Wallet Balance</span>
                                    <span class="info-box-number" id="actual-balance">Loading...</span>
                                    <small class="text-muted">Live from EVE</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-calendar-day"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Today's Change</span>
                                    <span class="info-box-number" id="today-change">Loading...</span>
                                    <small class="text-muted">Since midnight</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-success"><i class="fas fa-calendar"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">This Month's Flow</span>
                                    <span class="info-box-number" id="month-flow">Loading...</span>
                                    <small class="text-muted">Total in/out</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning"><i class="fas fa-chart-line"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">30-Day Prediction</span>
                                    <span class="info-box-number" id="predicted-balance">Loading...</span>
                                    <small class="text-muted">Estimated</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Division Breakdown -->
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Division Wallets</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Division</th>
                                                    <th>Current Balance</th>
                                                    <th>Month Change</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody id="division-table">
                                                <tr>
                                                    <td colspan="4" class="text-center">Loading...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Balance History Chart -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="card-title">Balance History (12 Months)</h3>
                                    <div class="card-tools">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary" onclick="updateBalanceChart('actual')">Actual</button>
                                            <button type="button" class="btn btn-secondary active" onclick="updateBalanceChart('flow')">Flow</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <canvas id="balanceChart" height="400"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Income vs Expenses Chart -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="card-title">Income vs Expenses Trend (12 Months)</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="incomeExpenseChart" height="400"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pie Charts Row -->
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Income Breakdown</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <canvas id="incomeBreakdownChart" height="300"></canvas>
                                        </div>
                                        <div class="col-md-4">
                                            <div id="income-legend"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Expense Breakdown</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <canvas id="expenseBreakdownChart" height="300"></canvas>
                                        </div>
                                        <div class="col-md-4">
                                            <div id="expense-legend"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Prediction Chart -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h3 class="card-title">30-Day Forecast</h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" onclick="refreshData()">
                                    <i class="fas fa-sync-alt"></i> Refresh All
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="predictionChart" height="150"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Analytics Tab -->
                <div class="tab-pane" id="analytics">
                    <div class="row mt-3">
                        <!-- Financial Health Score -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Financial Health Score</h3>
                                </div>
                                <div class="card-body text-center">
                                    <div style="font-size: 48px; font-weight: bold;" id="health-score">
                                        <i class="fas fa-spinner fa-spin"></i>
                                    </div>
                                    <div class="progress mt-3" style="height: 25px;">
                                        <div id="health-bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                    <div class="mt-3" id="health-details">
                                        <small class="text-muted">Calculating...</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Burn Rate Analysis -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Burn Rate Analysis</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-6">
                                            <h5>Daily Burn</h5>
                                            <h3 id="daily-burn" class="text-danger">0 ISK</h3>
                                        </div>
                                        <div class="col-6">
                                            <h5>Days of Cash</h5>
                                            <h3 id="days-remaining" class="text-info">0 days</h3>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-6">
                                            <small>Weekly Average</small>
                                            <p id="weekly-avg">0 ISK</p>
                                        </div>
                                        <div class="col-6">
                                            <small>Monthly Average</small>
                                            <p id="monthly-avg">0 ISK</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Ratios -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Key Financial Ratios</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <div class="info-box bg-info">
                                                <span class="info-box-icon"><i class="fas fa-percentage"></i></span>
                                                <div class="info-box-content">
                                                    <span class="info-box-text">Liquidity Ratio</span>
                                                    <span class="info-box-number" id="liquidity-ratio">0.0</span>
                                                    <small>Balance / Monthly Expenses</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="info-box bg-success">
                                                <span class="info-box-icon"><i class="fas fa-chart-line"></i></span>
                                                <div class="info-box-content">
                                                    <span class="info-box-text">Growth Rate</span>
                                                    <span class="info-box-number" id="growth-rate">0%</span>
                                                    <small>Month over Month</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="info-box bg-warning">
                                                <span class="info-box-icon"><i class="fas fa-balance-scale"></i></span>
                                                <div class="info-box-content">
                                                    <span class="info-box-text">Income/Expense</span>
                                                    <span class="info-box-number" id="income-expense-ratio">0.0</span>
                                                    <small>Profitability Ratio</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="info-box bg-danger">
                                                <span class="info-box-icon"><i class="fas fa-exclamation-triangle"></i></span>
                                                <div class="info-box-content">
                                                    <span class="info-box-text">Volatility</span>
                                                    <span class="info-box-number" id="volatility">0%</span>
                                                    <small>Balance Stability</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Trends Tab -->
                <div class="tab-pane" id="trends">
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Activity Heatmap</h3>
                                </div>
                                <div class="card-body">
                                    <div id="activity-heatmap">
                                        <p class="text-center text-muted">Loading activity data...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Best & Worst Days</h3>
                                </div>
                                <div class="card-body">
                                    <h5>Best Income Days</h5>
                                    <ul id="best-days" class="list-unstyled">
                                        <li class="text-muted">Loading...</li>
                                    </ul>
                                    <hr>
                                    <h5>Highest Expense Days</h5>
                                    <ul id="worst-days" class="list-unstyled">
                                        <li class="text-muted">Loading...</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Weekly Patterns</h3>
                                </div>
                                <div class="card-body">
                                    <div style="height: 400px; position: relative;">
                                        <canvas id="weekly-pattern-chart"></canvas>
                                    </div>
                                    <div class="weekly-pattern-info mt-2 text-center">
                                        <!-- Info will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Tab -->
                <div class="tab-pane" id="performance">
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Division Performance Metrics</h3>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Division</th>
                                                    <th>Balance</th>
                                                    <th>Monthly Income</th>
                                                    <th>Monthly Expense</th>
                                                    <th>ROI</th>
                                                    <th>Efficiency</th>
                                                    <th>Trend</th>
                                                </tr>
                                            </thead>
                                            <tbody id="division-performance">
                                                <tr>
                                                    <td colspan="7" class="text-center">Loading performance data...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Top Income Sources</h3>
                                </div>
                                <div class="card-body">
                                    <ul id="top-income-sources" class="list-unstyled">
                                        <li class="text-muted">Loading...</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Top Expense Categories</h3>
                                </div>
                                <div class="card-body">
                                    <ul id="top-expense-categories" class="list-unstyled">
                                        <li class="text-muted">Loading...</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cash Flow Tab -->
                <div class="tab-pane" id="cashflow">
                    <!-- Overall Summary Section -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Overall Cash Flow Waterfall</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="cashflow-waterfall" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                
                    <!-- Division Selector and Chart -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Division Daily Cash Flow</h3>
                                    <div class="card-tools">
                                        <select class="form-control form-control-sm d-inline-block" style="width: 200px;" id="division-selector">
                                            <option value="">Loading divisions...</option>
                                        </select>
                                        <div class="btn-group btn-group-sm ml-2">
                                            <button type="button" class="btn btn-outline-secondary active" onclick="loadDivisionCashFlow(7)">7D</button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="loadDivisionCashFlow(30)">30D</button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="loadDivisionCashFlow(60)">60D</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div style="height: 400px; position: relative;">
                                        <canvas id="division-cashflow-chart"></canvas>
                                    </div>
                                    <div id="division-cashflow-stats" class="mt-3">
                                        <!-- Statistics will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                
                    <!-- Division Comparison Grid -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">All Divisions Comparison (Last 7 Days)</h3>
                                </div>
                                <div class="card-body">
                                    <div class="row" id="division-comparison-grid">
                                        <!-- Mini charts will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                
                    <!-- Income/Expense Categories Row -->
                    <div class="row mt-3">
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Income Categories</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="income-categories-detailed" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Expense Categories</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="expense-categories-detailed" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Net Flow Summary</h3>
                                </div>
                                <div class="card-body">
                                    <div class="text-center">
                                        <h2 id="net-flow-total">0 ISK</h2>
                                        <p class="text-muted">This Month</p>
                                    </div>
                                    <hr>
                                    <div id="flow-breakdown">
                                        <div class="mb-2">
                                            <span class="text-success">Income:</span>
                                            <span class="float-right" id="total-income">0 ISK</span>
                                        </div>
                                        <div class="mb-2">
                                            <span class="text-danger">Expenses:</span>
                                            <span class="float-right" id="total-expenses">0 ISK</span>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Net:</strong>
                                            <strong class="float-right" id="net-difference">0 ISK</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                
                    <!-- Overall Daily Trend -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Overall Daily Cash Flow Trend</h3>
                                    <div class="card-tools">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary active" onclick="loadDailyCashFlowWithDays(30)">30D</button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="loadDailyCashFlowWithDays(60)">60D</button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="loadDailyCashFlowWithDays(90)">90D</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div style="height: 400px; position: relative;">
                                        <canvas id="daily-cashflow-chart"></canvas>
                                    </div>
                                    <div id="cashflow-statistics" class="mt-3">
                                        <!-- Statistics will be populated here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reports Tab -->
                <div class="tab-pane" id="reports">
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Executive Summary</h3>
                                    <div class="card-tools">
                                        <button class="btn btn-sm btn-primary" onclick="exportReport('pdf')">
                                            <i class="fas fa-file-pdf"></i> Export PDF
                                        </button>
                                        <button class="btn btn-sm btn-success" onclick="exportReport('excel')">
                                            <i class="fas fa-file-excel"></i> Export Excel
                                        </button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="executive-summary">
                                        <h4>Key Insights</h4>
                                        <ul id="key-insights">
                                            <li>Loading insights...</li>
                                        </ul>

                                        <h4 class="mt-4">Recommendations</h4>
                                        <ul id="recommendations">
                                            <li>Loading recommendations...</li>
                                        </ul>

                                        <h4 class="mt-4">Risk Assessment</h4>
                                        <div id="risk-assessment">
                                            <p class="text-muted">Analyzing risks...</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Monthly Report</h3>
                                </div>
                                <div class="card-body">
                                    <select class="form-control mb-3" id="report-month">
                                        <!-- Will be populated with months -->
                                    </select>
                                    <button class="btn btn-primary" onclick="generateMonthlyReport()">
                                        Generate Report
                                    </button>
                                    <div id="monthly-report-content" class="mt-3">
                                        <!-- Report will appear here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Custom Report Builder</h3>
                                </div>
                                <div class="card-body">
                                    <div class="form-group">
                                        <label>Date Range</label>
                                        <input type="date" class="form-control mb-2" id="report-start">
                                        <input type="date" class="form-control" id="report-end">
                                    </div>
                                    <div class="form-group">
                                        <label>Include Sections</label>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="inc-balance" checked>
                                            <label class="form-check-label" for="inc-balance">Balance History</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="inc-income" checked>
                                            <label class="form-check-label" for="inc-income">Income Analysis</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="inc-expense" checked>
                                            <label class="form-check-label" for="inc-expense">Expense Analysis</label>
                                        </div>
                                    </div>
                                    <button class="btn btn-success" onclick="generateCustomReport()">
                                        Build Report
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@stop

@push('javascript')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Fix SeAT's mixed content issue first
(function() {
    // Wait for jQuery to be available
    if (typeof $ !== 'undefined' && $.ajax) {
        var originalAjax = $.ajax;
        $.ajax = function(settings) {
            if (settings && settings.url && typeof settings.url === 'string' && settings.url.startsWith('http://')) {
                settings.url = settings.url.replace('http://', 'https://');
            }
            return originalAjax.call(this, settings);
        };
    }
})();

// Configuration - Using Blade syntax properly
let config = {
    decimals: {!! config('corpwalletmanager.decimals', 2) !!},
    colorActual: "{!! config('corpwalletmanager.color_actual', '#4cafef') !!}",
    colorPredicted: "{!! config('corpwalletmanager.color_predicted', '#ef4444') !!}",
    colorIncome: '#10b981',
    colorExpense: '#ef4444',
    corporationId: null,
    refreshInterval: null,
    refreshTimer: null
};

// Chart variables
let balanceChart = null;
let incomeExpenseChart = null;
let predictionChart = null;
let incomeBreakdownChart = null;
let expenseBreakdownChart = null;
let cashflowWaterfallChart = null;
let incomeCategoriesDetailedChart = null;
let expenseCategoriesDetailedChart = null;
let dailyCashflowChart = null;
let weeklyPatternChart = null;
let currentChartMode = 'flow';

// Helper function to build URLs - FIXED VERSION
//function buildUrl(path) {
    // Use the current page's protocol to ensure HTTPS when needed
//    const currentUrl = window.location;
    // Force HTTPS if the current page is HTTPS
//    const protocol = currentUrl.protocol; // This will be 'https:' on your site
//    const baseUrl = protocol + '//' + currentUrl.host;
//    return baseUrl + path;
//}
// Alternative more robust version if the above still has issues:
//function buildUrl(path) {
    // Always use the same protocol as the current page
    // This ensures we never mix HTTP and HTTPS
//    return window.location.origin + path;
//}

// Or if you want to ALWAYS force HTTPS (recommended for production):
function buildUrl(path) {
    const host = window.location.host;
    return 'https://' + host + path;
}

// Load corporation settings
function loadCorporationSettings() {
    fetch(buildUrl('/corp-wallet-manager/api/selected-corporation'))
        .then(response => response.json())
        .then(data => {
            config.corporationId = data.corporation_id;
            config.refreshInterval = data.refresh_interval;

            // Update display
            const displayText = config.corporationId
                ? `Viewing data for Corporation ID: ${config.corporationId}`
                : 'Viewing data for all corporations';

            document.getElementById('current-corp-display').textContent = displayText;

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

// Setup auto-refresh based on settings
function setupAutoRefresh(refreshMinutes) {
    // Clear existing timer
    if (config.refreshTimer) {
        clearInterval(config.refreshTimer);
        config.refreshTimer = null;
    }

    // Set new timer if refresh is enabled
    if (refreshMinutes && refreshMinutes !== '0') {
        const intervalMs = parseInt(refreshMinutes) * 60 * 1000;
        config.refreshTimer = setInterval(refreshData, intervalMs);
        console.log(`Auto-refresh enabled: every ${refreshMinutes} minutes`);
    } else {
        console.log('Auto-refresh disabled');
    }
}

// Enhanced ISK formatter that handles large numbers better
function formatISK(value, compact = false) {
    if (!isFinite(value) || isNaN(value)) {
        return '0 ISK';
    }

    // For chart axis labels, use very compact format
    if (compact) {
        const absValue = Math.abs(value);
        if (absValue >= 1000000000000) {
            return (value / 1000000000000).toFixed(1) + 'T ISK';
        } else if (absValue >= 1000000000) {
            return (value / 1000000000).toFixed(1) + 'B ISK';
        } else if (absValue >= 1000000) {
            return (value / 1000000).toFixed(1) + 'M ISK';
        } else if (absValue >= 1000) {
            return (value / 1000).toFixed(1) + 'K ISK';
        }
        return value.toFixed(0) + ' ISK';
    }

    // For normal display, use full formatting
    return new Intl.NumberFormat('en-US', {
        style: 'decimal',
        minimumFractionDigits: 0,
        maximumFractionDigits: config.decimals
    }).format(value) + ' ISK';
}

// Special formatter for chart axes (even more compact)
function formatAxisValue(value) {
    const absValue = Math.abs(value);
    if (absValue >= 1000000000000) {
        return (value / 1000000000000).toFixed(0) + 'T';
    } else if (absValue >= 1000000000) {
        return (value / 1000000000).toFixed(0) + 'B';
    } else if (absValue >= 1000000) {
        return (value / 1000000).toFixed(0) + 'M';
    } else if (absValue >= 1000) {
        return (value / 1000).toFixed(0) + 'K';
    }
    return value.toFixed(0);
}

// Add corporation parameter to API calls
function addCorpParam(url) {
    if (config.corporationId) {
        const separator = url.includes('?') ? '&' : '?';
        return url + separator + 'corporation_id=' + config.corporationId;
    }
    return url;
}

// Load actual wallet balance from EVE
function loadActualBalance() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/wallet-actual')))
        .then(response => response.json())
        .then(data => {
            document.getElementById('actual-balance').textContent = formatISK(data.balance, true);
        })
        .catch(error => {
            document.getElementById('actual-balance').textContent = 'N/A';
        });
}

// Load today's changes
function loadTodayChanges() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/today')))
        .then(response => response.json())
        .then(data => {
            const changeEl = document.getElementById('today-change');
            const value = data.change || 0;
            changeEl.innerHTML = (value >= 0 ? '<i class="fas fa-arrow-up text-success"></i> ' : '<i class="fas fa-arrow-down text-danger"></i> ') + formatISK(Math.abs(value), true);
        })
        .catch(error => {
            document.getElementById('today-change').textContent = 'Error';
        });
}

// Load current data
function loadCurrentData() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/latest')))
        .then(response => response.json())
        .then(data => {
            document.getElementById('month-flow').textContent = formatISK(data.balance, true);
            document.getElementById('predicted-balance').textContent = formatISK(data.predicted, true);
        })
        .catch(error => {
            console.error('Error loading current data:', error);
        });
}

// Load division breakdown
function loadDivisionBreakdown() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/division-current')))
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('division-table');
            if (!data.divisions || data.divisions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No division data available</td></tr>';
                return;
            }

            let html = '';
            data.divisions.forEach(div => {
                const changeClass = div.change >= 0 ? 'text-success' : 'text-danger';
                const changeIcon = div.change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                const statusClass = div.balance > 0 ? 'badge-success' : 'badge-warning';
                const status = div.balance > 0 ? 'Healthy' : 'Low';

                html += `
                    <tr>
                        <td>${div.name}</td>
                        <td>${formatISK(div.balance, true)}</td>
                        <td class="${changeClass}">
                            <i class="fas ${changeIcon}"></i> ${formatISK(Math.abs(div.change), true)}
                        </td>
                        <td><span class="badge ${statusClass}">${status}</span></td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        })
        .catch(error => {
            document.getElementById('division-table').innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading divisions</td></tr>';
        });
}

// Load balance history chart
function loadBalanceChart(mode = 'flow') {
    const endpoint = mode === 'actual'
        ? '/corp-wallet-manager/api/balance-history?months=12'
        : '/corp-wallet-manager/api/monthly-comparison?months=12';

    fetch(buildUrl(addCorpParam(endpoint)))
        .then(response => response.json())
        .then(data => {
            // Destroy existing chart
            if (balanceChart) {
                balanceChart.destroy();
                balanceChart = null;
            }

            const canvas = document.getElementById('balanceChart');
            const ctx = canvas.getContext('2d');

            // Explicit height control
            canvas.parentNode.style.height = '400px';
            canvas.parentNode.style.width = '100%';

            balanceChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: mode === 'actual' ? 'Actual Balance' : 'Monthly Flow',
                        data: data.data || [],
                        borderColor: config.colorActual,
                        backgroundColor: config.colorActual + '20',
                        fill: true,
                        tension: 0.4
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
                                    return context.dataset.label + ': ' + formatISK(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return formatAxisValue(value);
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading balance chart:', error);
        });
}

// Load income vs expense chart
function loadIncomeExpenseChart() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/income-expense?months=12')))
        .then(response => response.json())
        .then(data => {
            // Destroy existing chart
            if (incomeExpenseChart) {
                incomeExpenseChart.destroy();
                incomeExpenseChart = null;
            }

            const canvas = document.getElementById('incomeExpenseChart');
            const ctx = canvas.getContext('2d');

            // Explicit height control
            canvas.parentNode.style.height = '400px';
            canvas.parentNode.style.width = '100%';

            incomeExpenseChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [
                        {
                            label: 'Income',
                            data: data.income || [],
                            borderColor: '#10b981',
                            backgroundColor: '#10b98120',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Expenses',
                            data: data.expenses || [],
                            borderColor: '#ef4444',
                            backgroundColor: '#ef444420',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatISK(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatAxisValue(value);
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading income/expense chart:', error);
        });
}

// Load income breakdown pie chart
function loadIncomeBreakdown() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/transaction-breakdown?type=income')))
        .then(response => response.json())
        .then(data => {
            const canvas = document.getElementById('incomeBreakdownChart');
            if (!canvas) {
                console.error('Income breakdown chart canvas not found');
                return;
            }

            // Destroy existing chart
            if (incomeBreakdownChart) {
                incomeBreakdownChart.destroy();
                incomeBreakdownChart = null;
            }

            const ctx = canvas.getContext('2d');

            // Explicit height control for pie chart
            canvas.parentNode.style.height = '300px';
            canvas.parentNode.style.width = '100%';

            // Generate colors for each segment
            const colors = [
                '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b',
                '#06b6d4', '#84cc16', '#f97316', '#6366f1', '#14b8a6'
            ];

            incomeBreakdownChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        data: data.values || [],
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${formatISK(value, true)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Create detailed legend
            if (data.details && data.details.length > 0) {
                const legendDiv = document.getElementById('income-legend');
                let html = '<small class="text-muted">Top Sources:</small><ul class="list-unstyled mb-0">';
                data.details.slice(0, 5).forEach((item, index) => {
                    html += `<li><span style="color: ${colors[index]}">●</span> ${item.label}: ${formatISK(item.value, true)}</li>`;
                });
                html += '</ul>';
                legendDiv.innerHTML = html;
            }
        })
        .catch(error => {
            console.error('Error loading income breakdown:', error);
        });
}

// Load expense breakdown pie chart
function loadExpenseBreakdown() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/transaction-breakdown?type=expense')))
        .then(response => response.json())
        .then(data => {
            const canvas = document.getElementById('expenseBreakdownChart');
            if (!canvas) {
                console.error('Expense breakdown chart canvas not found');
                return;
            }

            // Destroy existing chart
            if (expenseBreakdownChart) {
                expenseBreakdownChart.destroy();
                expenseBreakdownChart = null;
            }

            const ctx = canvas.getContext('2d');

            // Explicit height control for pie chart
            canvas.parentNode.style.height = '300px';
            canvas.parentNode.style.width = '100%';

            // Generate colors for each segment
            const colors = [
                '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16',
                '#22c55e', '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9'
            ];

            expenseBreakdownChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        data: data.values || [],
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${formatISK(value, true)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });

            // Create detailed legend
            if (data.details && data.details.length > 0) {
                const legendDiv = document.getElementById('expense-legend');
                let html = '<small class="text-muted">Top Expenses:</small><ul class="list-unstyled mb-0">';
                data.details.slice(0, 5).forEach((item, index) => {
                    html += `<li><span style="color: ${colors[index]}">●</span> ${item.label}: ${formatISK(item.value, true)}</li>`;
                });
                html += '</ul>';
                legendDiv.innerHTML = html;
            }
        })
        .catch(error => {
            console.error('Error loading expense breakdown:', error);
        });
}

// Load prediction chart
function loadPredictionChart() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/predictions?days=30')))
        .then(response => response.json())
        .then(data => {
            // Destroy existing chart
            if (predictionChart) {
                predictionChart.destroy();
                predictionChart = null;
            }

            const canvas = document.getElementById('predictionChart');
            const ctx = canvas.getContext('2d');

            // Explicit height control
            canvas.parentNode.style.height = '150px';
            canvas.parentNode.style.width = '100%';

            if (!data.data || data.data.length === 0) {
                ctx.font = '20px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('No prediction data available', canvas.width / 2, canvas.height / 2);
                return;
            }

            predictionChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Predicted Balance',
                        data: data.data || [],
                        borderColor: config.colorPredicted,
                        backgroundColor: config.colorPredicted + '20',
                        borderDash: [5, 5],
                        tension: 0.4,
                        fill: true
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
                                    return 'Predicted: ' + formatISK(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return formatAxisValue(value);
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading prediction chart:', error);
        });
}

// Update balance chart mode
function updateBalanceChart(mode) {
    currentChartMode = mode;
    document.querySelectorAll('.card-tools .btn-group .btn').forEach(btn => {
        btn.classList.remove('btn-secondary', 'active');
        btn.classList.add('btn-outline-secondary');
    });
    event.target.classList.remove('btn-outline-secondary');
    event.target.classList.add('btn-secondary', 'active');
    loadBalanceChart(mode);
}
// Analytics Tab Functions
function loadAnalyticsData() {
    calculateHealthScore();
    calculateBurnRate();
    calculateFinancialRatios();
}

function calculateHealthScore() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/analytics/health-score')))
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Health score error:', data.error);
                return;
            }

            document.getElementById('health-score').textContent = data.score + '/100';
            const bar = document.getElementById('health-bar');
            bar.style.width = data.score + '%';

            let barClass = 'progress-bar ';
            if (data.score >= 80) {
                barClass += 'bg-success';
            } else if (data.score >= 60) {
                barClass += 'bg-primary';
            } else if (data.score >= 40) {
                barClass += 'bg-warning';
            } else {
                barClass += 'bg-danger';
            }
            bar.className = barClass;

            let detailsHtml = `
                <p>Balance Stability: <strong>${data.components.balance_stability}%</strong></p>
                <p>Income Consistency: <strong>${data.components.income_consistency}%</strong></p>
                <p>Expense Control: <strong>${data.components.expense_control}%</strong></p>
                <hr>
                <small class="text-muted">Status: <strong>${data.status}</strong></small>
            `;
            document.getElementById('health-details').innerHTML = detailsHtml;
        })
        .catch(error => {
            console.error('Error loading health score:', error);
            document.getElementById('health-score').innerHTML = '<i class="fas fa-exclamation-triangle text-warning"></i>';
        });
}

function calculateBurnRate() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/analytics/burn-rate')))
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Burn rate error:', data.error);
                return;
            }

            document.getElementById('daily-burn').textContent = formatISK(Math.abs(data.burn_rates.daily), true);
            document.getElementById('daily-burn').className = data.burn_rates.daily > 0 ? 'text-danger' : 'text-success';

            const daysText = data.days_of_cash >= 999 ? '999+ days' : data.days_of_cash + ' days';
            document.getElementById('days-remaining').textContent = daysText;
            document.getElementById('days-remaining').className =
                data.days_of_cash > 90 ? 'text-success' :
                data.days_of_cash > 30 ? 'text-warning' : 'text-danger';

            document.getElementById('weekly-avg').textContent = formatISK(Math.abs(data.burn_rates.weekly * 7), true);
            document.getElementById('monthly-avg').textContent = formatISK(Math.abs(data.burn_rates.monthly * 30), true);

            if (data.runway_date) {
                document.getElementById('days-remaining').innerHTML +=
                    `<br><small class="text-muted">Until: ${data.runway_date}</small>`;
            }
        })
        .catch(error => {
            console.error('Error loading burn rate:', error);
        });
}

function calculateFinancialRatios() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/analytics/financial-ratios')))
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Financial ratios error:', data.error);
                return;
            }

            document.getElementById('liquidity-ratio').textContent = data.liquidity_ratio.toFixed(2);

            const growthEl = document.getElementById('growth-rate');
            growthEl.textContent = (data.growth_rate > 0 ? '+' : '') + data.growth_rate + '%';
            growthEl.className = 'info-box-number ' + (data.growth_rate > 0 ? 'text-success' : 'text-danger');

            document.getElementById('income-expense-ratio').textContent = data.income_expense_ratio.toFixed(2);
            document.getElementById('volatility').textContent = data.volatility + '%';
        })
        .catch(error => {
            console.error('Error loading financial ratios:', error);
        });
}

// Trends Tab Functions
function loadTrendsData() {
    loadActivityHeatmap();
    loadBestWorstDays();
    loadWeeklyPatterns();
}

function loadActivityHeatmap() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/analytics/activity-heatmap?days=90')))
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Activity heatmap error:', data.error);
                return;
            }

            const container = document.getElementById('activity-heatmap');
            let html = '<div class="heatmap-grid">';

            html += '<div class="row">';
            data.heatmap.forEach(day => {
                const colorClass = day.value > 0 ? 'bg-success' : day.value < 0 ? 'bg-danger' : 'bg-secondary';
                const opacity = Math.min(Math.abs(day.intensity) / 10, 1);
                html += `
                    <div class="col-auto p-1" title="${day.date}: ${formatISK(day.value, true)}">
                        <div class="${colorClass}" style="width: 15px; height: 15px; opacity: ${opacity}; cursor: pointer;"></div>
                    </div>
                `;
            });
            html += '</div>';

            html += `
                <div class="mt-3">
                    <small class="text-muted">
                        Total Days: ${data.summary.total_days} |
                        Positive: ${data.summary.positive_days} |
                        Negative: ${data.summary.negative_days}
                    </small>
                </div>
            `;

            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading activity heatmap:', error);
        });
}

function loadBestWorstDays() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/analytics/best-worst-days')))
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Best/worst days error:', data.error);
                return;
            }

            let bestHtml = '';
            data.best_days.forEach((day, index) => {
                bestHtml += `
                    <li class="mb-2">
                        <strong>${index + 1}.</strong> ${day.date}
                        <span class="float-right text-success">+${formatISK(day.income, true)}</span>
                    </li>
                `;
            });
            document.getElementById('best-days').innerHTML = bestHtml || '<li class="text-muted">No data available</li>';

            let worstHtml = '';
            data.worst_days.forEach((day, index) => {
                worstHtml += `
                    <li class="mb-2">
                        <strong>${index + 1}.</strong> ${day.date}
                        <span class="float-right text-danger">-${formatISK(day.expenses, true)}</span>
                    </li>
                `;
            });
            document.getElementById('worst-days').innerHTML = worstHtml || '<li class="text-muted">No data available</li>';
        })
        .catch(error => {
            console.error('Error loading best/worst days:', error);
        });
}

function loadWeeklyPatterns() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/analytics/weekly-patterns')))
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Weekly patterns error:', data.error);
                return;
            }

            // Destroy existing chart instance first
            if (weeklyPatternChart) {
                weeklyPatternChart.destroy();
                weeklyPatternChart = null;
            }

            const canvas = document.getElementById('weekly-pattern-chart');
            const ctx = canvas.getContext('2d');

            // Get the card body (the parent of the canvas)
            const cardBody = canvas.closest('.card-body');

            // Check if info div already exists, if not create it
            let info = cardBody.querySelector('.weekly-pattern-info');
            if (!info) {
                info = document.createElement('div');
                info.className = 'mt-2 text-center weekly-pattern-info';
                cardBody.appendChild(info); // Append to card body, not canvas parent
            }

            // Update info content
            if (data.best_day) {
                info.innerHTML = `
                    <small class="text-muted">
                        Best Day: <strong>${data.best_day.day}</strong> |
                        Worst Day: <strong>${data.worst_day.day}</strong>
                    </small>
                `;
            }

            // Explicit height control to prevent runaway growth
            canvas.parentNode.style.height = '400px'; // adjust as you like
            canvas.parentNode.style.width = '100%';

            const labels = data.patterns.map(p => p.day);
            const incomeData = data.patterns.map(p => p.avg_income);
            const expenseData = data.patterns.map(p => p.avg_expenses);

            weeklyPatternChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Average Income',
                            data: incomeData,
                            backgroundColor: '#10b981',
                            borderColor: '#10b981',
                            borderWidth: 1
                        },
                        {
                            label: 'Average Expenses',
                            data: expenseData,
                            backgroundColor: '#ef4444',
                            borderColor: '#ef4444',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatISK(context.parsed.y, true);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatAxisValue(value);
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading weekly patterns:', error);
        });
}

// Performance Tab Functions
function loadPerformanceData() {
    loadDivisionPerformance();
}

function loadDivisionPerformance() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/analytics/division-performance')))
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Division performance error:', data.error);
                return;
            }

            let tableHtml = '';
            data.divisions.forEach(div => {
                const trendIcon = div.trend === 'up' ?
                    '<i class="fas fa-arrow-up text-success"></i>' :
                    '<i class="fas fa-arrow-down text-danger"></i>';

                const roiClass = div.roi > 0 ? 'text-success' : 'text-danger';

                tableHtml += `
                    <tr>
                        <td>${div.name}</td>
                        <td>${formatISK(div.balance, true)}</td>
                        <td class="text-success">${formatISK(div.monthly_income, true)}</td>
                        <td class="text-danger">${formatISK(div.monthly_expense, true)}</td>
                        <td class="${roiClass}">${div.roi}%</td>
                        <td>${div.efficiency.toFixed(3)}</td>
                        <td>${trendIcon}</td>
                    </tr>
                `;
            });

            document.getElementById('division-performance').innerHTML =
                tableHtml || '<tr><td colspan="7" class="text-center text-muted">No performance data available</td></tr>';

            if (data.summary && data.summary.best_performer) {
                document.getElementById('top-income-sources').innerHTML = `
                    <li><strong>Best Performer:</strong> ${data.summary.best_performer.name}</li>
                    <li>ROI: ${data.summary.best_performer.roi}%</li>
                    <li>Net Flow: ${formatISK(data.summary.best_performer.net_flow, true)}</li>
                `;
            }

            if (data.summary && data.summary.worst_performer) {
                document.getElementById('top-expense-categories').innerHTML = `
                    <li><strong>Worst Performer:</strong> ${data.summary.worst_performer.name}</li>
                    <li>ROI: ${data.summary.worst_performer.roi}%</li>
                    <li>Net Flow: ${formatISK(data.summary.worst_performer.net_flow, true)}</li>
                `;
            }
        })
        .catch(error => {
            console.error('Error loading division performance:', error);
        });
}

// Cash Flow Tab Functions
function loadCashFlowData() {
    loadCashFlowWaterfall();
    loadIncomeCategoriesDetailed();
    loadExpenseCategoriesDetailed();
    loadNetFlowSummary();
    loadDailyCashFlowTrend();
}

function loadCashFlowWaterfall() {
    // First get the last month's closing balance
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/analytics/last-month-balance')))
        .then(response => response.json())
        .then(balanceData => {
            // Then get current month's income/expense
            return fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/income-expense?months=1')))
                .then(response => response.json())
                .then(flowData => ({balance: balanceData, flow: flowData}));
        })
        .then(data => {
            const canvas = document.getElementById('cashflow-waterfall');
            if (!canvas) return;

            // Destroy existing chart
            if (cashflowWaterfallChart) {
                cashflowWaterfallChart.destroy();
                cashflowWaterfallChart = null;
            }

            const ctx = canvas.getContext('2d');

            // Explicit height control
            canvas.parentNode.style.height = '300px';
            canvas.parentNode.style.width = '100%';

            // Use actual starting balance from last month
            const startBalance = data.balance.closing_balance || 0;
            const income = data.flow.income && data.flow.income[0] ? data.flow.income[0] : 0;
            const expenses = data.flow.expenses && data.flow.expenses[0] ? data.flow.expenses[0] : 0;
            const endBalance = startBalance + income - expenses;

            // Prepare data for waterfall effect
            const waterfallData = [
                {
                    label: 'Starting Balance',
                    value: startBalance,
                    cumulative: startBalance,
                    color: '#3b82f6'
                },
                {
                    label: 'Income',
                    value: income,
                    cumulative: startBalance + income,
                    color: '#10b981'
                },
                {
                    label: 'Expenses',
                    value: -expenses,
                    cumulative: startBalance + income - expenses,
                    color: '#ef4444'
                },
                {
                    label: 'Ending Balance',
                    value: endBalance,
                    cumulative: endBalance,
                    color: endBalance > startBalance ? '#10b981' : '#ef4444'
                }
            ];

            cashflowWaterfallChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: waterfallData.map(d => d.label),
                    datasets: [{
                        label: 'Cash Flow',
                        data: waterfallData.map(d => d.value),
                        backgroundColor: waterfallData.map(d => d.color),
                        borderColor: '#1f2937',
                        borderWidth: 1
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
                                    const value = context.parsed.y;
                                    const label = context.label;
                                    if (label === 'Starting Balance' || label === 'Ending Balance') {
                                        return 'Balance: ' + formatISK(Math.abs(value));
                                    }
                                    return (value >= 0 ? '+' : '') + formatISK(value);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            ticks: {
                                callback: function(value) {
                                    return formatAxisValue(value);
                                }
                            }
                        }
                    }
                }
            });

            // Add month info
            const monthInfo = document.createElement('div');
            monthInfo.className = 'text-center text-muted mt-2';
            monthInfo.innerHTML = `<small>Period: ${data.balance.month || 'Current Month'}</small>`;
            canvas.parentNode.appendChild(monthInfo);
        })
        .catch(error => {
            console.error('Error loading cash flow waterfall:', error);
        });
}

function loadIncomeCategoriesDetailed() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/transaction-breakdown?type=income&months=1')))
        .then(response => response.json())
        .then(data => {
            const canvas = document.getElementById('income-categories-detailed');
            if (!canvas) return;

            // Destroy existing chart
            if (incomeCategoriesDetailedChart) {
                incomeCategoriesDetailedChart.destroy();
                incomeCategoriesDetailedChart = null;
            }

            const ctx = canvas.getContext('2d');

            // Explicit height control for smaller pie chart
            canvas.parentNode.style.height = '250px';
            canvas.parentNode.style.width = '100%';

            const colors = ['#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b'];

            incomeCategoriesDetailedChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.labels ? data.labels.slice(0, 5) : [],
                    datasets: [{
                        data: data.values ? data.values.slice(0, 5) : [],
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 10,
                                font: { size: 10 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + formatISK(context.parsed, true);
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading income categories:', error);
        });
}

function loadExpenseCategoriesDetailed() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/transaction-breakdown?type=expense&months=1')))
        .then(response => response.json())
        .then(data => {
            const canvas = document.getElementById('expense-categories-detailed');
            if (!canvas) return;

            // Destroy existing chart
            if (expenseCategoriesDetailedChart) {
                expenseCategoriesDetailedChart.destroy();
                expenseCategoriesDetailedChart = null;
            }

            const ctx = canvas.getContext('2d');

            // Explicit height control for smaller pie chart
            canvas.parentNode.style.height = '250px';
            canvas.parentNode.style.width = '100%';

            const colors = ['#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16'];

            expenseCategoriesDetailedChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: data.labels ? data.labels.slice(0, 5) : [],
                    datasets: [{
                        data: data.values ? data.values.slice(0, 5) : [],
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 10,
                                font: { size: 10 }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + formatISK(context.parsed, true);
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading expense categories:', error);
        });
}

function loadNetFlowSummary() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/income-expense?months=1')))
        .then(response => response.json())
        .then(data => {
            const income = data.income && data.income[0] ? data.income[0] : 0;
            const expenses = data.expenses && data.expenses[0] ? data.expenses[0] : 0;
            const net = income - expenses;

            document.getElementById('total-income').textContent = formatISK(income, true);
            document.getElementById('total-expenses').textContent = formatISK(expenses, true);
            document.getElementById('net-difference').textContent = formatISK(net, true);
            document.getElementById('net-flow-total').textContent = formatISK(net, true);

            // Color code the net flow
            const netEl = document.getElementById('net-difference');
            if (net > 0) {
                netEl.className = 'float-right text-success';
            } else if (net < 0) {
                netEl.className = 'float-right text-danger';
            } else {
                netEl.className = 'float-right';
            }
        })
        .catch(error => {
            console.error('Error loading net flow summary:', error);
        });
}

function loadDailyCashFlowTrend() {
    fetch(buildUrl(addCorpParam(`/corp-wallet-manager/api/analytics/daily-cashflow?days=${days}`)))
        .then(response => response.json())
        .then(data => {
            // Update statistics
            if (data.statistics) {
                const statsHtml = `
                    <div class="row">
                        <div class="col-md-3">
                            <small class="text-muted">Total Income:</small>
                            <p class="mb-0 text-success">${formatISK(data.statistics.total_income, true)}</p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Total Expenses:</small>
                            <p class="mb-0 text-danger">${formatISK(data.statistics.total_expenses, true)}</p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Net Total:</small>
                            <p class="mb-0">${formatISK(data.statistics.net_total, true)}</p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Daily Average:</small>
                            <p class="mb-0">${formatISK(data.statistics.average_daily_flow, true)}</p>
                        </div>
                    </div>
                `;
                document.getElementById('cashflow-statistics').innerHTML = statsHtml;
            }
            // Destroy existing chart
            if (dailyCashflowChart) {
                dailyCashflowChart.destroy();
                dailyCashflowChart = null;
            }

            const canvas = document.getElementById('daily-cashflow-chart');
            const ctx = canvas.getContext('2d');

            // Fix the parent container height
            canvas.parentNode.style.height = '400px';
            canvas.parentNode.style.width = '100%';

            dailyCashflowChart = new Chart(ctx, { // Fixed: just ctx, not ctx.getContext('2d')
                type: 'bar',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Net Cash Flow',
                        data: data.datasets ? data.datasets.net_flow : [],
                        backgroundColor: data.datasets && data.datasets.net_flow ?
                            data.datasets.net_flow.map(v => v >= 0 ? '#10b981' : '#ef4444') : [],
                        borderColor: '#1f2937',
                        borderWidth: 1
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
                                    return 'Net Flow: ' + formatISK(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatAxisValue(value);
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading daily cash flow trend:', error);
        });
}

function loadDailyCashFlowWithDays(days) {
    // Update button states - without using event.target
    document.querySelectorAll('#cashflow .card-tools .btn-group .btn').forEach(btn => {
        btn.classList.remove('active');
        btn.classList.add('btn-outline-secondary');
    });

    // Find the button with the matching days value and activate it
    document.querySelectorAll('#cashflow .card-tools .btn-group .btn').forEach(btn => {
        if (btn.textContent.includes(days + 'D')) {
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('active');
        }
    });

    // Reload chart with new days parameter
    fetch(buildUrl(addCorpParam(`/corp-wallet-manager/api/analytics/daily-cashflow?days=${days}`)))
        .then(response => response.json())
        .then(data => {
            // Update statistics BEFORE creating the chart
            if (data.statistics) {
                const statsHtml = `
                    <div class="row mt-3">
                        <div class="col-md-3">
                            <small class="text-muted">Total Income:</small>
                            <p class="mb-0 text-success">${formatISK(data.statistics.total_income, true)}</p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Total Expenses:</small>
                            <p class="mb-0 text-danger">${formatISK(data.statistics.total_expenses, true)}</p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Net Total:</small>
                            <p class="mb-0">${formatISK(data.statistics.net_total, true)}</p>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted">Daily Average:</small>
                            <p class="mb-0">${formatISK(data.statistics.average_daily_flow, true)}</p>
                        </div>
                    </div>
                `;
                document.getElementById('cashflow-statistics').innerHTML = statsHtml;
            }

            // Destroy existing chart
            if (dailyCashflowChart) {
                dailyCashflowChart.destroy();
                dailyCashflowChart = null;
            }

            const canvas = document.getElementById('daily-cashflow-chart');
            const ctx = canvas.getContext('2d');

            // Explicit height control
            canvas.parentNode.style.height = '400px';
            canvas.parentNode.style.width = '100%';

            dailyCashflowChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Net Cash Flow',
                        data: data.datasets ? data.datasets.net_flow : [],
                        backgroundColor: data.datasets && data.datasets.net_flow ?
                            data.datasets.net_flow.map(v => v >= 0 ? '#10b981' : '#ef4444') : [],
                        borderColor: '#1f2937',
                        borderWidth: 1
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
                                    return 'Net Flow: ' + formatISK(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatAxisValue(value);
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error loading daily cash flow for ' + days + ' days:', error);
        });
}

// Reports Tab Functions
function loadReportsData() {
    generateExecutiveSummary();
    populateReportMonths();
}

function generateExecutiveSummary() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/analytics/executive-summary')))
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Executive summary error:', data.error);
                return;
            }

            let insightsHtml = '';
            data.insights.forEach(insight => {
                insightsHtml += `<li>${insight}</li>`;
            });
            document.getElementById('key-insights').innerHTML = insightsHtml || '<li>No insights available</li>';

            let recommendationsHtml = '';
            data.recommendations.forEach(rec => {
                recommendationsHtml += `<li>${rec}</li>`;
            });
            document.getElementById('recommendations').innerHTML = recommendationsHtml || '<li>No recommendations at this time</li>';

            let riskClass = 'alert-info';
            if (data.risk_assessment.level === 'Critical') riskClass = 'alert-danger';
            else if (data.risk_assessment.level === 'High') riskClass = 'alert-warning';
            else if (data.risk_assessment.level === 'Medium') riskClass = 'alert-warning';

            let riskHtml = `
                <div class="alert ${riskClass}">
                    <strong>Risk Level: ${data.risk_assessment.level}</strong>
                    <ul class="mb-0 mt-2">
            `;

            data.risk_assessment.factors.forEach(factor => {
                riskHtml += `<li>${factor}</li>`;
            });

            riskHtml += '</ul></div>';
            document.getElementById('risk-assessment').innerHTML = riskHtml;
        })
        .catch(error => {
            console.error('Error loading executive summary:', error);
        });
}

function populateReportMonths() {
    const select = document.getElementById('report-month');
    if (!select) return;

    let html = '';
    for (let i = 0; i < 12; i++) {
        const date = new Date();
        date.setMonth(date.getMonth() - i);
        const value = date.toISOString().slice(0, 7);
        const label = date.toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
        html += `<option value="${value}">${label}</option>`;
    }
    select.innerHTML = html;
}

function exportReport(format) {
    alert('Export to ' + format.toUpperCase() + ' feature coming soon!');
}

function generateMonthlyReport() {
    alert('Monthly report generation coming soon!');
}

function generateCustomReport() {
    alert('Custom report builder coming soon!');
}

// Refresh all data
function refreshData() {
    // Load Overview tab data
    loadActualBalance();
    loadTodayChanges();
    loadCurrentData();
    loadDivisionBreakdown();
    loadBalanceChart(currentChartMode);
    loadIncomeExpenseChart();
    loadPredictionChart();
    loadIncomeBreakdown();
    loadExpenseBreakdown();

    // Check which tab is active and load its data
    const activeTab = document.querySelector('.nav-link.active').getAttribute('href');
    switch(activeTab) {
        case '#analytics':
            loadAnalyticsData();
            break;
        case '#trends':
            loadTrendsData();
            break;
        case '#performance':
            loadPerformanceData();
            break;
        case '#cashflow':
            loadCashFlowData();
            break;
        case '#reports':
            loadReportsData();
            break;
    }
}

// Tab change handler
document.addEventListener('DOMContentLoaded', function() {
    // Load corporation settings first
    loadCorporationSettings();

    // Handle tab clicks directly
    $('.nav-tabs a[data-toggle="tab"]').on('click', function(e) {
        const target = $(this).attr('href');
        console.log('Tab switching to:', target);

        // Wait for tab animation to complete
        setTimeout(function() {
            switch(target) {
                case '#analytics':
                    loadAnalyticsData();
                    break;
                case '#trends':
                    loadTrendsData();
                    break;
                case '#performance':
                    loadPerformanceData();
                    break;
                case '#cashflow':
                    loadCashFlowData();
                    break;
                case '#reports':
                    loadReportsData();
                    break;
            }
        }, 200);
    });
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (config.refreshTimer) {
        clearInterval(config.refreshTimer);
    }
});
</script>
@endpush
