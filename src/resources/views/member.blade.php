@extends('web::layouts.app')

@section('title', 'CorpWallet Manager - Member View')

@section('content')
<div class="row">
    <div class="col-12">
        @if(session('error'))
            <div class="alert alert-danger">
                <i class="fa fa-times"></i> {{ session('error') }}
            </div>
        @endif

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Corporation Wallet Overview</h3>
                <div class="card-tools">
                    <span id="last-updated" class="badge badge-secondary">Never updated</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-blue">
                                <i class="fa fa-wallet"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">Current Balance</span>
                                <span class="info-box-number" id="current-balance">Loading...</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-green">
                                <i class="fa fa-chart-line"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">Predicted Balance</span>
                                <span class="info-box-number" id="predicted-balance">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="info-box info-box-sm">
                            <span class="info-box-icon bg-yellow">
                                <i class="fa fa-calendar"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">This Month</span>
                                <span class="info-box-number" id="current-month">--</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box info-box-sm">
                            <span class="info-box-icon bg-purple">
                                <i class="fa fa-arrow-up"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">Monthly Change</span>
                                <span class="info-box-number" id="monthly-change">--</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box info-box-sm">
                            <span class="info-box-icon bg-orange">
                                <i class="fa fa-percentage"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">Change %</span>
                                <span class="info-box-number" id="change-percent">--</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="balance-error" class="alert alert-warning mt-3" style="display: none;">
                    <i class="fa fa-exclamation-triangle"></i> Unable to load balance data. Please refresh the page or try again later.
                    <button type="button" class="btn btn-sm btn-warning ml-2" onclick="updateBalanceInfo()">
                        <i class="fa fa-refresh"></i> Retry
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Monthly Balance Trend</h3>
                <div class="card-tools">
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary" onclick="updateChartMonths(3)">3M</button>
                        <button type="button" class="btn btn-secondary active" onclick="updateChartMonths(6)">6M</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="updateChartMonths(12)">12M</button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div id="chart-loading" class="text-center" style="display: none;">
                    <i class="fa fa-spinner fa-spin fa-2x"></i>
                    <p class="mt-2">Loading chart data...</p>
                </div>
                <canvas id="monthlyChart" height="100"></canvas>
                <div id="monthly-error" class="alert alert-warning" style="display: none;">
                    <i class="fa fa-exclamation-triangle"></i> Unable to load trend data. Please refresh the page or try again later.
                    <button type="button" class="btn btn-sm btn-warning ml-2" onclick="loadMonthlyChart()">
                        <i class="fa fa-refresh"></i> Retry
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
@endpush

@section('scripts')
<script>
// Configuration with fallbacks
const config = {
    refreshInterval: {{ config('corpwalletmanager.refresh_interval', 60000) }},
    decimals: {{ config('corpwalletmanager.decimals', 2) }},
    colorActual: "{{ config('corpwalletmanager.color_actual', '#4cafef') }}",
    colorPredicted: "{{ config('corpwalletmanager.color_predicted', '#ef4444') }}"
};

// Global variables
let monthlyChart = null;
let balanceInterval = null;
let currentMonths = 6;
let retryCount = 0;
const maxRetries = 3;

// Helper function to format ISK values safely
function formatISK(value) {
    try {
        if (!isFinite(value) || isNaN(value)) {
            return '0.00 ISK';
        }
        return new Intl.NumberFormat('en-US', {
            style: 'decimal',
            minimumFractionDigits: config.decimals,
            maximumFractionDigits: config.decimals
        }).format(value) + ' ISK';
    } catch (e) {
        console.warn('ISK formatting error:', e);
        return value + ' ISK';
    }
}

// Helper function to format percentage
function formatPercent(value) {
    try {
        if (!isFinite(value) || isNaN(value)) {
            return '0.00%';
        }
        return parseFloat(value).toFixed(2) + '%';
    } catch (e) {
        return '0.00%';
    }
}

// Update last updated timestamp
function updateTimestamp() {
    const now = new Date();
    const timeString = now.toLocaleTimeString();
    document.getElementById('last-updated').textContent = `Updated: ${timeString}`;
    document.getElementById('last-updated').className = 'badge badge-success';
}

// Update balance info boxes with comprehensive error handling
function updateBalanceInfo() {
    const balanceError = document.getElementById('balance-error');
    const currentBalanceEl = document.getElementById('current-balance');
    const predictedBalanceEl = document.getElementById('predicted-balance');
    
    // Show loading state
    currentBalanceEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    predictedBalanceEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    
    // Fetch latest balance data
    fetch('{{ route("corpwalletmanager.latest") }}', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'Cache-Control': 'no-cache'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.error) {
            throw new Error(data.error);
        }
        
        const balance = parseFloat(data.balance) || 0;
        const predicted = parseFloat(data.predicted) || 0;
        
        currentBalanceEl.textContent = formatISK(balance);
        predictedBalanceEl.textContent = formatISK(predicted);
        
        updateTimestamp();
        balanceError.style.display = 'none';
        retryCount = 0; // Reset retry count on success
        
        // Also fetch summary data for additional metrics
        fetchSummaryData();
    })
    .catch(error => {
        console.error('Balance fetch error:', error);
        retryCount++;
        
        currentBalanceEl.textContent = 'Error loading';
        predictedBalanceEl.textContent = 'Error loading';
        
        document.getElementById('last-updated').textContent = 'Update failed';
        document.getElementById('last-updated').className = 'badge badge-danger';
        
        if (retryCount >= maxRetries) {
            balanceError.style.display = 'block';
        }
    });
}

