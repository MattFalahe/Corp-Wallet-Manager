@extends('web::layouts.app')

@section('title', 'CorpWallet Manager - Member View')

@section('content')
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

        <!-- Activity and Milestones Row -->
        <div class="row">
            <!-- Activity Heatmap -->
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

            <!-- Recent Milestones -->
            <div class="col-md-6" id="milestones-section">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-medal"></i> Recent Achievements
                        </h3>
                    </div>
                    <div class="card-body">
                        <div id="milestones-list">
                            <div class="milestone-item">
                                <i class="fas fa-check-circle text-success"></i>
                                <span>Loading achievements...</span>
                            </div>
                        </div>

                        <hr>

                        <h6 class="text-muted">Upcoming Events</h6>
                        <div id="upcoming-events">
                            <div class="event-item">
                                <i class="far fa-calendar"></i>
                                <span>Loading events...</span>
                            </div>
                        </div>
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
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" onclick="refreshData()">
                                <i class="fas fa-sync-alt"></i> Refresh All
                            </button>
                        </div>
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
    </div>
</div>
@stop

@push('javascript')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Fix SeAT's mixed content issue
(function() {
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
};

// Chart instances
let balanceTrendChart = null;
let performanceRadarChart = null;
let activityPatternChart = null;
let currentMonths = 6;

// Helper function to build URLs
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
            
            const displayText = config.corporationId 
                ? `Viewing data for Corporation ID: ${config.corporationId}` 
                : 'Viewing data for all corporations';
                
            document.getElementById('current-corp-display').textContent = displayText;
            
            setupAutoRefresh(data.refresh_minutes);
            refreshData();
        })
        .catch(error => {
            console.error('Error loading corporation settings:', error);
            document.getElementById('current-corp-display').textContent = 'Error loading corporation settings';
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
        document.getElementById('milestones-section').style.display = 'none';
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
    
    if (!config.showBalance) {
        const balanceElements = document.querySelectorAll('[id*="balance"], [id*="Balance"]');
        balanceElements.forEach(el => {
            if (el.textContent.includes('ISK')) {
                el.textContent = 'Protected';
            }
        });
    }
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

// Refresh all data
function refreshData() {
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
    
    // Log this access
    logAccess();
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Apply visibility settings first
    applySectionVisibility();
    
    // Load corporation settings, which will trigger data loading
    loadCorporationSettings();
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (config.refreshTimer) {
        clearInterval(config.refreshTimer);
    }
});
</script>
@endpush
