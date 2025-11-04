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
            @can('corpwalletmanager.settings')
                <a href="{{ route('corpwalletmanager.settings') }}" class="float-right">
                    <i class="fas fa-cog"></i> Change in Settings
                </a>
            @endcan
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
                            <h3 class="card-title">Balance Forecast</h3>
                            <div class="card-tools">
                                <div class="btn-group btn-group-sm mr-2">
                                    <button type="button" class="btn btn-secondary active" onclick="updatePredictionDays(30)">30D</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="updatePredictionDays(60)">60D</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="updatePredictionDays(90)">90D</button>
                                </div>
                                <button type="button" class="btn btn-tool" onclick="refreshData()">
                                    <i class="fas fa-sync-alt"></i> Refresh All
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <canvas id="predictionChart" height="300"></canvas> <!-- Increased height -->
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
                <div class="tab-pane fade" id="reports" role="tabpanel">
                    
                    <!-- Executive Summary Section -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-chart-line"></i> Executive Summary
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5>Key Insights</h5>
                                            <ul id="key-insights">
                                                <li><i class="fas fa-spinner fa-spin"></i> Loading insights...</li>
                                            </ul>
                                            
                                            <h5 class="mt-3">Recommendations</h5>
                                            <ul id="recommendations">
                                                <li><i class="fas fa-spinner fa-spin"></i> Loading recommendations...</li>
                                            </ul>
                                        </div>
                                        <div class="col-md-6">
                                            <h5>Risk Assessment</h5>
                                            <div id="risk-assessment">
                                                <i class="fas fa-spinner fa-spin"></i> Loading risk assessment...
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                
                    <!-- Report Generator Section -->
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-file-alt"></i> Generate Report
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <form id="reportGeneratorForm">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="report_type">Report Type</label>
                                                    <select class="form-control" id="report_type" name="report_type">
                                                        <option value="executive">Executive Summary</option>
                                                        <option value="financial">Financial Analysis</option>
                                                        <option value="division">Division Performance</option>
                                                        <option value="custom">Custom Report</option>
                                                    </select>
                                                </div>
                                            </div>
                
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="date_from">From Date</label>
                                                    <input type="date" class="form-control" id="date_from" name="date_from">
                                                </div>
                                            </div>
                
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="date_to">To Date</label>
                                                    <input type="date" class="form-control" id="date_to" name="date_to">
                                                </div>
                                            </div>
                
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>&nbsp;</label>
                                                    <div>
                                                        <button type="button" class="btn btn-sm btn-secondary" onclick="setQuickDate('week')">
                                                            This Week
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-secondary" onclick="setQuickDate('month')">
                                                            This Month
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                
                                        <!-- Custom Report Sections (hidden by default) -->
                                        <div class="row" id="custom-sections" style="display: none;">
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label>Include Sections</label>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input" id="section_balance" value="balance_history" checked>
                                                                <label class="custom-control-label" for="section_balance">Balance History</label>
                                                            </div>
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input" id="section_income" value="income_analysis" checked>
                                                                <label class="custom-control-label" for="section_income">Income Analysis</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input" id="section_expense" value="expense_analysis" checked>
                                                                <label class="custom-control-label" for="section_expense">Expense Analysis</label>
                                                            </div>
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input" id="section_transactions" value="transaction_breakdown" checked>
                                                                <label class="custom-control-label" for="section_transactions">Transaction Breakdown</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input" id="section_divisions" value="division_summary" checked>
                                                                <label class="custom-control-label" for="section_divisions">Division Summary</label>
                                                            </div>
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input" id="section_risk" value="risk_assessment" checked>
                                                                <label class="custom-control-label" for="section_risk">Risk Assessment</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="custom-control custom-checkbox">
                                                    <input type="checkbox" class="custom-control-input" id="send_to_discord">
                                                    <label class="custom-control-label" for="send_to_discord">
                                                        Send to Discord <small class="text-muted">(if enabled in settings)</small>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                
                                        <div class="form-group mt-3">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-play"></i> Generate Report
                                            </button>
                                            <button type="button" class="btn btn-info" onclick="loadReportHistory()">
                                                <i class="fas fa-sync-alt"></i> Refresh History
                                            </button>
                                        </div>
                
                                        <div id="report-status" class="mt-2"></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                
                    <!-- Report History Section -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-history"></i> Report History
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div id="report-history-table">
                                        <div class="text-center">
                                            <i class="fas fa-spinner fa-spin"></i> Loading report history...
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                
                </div>
                <!-- End Reports Tab -->
                
                <!-- Report View Modal -->
                <div class="modal fade" id="reportViewModal" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-xl" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-file-alt"></i> Report Details
                                </h5>
                                <button type="button" class="close" data-dismiss="modal">
                                    <span>&times;</span>
                                </button>
                            </div>
                            <div class="modal-body" id="reportModalContent">
                                <div class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading report...
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
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
let currentPredictionDays = 30;
let divisionCashflowChart = null;
let divisionMiniCharts = {};
let currentDivisionId = null;
let currentDivisionDays = 7;

