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

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Corporation Wallet Overview</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-info">
                                <i class="fas fa-wallet"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">Current Balance</span>
                                <span class="info-box-number" id="current-balance">Loading...</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-box">
                            <span class="info-box-icon bg-success">
                                <i class="fas fa-chart-line"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">Monthly Change</span>
                                <span class="info-box-number" id="monthly-change">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Monthly Balance Trend</h3>
                <div class="card-tools">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary" onclick="updateChartMonths(3)">3M</button>
                        <button type="button" class="btn btn-secondary active" onclick="updateChartMonths(6)">6M</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="updateChartMonths(12)">12M</button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" height="100"></canvas>
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
    corporationId: null,
    refreshInterval: null,
    refreshTimer: null
};

let monthlyChart = null;
let currentMonths = 6;

// Helper function to build URLs that respect the current protocol
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

// Add corporation parameter to API calls
function addCorpParam(url) {
    if (config.corporationId) {
        const separator = url.includes('?') ? '&' : '?';
        return url + separator + 'corporation_id=' + config.corporationId;
    }
    return url;
}

// Format ISK values
function formatISK(value) {
    if (!isFinite(value) || isNaN(value)) {
        return '0.00 ISK';
    }
    return new Intl.NumberFormat('en-US', {
        style: 'decimal',
        minimumFractionDigits: config.decimals,
        maximumFractionDigits: config.decimals
    }).format(value) + ' ISK';
}

// Load balance info
function loadBalanceInfo() {
    // Load current balance
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/latest')))
        .then(response => response.json())
        .then(data => {
            document.getElementById('current-balance').textContent = formatISK(data.balance);
        })
        .catch(error => {
            console.error('Balance fetch error:', error);
            document.getElementById('current-balance').textContent = 'Error';
        });

    // Load summary for change
    fetch(buildUrl(addCorpParam('/corp-wallet-manager/api/summary')))
        .then(response => response.json())
        .then(data => {
            const change = data.change.absolute;
            const changeEl = document.getElementById('monthly-change');
            
            if (change >= 0) {
                changeEl.innerHTML = '<i class="fas fa-arrow-up"></i> ' + formatISK(Math.abs(change));
                changeEl.parentElement.previousElementSibling.className = 'info-box-icon bg-success';
            } else {
                changeEl.innerHTML = '<i class="fas fa-arrow-down"></i> ' + formatISK(Math.abs(change));
                changeEl.parentElement.previousElementSibling.className = 'info-box-icon bg-danger';
            }
        })
        .catch(error => {
            console.error('Summary fetch error:', error);
            document.getElementById('monthly-change').textContent = 'Error';
        });
}

// Load monthly chart
function loadMonthlyChart(months = 6) {
    const url = buildUrl(addCorpParam(`/corp-wallet-manager/api/monthly-comparison?months=${months}`));
    fetch(url)
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('monthlyChart').getContext('2d');
            
            if (monthlyChart) {
                monthlyChart.destroy();
            }
            
            monthlyChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels || [],
                    datasets: [{
                        label: 'Monthly Balance',
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
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Balance: ' + formatISK(context.parsed.y);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('en-US', {
                                        notation: 'compact',
                                        maximumFractionDigits: 1
                                    }).format(value);
                                }
                            }
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Monthly chart error:', error);
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
    
    loadMonthlyChart(months);
}

// Refresh all data
function refreshData() {
    loadBalanceInfo();
    loadMonthlyChart(currentMonths);
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // Load corporation settings first, which will then trigger data loading
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
