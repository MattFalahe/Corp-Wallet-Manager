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
                                    <canvas id="weekly-pattern-chart" height="200"></canvas>
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
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Cash Flow Waterfall Chart</h3>
                                </div>
                                <div class="card-body">
                                    <canvas id="cashflow-waterfall" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

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

                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">Daily Cash Flow Trend</h3>
                                    <div class="card-tools">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary active" onclick="loadDailyCashFlowWithDays(30)">30D</button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="loadDailyCashFlowWithDays(60)">60D</button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="loadDailyCashFlowWithDays(90)">90D</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <canvas id="daily-cashflow-chart" height="200"></canvas>
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
