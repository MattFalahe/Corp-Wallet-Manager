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
                <!-- Overview Tab (Current Content) -->
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

                    <!-- Balance History Chart - Full Width -->
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

                    <!-- Income vs Expenses Chart - Full Width -->
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

                    <!-- Pie Charts Row - Side by Side -->
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

                    <!-- Prediction Chart - Larger Height -->
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
                                        <!-- Will be populated by JavaScript -->
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
                                    <h3 class="card-title">Detailed Cash Flow Analysis</h3>
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
                                    <h3 class="card-title">Net Flow</h3>
                                </div>
                                <div class="card-body">
                                    <div class="text-center">
                                        <h2 id="net-flow-total">0 ISK</h2>
                                        <p class="text-muted">This Month</p>
                                    </div>
                                    <hr>
                                    <div id="flow-breakdown">
                                        <!-- Will be populated -->
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
// Configuration
let config = {
    decimals: {{ config('corpwalletmanager.decimals', 2) }},
    colorActual: "{{ config('corpwalletmanager.color_actual', '#4cafef') }}",
    colorPredicted: "{{ config('corpwalletmanager.color_predicted', '#ef4444') }}",
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
let currentChartMode = 'flow';

// Helper function to build URLs
function buildUrl(path) {
    const currentUrl = window.location;
    const baseUrl = currentUrl.protocol + '//' + currentUrl.host;
    return baseUrl + path;
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

// Format ISK values
function formatISK(value, compact = false) {
    if (!isFinite(value) || isNaN(value)) {
        return '0.00 ISK';
    }
    
    if (compact && Math.abs(value) > 1000000000) {
        return (value / 1000000000).toFixed(2) + 'B ISK';
    } else if (compact && Math.abs(value) > 1000000) {
        return (value / 1000000).toFixed(2) + 'M ISK';
    }
    
    return new Intl.NumberFormat('en-US', {
        style: 'decimal',
        minimumFractionDigits: config.decimals,
        maximumFractionDigits: config.decimals
    }).format(value) + ' ISK';
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
            const ctx = document.getElementById('balanceChart').getContext('2d');
            
            if (balanceChart) {
                balanceChart.destroy();
            }
            
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
                                    return formatISK(value, true).replace(' ISK', '');
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

// Load income vs expense chart - Now as LINE chart
function loadIncomeExpenseChart() {
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/income-expense?months=12')))
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('incomeExpenseChart').getContext('2d');
            
            if (incomeExpenseChart) {
                incomeExpenseChart.destroy();
            }
            
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
                                    return formatISK(value, true).replace(' ISK', '');
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
            const ctx = document.getElementById('incomeBreakdownChart');
            if (!ctx) {
                console.error('Income breakdown chart canvas not found');
                return;
            }
            
            if (incomeBreakdownChart) {
                incomeBreakdownChart.destroy();
            }
            
            // Generate colors for each segment
            const colors = [
                '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b',
                '#06b6d4', '#84cc16', '#f97316', '#6366f1', '#14b8a6'
            ];
            
            incomeBreakdownChart = new Chart(ctx.getContext('2d'), {
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
            const ctx = document.getElementById('expenseBreakdownChart');
            if (!ctx) {
                console.error('Expense breakdown chart canvas not found');
                return;
            }
            
            if (expenseBreakdownChart) {
                expenseBreakdownChart.destroy();
            }
            
            // Generate colors for each segment
            const colors = [
                '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16',
                '#22c55e', '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9'
            ];
            
            expenseBreakdownChart = new Chart(ctx.getContext('2d'), {
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
            const ctx = document.getElementById('predictionChart').getContext('2d');
            
            if (predictionChart) {
                predictionChart.destroy();
            }
            
            if (!data.data || data.data.length === 0) {
                ctx.font = '20px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('No prediction data available', ctx.canvas.width / 2, ctx.canvas.height / 2);
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
                                    return formatISK(value, true).replace(' ISK', '');
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

// Analytics Tab Functions
function loadAnalyticsData() {
    calculateHealthScore();
    calculateBurnRate();
    calculateFinancialRatios();
}

function calculateHealthScore() {
    // This would need new API endpoints - for now showing placeholder
    const score = 75; // Placeholder
    document.getElementById('health-score').textContent = score + '/100';
    const bar = document.getElementById('health-bar');
    bar.style.width = score + '%';
    bar.className = 'progress-bar ' + (score > 70 ? 'bg-success' : score > 40 ? 'bg-warning' : 'bg-danger');
    document.getElementById('health-details').innerHTML = `
        <p>Balance Stability: <strong>Good</strong></p>
        <p>Income Consistency: <strong>Moderate</strong></p>
        <p>Expense Control: <strong>Excellent</strong></p>
    `;
}

function calculateBurnRate() {
    // Placeholder calculations
    document.getElementById('daily-burn').textContent = formatISK(5000000, true);
    document.getElementById('days-remaining').textContent = '45 days';
    document.getElementById('weekly-avg').textContent = formatISK(35000000, true);
    document.getElementById('monthly-avg').textContent = formatISK(150000000, true);
}

function calculateFinancialRatios() {
    // Placeholder ratios
    document.getElementById('liquidity-ratio').textContent = '2.5';
    document.getElementById('growth-rate').textContent = '+15%';
    document.getElementById('income-expense-ratio').textContent = '1.3';
    document.getElementById('volatility').textContent = '12%';
}

// Trends Tab Functions
function loadTrendsData() {
    // These would need new API endpoints
    console.log('Loading trends data...');
}

// Performance Tab Functions
function loadPerformanceData() {
    // These would need new API endpoints
    console.log('Loading performance data...');
}

// Cash Flow Tab Functions
function loadCashFlowData() {
    // These would need new API endpoints
    console.log('Loading cash flow data...');
}

// Reports Tab Functions
function loadReportsData() {
    generateExecutiveSummary();
}

function generateExecutiveSummary() {
    // Placeholder summary
    document.getElementById('key-insights').innerHTML = `
        <li>Monthly income increased by 23% compared to last month</li>
        <li>Expenses are within budget parameters</li>
        <li>Cash reserves adequate for 45 days of operations</li>
    `;
    
    document.getElementById('recommendations').innerHTML = `
        <li>Consider investing surplus funds in market opportunities</li>
        <li>Review Division 3 expenses - 40% above average</li>
        <li>Optimize tax payments schedule for better cash flow</li>
    `;
    
    document.getElementById('risk-assessment').innerHTML = `
        <div class="alert alert-warning">
            <strong>Medium Risk:</strong> Dependency on single income source (Player Trading) at 65% of total income
        </div>
    `;
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

// Tab change handler
document.addEventListener('DOMContentLoaded', function() {
    // Load corporation settings first
    loadCorporationSettings();
    
    // Handle tab changes
    document.querySelectorAll('.nav-link[data-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            const target = e.target.getAttribute('href');
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
        });
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