// Load corporation settings
function loadCorporationSettings() {
    fetch(buildUrl('/corp-wallet-manager/api/selected-corporation'))
        .then(response => response.json())
        .then(data => {
            config.corporationId = data.corporation_id;
            config.refreshInterval = data.refresh_interval;

            // Update display with corporation name
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
function loadPredictionChart(days = null) {
    const requestDays = days || currentPredictionDays || 30;
    
    fetch(buildUrl(addCorpParam(`/corp-wallet-manager/api/predictions?days=${requestDays}`)))
        .then(response => response.json())
        .then(data => {
            // Destroy existing chart
            if (predictionChart) {
                predictionChart.destroy();
                predictionChart = null;
            }

            const canvas = document.getElementById('predictionChart');
            const ctx = canvas.getContext('2d');

            // Increased height for better visibility
            canvas.parentNode.style.height = '400px'; // Increased from 300px
            canvas.parentNode.style.width = '100%';

            if (!data.data || data.data.length === 0) {
                ctx.font = '20px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('No prediction data available', canvas.width / 2, canvas.height / 2);
                return;
            }

            // Prepare datasets for chart
            const datasets = [
                {
                    label: 'Predicted Balance',
                    data: data.data || data.predictions || [],
                    borderColor: config.colorPredicted,
                    backgroundColor: 'transparent',
                    borderWidth: 3,
                    tension: 0.4,
                    pointRadius: requestDays > 30 ? 1 : 2, // Smaller points for longer ranges
                    pointHoverRadius: 6
                }
            ];

            // Add confidence bands if available
            if (data.confidence_bands) {
                // Color intensity based on range
                const alphaMultiplier = requestDays > 60 ? '20' : requestDays > 30 ? '30' : '40';
                
                datasets.push({
                    label: '68% Confidence Range',
                    data: data.confidence_bands.upper_68,
                    borderColor: config.colorPredicted + alphaMultiplier,
                    backgroundColor: 'transparent',
                    borderDash: [5, 5],
                    borderWidth: 1,
                    pointRadius: 0,
                    fill: '+1'
                });
                
                datasets.push({
                    label: '68% Lower Bound',
                    data: data.confidence_bands.lower_68,
                    borderColor: config.colorPredicted + alphaMultiplier,
                    backgroundColor: config.colorPredicted + '20',
                    borderDash: [5, 5],
                    borderWidth: 1,
                    pointRadius: 0,
                    fill: '-1'
                });
                
                // Add 95% confidence for 30-day view only (less clutter)
                if (requestDays <= 30 && data.confidence_bands.upper_95 && data.confidence_bands.upper_95.length > 0) {
                    datasets.push({
                        label: '95% Confidence Range',
                        data: data.confidence_bands.upper_95,
                        borderColor: 'transparent',
                        backgroundColor: config.colorPredicted + '10',
                        borderWidth: 0,
                        pointRadius: 0,
                        fill: '+1'
                    });
                    
                    datasets.push({
                        label: '95% Lower Bound',
                        data: data.confidence_bands.lower_95,
                        borderColor: 'transparent',
                        backgroundColor: 'transparent',
                        borderWidth: 0,
                        pointRadius: 0,
                        fill: false
                    });
                }
            }

            // Determine confidence text based on range
            let confidenceText = '';
            if (requestDays <= 30) {
                confidenceText = 'High confidence (based on 12-month weighted analysis)';
            } else if (requestDays <= 60) {
                confidenceText = 'Medium confidence (extended forecast)';
            } else {
                confidenceText = 'Low confidence (long-range forecast)';
            }

            predictionChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: datasets
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
                            display: true,
                            position: 'top',
                            labels: {
                                filter: function(item) {
                                    // Only show main prediction in legend
                                    return item.text === 'Predicted Balance';
                                },
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.label === 'Predicted Balance') {
                                        const confidence = data.confidence_values ? data.confidence_values[context.dataIndex] : null;
                                        let label = 'Predicted: ' + formatISK(context.parsed.y);
                                        if (confidence) {
                                            label += ` (${confidence}% confidence)`;
                                        }
                                        return label;
                                    }
                                    return context.dataset.label + ': ' + formatISK(context.parsed.y);
                                },
                                afterBody: function(tooltipItems) {
                                    const index = tooltipItems[0].dataIndex;
                                    const factors = data.factors ? data.factors[index] : null;
                                    
                                    if (factors && requestDays <= 30) { // Only show factors for 30-day view
                                        let details = [];
                                        if (factors.seasonal) details.push(`Seasonal: ${(factors.seasonal * 100).toFixed(0)}%`);
                                        if (factors.momentum) details.push(`Momentum: ${(factors.momentum * 100).toFixed(0)}%`);
                                        if (factors.activity) details.push(`Activity: ${(factors.activity * 100).toFixed(0)}%`);
                                        return details;
                                    }
                                    return [];
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: `${requestDays}-Day Balance Forecast with Confidence Intervals`,
                            font: {
                                size: 14
                            }
                        },
                        subtitle: {
                            display: true,
                            text: confidenceText,
                            font: {
                                size: 11,
                                style: 'italic'
                            },
                            color: '#666'
                        }
                    },
                    scales: {
                        x: {
                            title: {
                                display: true,
                                text: 'Date'
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                maxTicksLimit: requestDays > 60 ? 15 : requestDays > 30 ? 10 : 8
                            }
                        },
                        y: {
                            title: {
                                display: true,
                                text: 'Balance (ISK)'
                            },
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

// Update prediction chart time range
function updatePredictionDays(days) {
    currentPredictionDays = days;
    
    // Update button states
    document.querySelectorAll('.card-tools .btn-group .btn').forEach(btn => {
        if (btn.textContent.includes('D') && btn.onclick && btn.onclick.toString().includes('updatePredictionDays')) {
            btn.classList.remove('btn-secondary', 'active');
            btn.classList.add('btn-outline-secondary');
        }
    });
    
    // Activate the clicked button
    event.target.classList.remove('btn-outline-secondary');
    event.target.classList.add('btn-secondary', 'active');
    
    // Reload the prediction chart with new range
    loadPredictionChart(days);
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
    loadCashFlowWaterfall(); // Updated version with proper starting balance
    loadIncomeCategoriesDetailed();
    loadExpenseCategoriesDetailed();
    loadNetFlowSummary();
    loadDailyCashFlowTrend();
    loadDivisionsList(); // New function to load divisions
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
                        },
                        // Add subtitle plugin to show period inside the chart
                        subtitle: {
                            display: true,
                            text: 'Period: ' + (data.balance.month || 'Current Month'),
                            position: 'top',
                            font: {
                                size: 12,
                                style: 'italic'
                            },
                            color: '#6b7280',
                            padding: {
                                top: 5,
                                bottom: 5
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

function loadDailyCashFlowTrend(days = 30) { // Default parameter
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
    document.querySelectorAll('#cashflow .row:nth-child(5) .card-tools .btn-group .btn').forEach(btn => {
        btn.classList.remove('active');
        btn.classList.add('btn-outline-secondary');
    });

    // Find the button with the matching days value and activate it
    document.querySelectorAll('#cashflow .row:nth-child(5) .card-tools .btn-group .btn').forEach(btn => {
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

// Load divisions list and populate selector
function loadDivisionsList() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/analytics/divisions-list')))
        .then(response => response.json())
        .then(data => {
            const selector = document.getElementById('division-selector');
            if (!selector) return;
            
            let html = '<option value="">Select a division...</option>';
            data.divisions.forEach(div => {
                html += `<option value="${div.id}">${div.name} (${formatISK(div.balance, true)})</option>`;
            });
            selector.innerHTML = html;
            
            // Auto-select first division if available
            if (data.divisions.length > 0) {
                selector.value = data.divisions[0].id;
                currentDivisionId = data.divisions[0].id;
                loadDivisionCashFlow(currentDivisionDays);
            }
            
            // Also load division comparison grid
            loadDivisionComparisonGrid(data.divisions);
        })
        .catch(error => {
            console.error('Error loading divisions list:', error);
        });
}

// Load division-specific cash flow
function loadDivisionCashFlow(days) {
    const selector = document.getElementById('division-selector');
    const divisionId = selector ? selector.value : currentDivisionId;
    
    if (!divisionId) {
        console.log('No division selected');
        return;
    }
    
    currentDivisionDays = days;
    currentDivisionId = divisionId;
    
    // Update button states
    document.querySelectorAll('#cashflow .card:nth-child(2) .btn-group .btn').forEach(btn => {
        btn.classList.remove('active');
        btn.classList.add('btn-outline-secondary');
    });
    
    // Find and activate the correct button
    document.querySelectorAll('#cashflow .card:nth-child(2) .btn-group .btn').forEach(btn => {
        if (btn.textContent.includes(days + 'D')) {
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('active');
        }
    });
    
    fetch(buildUrl(addCorpParam(`/corp-wallet-manager/api/analytics/division-daily-cashflow?division_id=${divisionId}&days=${days}`)))
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error('Division cash flow error:', data.error);
                return;
            }
            
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
                            <small class="text-muted">Days Positive/Negative:</small>
                            <p class="mb-0">
                                <span class="text-success">${data.statistics.days_positive}</span> / 
                                <span class="text-danger">${data.statistics.days_negative}</span>
                            </p>
                        </div>
                    </div>
                `;
                document.getElementById('division-cashflow-stats').innerHTML = statsHtml;
            }
            
            // Destroy existing chart
            if (divisionCashflowChart) {
                divisionCashflowChart.destroy();
                divisionCashflowChart = null;
            }
            
            const canvas = document.getElementById('division-cashflow-chart');
            const ctx = canvas.getContext('2d');
            
            // Explicit height control
            canvas.parentNode.style.height = '400px';
            canvas.parentNode.style.width = '100%';
            
            divisionCashflowChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [
                        {
                            label: 'Daily Income',
                            data: data.datasets ? data.datasets.income : [],
                            borderColor: '#10b981',
                            backgroundColor: '#10b98120',
                            borderWidth: 2,
                            tension: 0.3
                        },
                        {
                            label: 'Daily Expenses',
                            data: data.datasets ? data.datasets.expenses : [],
                            borderColor: '#ef4444',
                            backgroundColor: '#ef444420',
                            borderWidth: 2,
                            tension: 0.3
                        },
                        {
                            label: 'Net Flow',
                            data: data.datasets ? data.datasets.net_flow : [],
                            borderColor: '#3b82f6',
                            backgroundColor: '#3b82f620',
                            borderWidth: 3,
                            tension: 0.3,
                            fill: true
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
                        },
                        title: {
                            display: true,
                            text: `${data.division_name} - ${days} Day Cash Flow`
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
            console.error('Error loading division cash flow:', error);
        });
}

// Load division comparison grid with mini line charts and statistics
function loadDivisionComparisonGrid(divisions) {
    const container = document.getElementById('division-comparison-grid');
    if (!container) return;
    
    // Clear existing mini charts
    Object.values(divisionMiniCharts).forEach(chart => {
        if (chart) chart.destroy();
    });
    divisionMiniCharts = {};
    
    let html = '';
    divisions.forEach(div => {
        html += `
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header py-2">
                        <h6 class="mb-0">${div.name}</h6>
                        <small class="text-muted">Balance: ${formatISK(div.balance, true)}</small>
                    </div>
                    <div class="card-body p-2">
                        <div style="height: 120px; position: relative;">
                            <canvas id="mini-chart-${div.id}"></canvas>
                        </div>
                        <div id="mini-stats-${div.id}" class="mt-2" style="font-size: 11px;">
                            <div class="text-center text-muted">
                                <i class="fas fa-spinner fa-spin"></i> Loading...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // Load data for each mini chart
    divisions.forEach(div => {
        loadMiniDivisionChart(div.id);
    });
}

// Load mini line chart for a specific division with statistics
function loadMiniDivisionChart(divisionId) {
    fetch(buildUrl(addCorpParam(`/corp-wallet-manager/api/analytics/division-daily-cashflow?division_id=${divisionId}&days=7`)))
        .then(response => response.json())
        .then(data => {
            if (data.error) return;
            
            const canvas = document.getElementById(`mini-chart-${divisionId}`);
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            
            // Create line chart instead of bar chart
            divisionMiniCharts[divisionId] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [
                        {
                            label: 'Income',
                            data: data.datasets ? data.datasets.income : [],
                            borderColor: '#10b981',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            tension: 0.3,
                            pointRadius: 0,
                            pointHoverRadius: 3
                        },
                        {
                            label: 'Expenses',
                            data: data.datasets ? data.datasets.expenses : [],
                            borderColor: '#ef4444',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            tension: 0.3,
                            pointRadius: 0,
                            pointHoverRadius: 3
                        },
                        {
                            label: 'Net',
                            data: data.datasets ? data.datasets.net_flow : [],
                            borderColor: '#3b82f6',
                            backgroundColor: '#3b82f620',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.3,
                            pointRadius: 0,
                            pointHoverRadius: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: { 
                            display: false 
                        },
                        tooltip: {
                            enabled: true,
                            position: 'nearest',
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatISK(context.parsed.y, true);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: false,
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            display: true,
                            grid: {
                                display: true,
                                drawBorder: false,
                                color: 'rgba(0,0,0,0.05)'
                            },
                            ticks: {
                                display: true,
                                callback: function(value) {
                                    return formatAxisValue(value);
                                },
                                maxTicksLimit: 3,
                                font: {
                                    size: 9
                                }
                            }
                        }
                    }
                }
            });
            
            // Update statistics below the chart
            if (data.statistics) {
                const statsDiv = document.getElementById(`mini-stats-${divisionId}`);
                if (statsDiv) {
                    const incomeFormatted = formatISK(data.statistics.total_income, true);
                    const expenseFormatted = formatISK(data.statistics.total_expenses, true);
                    const netFormatted = formatISK(data.statistics.net_total, true);
                    const netClass = data.statistics.net_total >= 0 ? 'text-success' : 'text-danger';
                    
                    statsDiv.innerHTML = `
                        <div class="row text-center" style="line-height: 1.2;">
                            <div class="col-6 px-1">
                                <small class="text-muted d-block">Income</small>
                                <small class="text-success font-weight-bold">${incomeFormatted}</small>
                            </div>
                            <div class="col-6 px-1">
                                <small class="text-muted d-block">Expense</small>
                                <small class="text-danger font-weight-bold">${expenseFormatted}</small>
                            </div>
                        </div>
                        <div class="row text-center mt-1" style="line-height: 1.2;">
                            <div class="col-6 px-1">
                                <small class="text-muted d-block">Net Total</small>
                                <small class="${netClass} font-weight-bold">${netFormatted}</small>
                            </div>
                            <div class="col-6 px-1">
                                <small class="text-muted d-block">Days +/-</small>
                                <small class="font-weight-bold">
                                    <span class="text-success">${data.statistics.days_positive}</span>/<span class="text-danger">${data.statistics.days_negative}</span>
                                </small>
                            </div>
                        </div>
                    `;
                }
            }
        })
        .catch(error => {
            console.error(`Error loading mini chart for division ${divisionId}:`, error);
            // Update stats div with error message
            const statsDiv = document.getElementById(`mini-stats-${divisionId}`);
            if (statsDiv) {
                statsDiv.innerHTML = '<div class="text-center text-danger"><small>Failed to load data</small></div>';
            }
        });
}

// Update the division selector when it changes
document.addEventListener('DOMContentLoaded', function() {
    // Add event listener for division selector
    const selector = document.getElementById('division-selector');
    if (selector) {
        selector.addEventListener('change', function() {
            currentDivisionId = this.value;
            if (currentDivisionId) {
                loadDivisionCashFlow(currentDivisionDays);
            }
        });
    }
});

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

// Set default dates on page load
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const monthAgo = new Date(today);
    monthAgo.setMonth(monthAgo.getMonth() - 1);
    
    document.getElementById('date_to').value = today.toISOString().split('T')[0];
    document.getElementById('date_from').value = monthAgo.toISOString().split('T')[0];
});

// Show/hide custom sections based on report type
document.getElementById('report_type').addEventListener('change', function() {
    const customSections = document.getElementById('custom-sections');
    if (this.value === 'custom') {
        customSections.style.display = 'block';
    } else {
        customSections.style.display = 'none';
    }
});

// Set quick date ranges
function setQuickDate(range) {
    const today = new Date();
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    dateTo.value = today.toISOString().split('T')[0];
    
    switch(range) {
        case 'week':
            const weekAgo = new Date(today);
            weekAgo.setDate(weekAgo.getDate() - 7);
            dateFrom.value = weekAgo.toISOString().split('T')[0];
            break;
        case 'month':
            const monthAgo = new Date(today);
            monthAgo.setMonth(monthAgo.getMonth() - 1);
            dateFrom.value = monthAgo.toISOString().split('T')[0];
            break;
    }
}

// Generate report (replaces generateMonthlyReport and generateCustomReport)
document.getElementById('reportGeneratorForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const reportType = document.getElementById('report_type').value;
    const dateFrom = document.getElementById('date_from').value;
    const dateTo = document.getElementById('date_to').value;
    const sendToDiscord = document.getElementById('send_to_discord').checked;
    
    // Get selected sections for custom reports
    let sections = [];
    if (reportType === 'custom') {
        const checkboxes = document.querySelectorAll('#custom-sections input[type="checkbox"]:checked');
        sections = Array.from(checkboxes).map(cb => cb.value);
        
        if (sections.length === 0) {
            alert('Please select at least one section for your custom report');
            return;
        }
    }
    
    // Show loading status
    document.getElementById('report-status').innerHTML = 
        '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Generating report...</div>';
    
    // Generate report
    fetch(buildUrl('/corp-wallet-manager/reports/generate'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        },
        body: JSON.stringify({
            report_type: reportType,
            date_from: dateFrom,
            date_to: dateTo,
            sections: sections,
            send_to_discord: sendToDiscord
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('report-status').innerHTML = 
                '<div class="alert alert-success"><i class="fas fa-check"></i> ' + data.message + '</div>';
            
            // Refresh report history
            setTimeout(() => {
                loadReportHistory();
            }, 1000);
        } else {
            document.getElementById('report-status').innerHTML = 
                '<div class="alert alert-danger"><i class="fas fa-times"></i> ' + data.message + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('report-status').innerHTML = 
            '<div class="alert alert-danger"><i class="fas fa-times"></i> Failed to generate report</div>';
    });
});

// Load report history
function loadReportHistory() {
    fetch(buildUrl('/corp-wallet-manager/reports/history'))
        .then(response => response.json())
        .then(data => {
            const historyDiv = document.getElementById('report-history-table');
            
            if (data.reports && data.reports.length > 0) {
                let html = '<div class="table-responsive"><table class="table table-hover table-sm">';
                html += '<thead><tr>';
                html += '<th>Type</th><th>Period</th><th>Generated</th><th>Actions</th>';
                html += '</tr></thead><tbody>';
                
                data.reports.forEach(report => {
                    const created = new Date(report.created_at).toLocaleString();
                    const typeBadge = {
                        'executive': 'badge-primary',
                        'financial': 'badge-success',
                        'division': 'badge-info',
                        'custom': 'badge-warning'
                    }[report.report_type] || 'badge-secondary';
                    
                    html += `<tr>
                        <td><span class="badge ${typeBadge}">${report.report_type}</span></td>
                        <td><small>${report.date_from} to ${report.date_to}</small></td>
                        <td><small>${created}</small></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewReport(${report.id})">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>
                    </tr>`;
                });
                
                html += '</tbody></table></div>';
                historyDiv.innerHTML = html;
            } else {
                historyDiv.innerHTML = '<p class="text-muted">No reports generated yet.</p>';
            }
        })
        .catch(error => {
            console.error('Error loading report history:', error);
            document.getElementById('report-history-table').innerHTML = 
                '<p class="text-danger">Failed to load report history</p>';
        });
}

// View report in modal
function viewReport(reportId) {
    // Show modal
    $('#reportViewModal').modal('show');
    
    // Load report data
    document.getElementById('reportModalContent').innerHTML = 
        '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading report...</p></div>';
    
    // Fetch report from history
    fetch(buildUrl('/corp-wallet-manager/reports/history'))
        .then(response => response.json())
        .then(data => {
            const report = data.reports.find(r => r.id == reportId);
            
            if (!report) {
                document.getElementById('reportModalContent').innerHTML = 
                    '<div class="alert alert-danger">Report not found</div>';
                return;
            }
            
            // Parse report data
            const reportData = JSON.parse(report.data);
            
            // Build report HTML
            let html = '<div class="report-content">';
            
            // Header
            html += `<div class="row mb-3">
                <div class="col-md-6">
                    <h4>${getReportTitle(report.report_type)}</h4>
                    <p class="text-muted">
                        Period: ${report.date_from} to ${report.date_to} 
                        (${reportData.period.days} days)
                    </p>
                </div>
                <div class="col-md-6 text-right">
                    <small class="text-muted">Generated: ${new Date(report.created_at).toLocaleString()}</small>
                </div>
            </div>`;
            
            // Balance History
            if (reportData.balance_history) {
                const bh = reportData.balance_history;
                const changeClass = bh.change >= 0 ? 'text-success' : 'text-danger';
                const changeIcon = bh.change >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
                
                html += `<div class="card mb-3">
                    <div class="card-header"><strong>Balance History</strong></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <p><strong>Starting Balance:</strong><br>${formatISK(bh.start_balance)}</p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Ending Balance:</strong><br>${formatISK(bh.end_balance)}</p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Change:</strong><br>
                                    <span class="${changeClass}">
                                        <i class="fas ${changeIcon}"></i> 
                                        ${formatISK(bh.change)} (${bh.change_percent >= 0 ? '+' : ''}${bh.change_percent.toFixed(2)}%)
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>`;
            }
            
            // Income & Expenses
            if (reportData.income_analysis && reportData.expense_analysis) {
                html += `<div class="row mb-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-success text-white"><strong>Income Analysis</strong></div>
                            <div class="card-body">
                                <p><strong>Total Income:</strong> ${formatISK(reportData.income_analysis.total)}</p>
                                <p><strong>Transactions:</strong> ${reportData.income_analysis.transactions.toLocaleString()}</p>
                                <p><strong>Average:</strong> ${formatISK(reportData.income_analysis.average)}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-danger text-white"><strong>Expense Analysis</strong></div>
                            <div class="card-body">
                                <p><strong>Total Expenses:</strong> ${formatISK(reportData.expense_analysis.total)}</p>
                                <p><strong>Transactions:</strong> ${reportData.expense_analysis.transactions.toLocaleString()}</p>
                                <p><strong>Average:</strong> ${formatISK(reportData.expense_analysis.average)}</p>
                            </div>
                        </div>
                    </div>
                </div>`;
            }
            
            // Risk Assessment
            if (reportData.risk_assessment) {
                const risk = reportData.risk_assessment;
                const riskColors = {
                    'HIGH': 'danger',
                    'MEDIUM': 'warning',
                    'LOW': 'success',
                    'VERY_LOW': 'info'
                };
                const riskColor = riskColors[risk.risk_level] || 'secondary';
                
                html += `<div class="card mb-3">
                    <div class="card-header bg-${riskColor} text-white"><strong>Risk Assessment</strong></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <p><strong>Risk Level:</strong><br>
                                    <span class="badge badge-${riskColor}">${risk.risk_level}</span>
                                </p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Days of Runway:</strong><br>${risk.days_of_runway.toFixed(1)} days</p>
                            </div>
                            <div class="col-md-4">
                                <p><strong>Current Balance:</strong><br>${formatISK(risk.current_balance)}</p>
                            </div>
                        </div>
                    </div>
                </div>`;
            }
            
            // Division Summary
            if (reportData.division_summary && reportData.division_summary.length > 0) {
                html += `<div class="card">
                    <div class="card-header"><strong>Division Performance</strong></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Division</th>
                                        <th>Income</th>
                                        <th>Expenses</th>
                                        <th>Net Change</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                
                reportData.division_summary.forEach(div => {
                    const netClass = div.net_change >= 0 ? 'text-success' : 'text-danger';
                    html += `<tr>
                        <td>Division ${div.division}</td>
                        <td class="text-success">${formatISK(div.income)}</td>
                        <td class="text-danger">${formatISK(div.expenses)}</td>
                        <td class="${netClass}">${formatISK(div.net_change)}</td>
                    </tr>`;
                });
                
                html += `</tbody></table></div>
                    </div>
                </div>`;
            }
            
            html += '</div>';
            
            document.getElementById('reportModalContent').innerHTML = html;
        })
        .catch(error => {
            console.error('Error viewing report:', error);
            document.getElementById('reportModalContent').innerHTML = 
                '<div class="alert alert-danger">Failed to load report</div>';
        });
}

// Helper function for report titles
function getReportTitle(type) {
    const titles = {
        'executive': 'Executive Summary Report',
        'financial': 'Financial Analysis Report',
        'division': 'Division Performance Report',
        'custom': 'Custom Report'
    };
    return titles[type] || 'Report';
}

// Update loadReportsData() function
function loadReportsData() {
    generateExecutiveSummary();
    loadReportHistory();
}

// Remove old placeholder functions (if they still exist)
function generateMonthlyReport() {
    // This is now handled by the form submission
    document.getElementById('report_type').value = 'financial';
    document.getElementById('reportGeneratorForm').scrollIntoView({ behavior: 'smooth' });
}

function generateCustomReport() {
    // This is now handled by the form submission  
    document.getElementById('report_type').value = 'custom';
    document.getElementById('custom-sections').style.display = 'block';
    document.getElementById('reportGeneratorForm').scrollIntoView({ behavior: 'smooth' });
}

function exportReport(format) {
    alert('Export to ' + format.toUpperCase() + ' will be added in a future update.\n\n' +
          'For now, you can view and send reports to Discord.');
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
    loadPredictionChart(currentPredictionDays);
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