// Fetch summary data for additional metrics
function fetchSummaryData() {
    fetch('{{ route("corpwalletmanager.summary") }}', {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.ok ? response.json() : Promise.reject(new Error('Summary fetch failed')))
    .then(data => {
        if (!data.error) {
            // Update current month
            document.getElementById('current-month').textContent = data.current_month?.month || '--';
            
            // Update monthly change
            const change = parseFloat(data.change?.absolute) || 0;
            const changeEl = document.getElementById('monthly-change');
            changeEl.textContent = formatISK(Math.abs(change));
            
            // Update change percentage
            const changePercent = parseFloat(data.change?.percent) || 0;
            const percentEl = document.getElementById('change-percent');
            percentEl.textContent = formatPercent(changePercent);
            
            // Update colors based on positive/negative change
            const changeIcon = changeEl.parentElement.querySelector('.info-box-icon');
            const percentIcon = percentEl.parentElement.querySelector('.info-box-icon');
            
            if (change >= 0) {
                changeIcon.className = 'info-box-icon bg-success';
                changeIcon.innerHTML = '<i class="fa fa-arrow-up"></i>';
                percentIcon.className = 'info-box-icon bg-success';
            } else {
                changeIcon.className = 'info-box-icon bg-danger';
                changeIcon.innerHTML = '<i class="fa fa-arrow-down"></i>';
                percentIcon.className = 'info-box-icon bg-danger';
            }
        }
    })
    .catch(error => {
        console.warn('Summary data fetch failed:', error);
        // Don't show error for summary data, just log it
    });
}

// Load monthly chart with error handling
function loadMonthlyChart(months = 6) {
    const chartContainer = document.getElementById('monthlyChart').parentElement;
    const loadingEl = document.getElementById('chart-loading');
    const errorEl = document.getElementById('monthly-error');
    
    loadingEl.style.display = 'block';
    errorEl.style.display = 'none';
    
    if (monthlyChart) {
        monthlyChart.destroy();
        monthlyChart = null;
    }
    
    fetch(`{{ route("corpwalletmanager.monthly") }}?months=${months}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.error) {
            throw new Error(data.error);
        }
        
        loadingEl.style.display = 'none';
        
        try {
            const ctx = document.getElementById('monthlyChart').getContext('2d');
            monthlyChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Monthly Balance',
                        data: (data.data || []).map(v => parseFloat(v) || 0),
                        borderColor: config.colorActual,
                        backgroundColor: config.colorActual + '20',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: config.colorActual,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    scales: {
                        y: {
                            title: {
                                display: true,
                                text: 'Balance (ISK)',
                                font: {
                                    weight: 'bold'
                                }
                            },
                            ticks: {
                                callback: function(value) {
                                    try {
                                        return new Intl.NumberFormat().format(value);
                                    } catch (e) {
                                        return value;
                                    }
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month',
                                font: {
                                    weight: 'bold'
                                }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Balance: ' + formatISK(context.parsed.y);
                                }
                            },
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: config.colorActual,
                            borderWidth: 1
                        },
                        legend: {
                            display: false
                        }
                    },
                    elements: {
                        line: {
                            borderWidth: 3
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Failed to create monthly chart:', error);
            errorEl.style.display = 'block';
            loadingEl.style.display = 'none';
        }
    })
    .catch(error => {
        console.error('Monthly data fetch error:', error);
        loadingEl.style.display = 'none';
        errorEl.style.display = 'block';
    });
}

// Update chart time range
function updateChartMonths(months) {
    currentMonths = months;
    
    // Update button states
    document.querySelectorAll('.btn-group .btn').forEach(btn => {
        btn.className = 'btn btn-outline-secondary';
    });
    event.target.className = 'btn btn-secondary active';
    
    // Reload chart with new timeframe
    loadMonthlyChart(months);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initial data load
    updateBalanceInfo();
    loadMonthlyChart(currentMonths);
    
    // Set up periodic refresh
    balanceInterval = setInterval(updateBalanceInfo, config.refreshInterval);
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (balanceInterval) {
        clearInterval(balanceInterval);
    }
    if (monthlyChart) {
        monthlyChart.destroy();
    }
});

// Handle visibility change (pause updates when tab not active)
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        if (balanceInterval) {
            clearInterval(balanceInterval);
            balanceInterval = null;
        }
    } else {
        if (!balanceInterval) {
            updateBalanceInfo(); // Immediate update when tab becomes visible
            balanceInterval = setInterval(updateBalanceInfo, config.refreshInterval);
        }
    }
});

// Manual refresh function (can be called from error retry buttons)
window.updateBalanceInfo = updateBalanceInfo;
window.loadMonthlyChart = loadMonthlyChart;
</script>
@endsection
