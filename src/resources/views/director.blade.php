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

        <!-- Current Wallet Status Row -->
        <div class="row mb-3">
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

        <!-- Charts Row -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Balance History</h3>
                        <div class="card-tools">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" onclick="updateBalanceChart('actual')">Actual</button>
                                <button type="button" class="btn btn-secondary active" onclick="updateBalanceChart('flow')">Flow</button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="balanceChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Income vs Expenses</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="incomeExpenseChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transaction Type Breakdown Row -->
        <div class="row mt-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Income Sources (This Month)</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="incomeBreakdownChart" height="300"></canvas>
                        <div id="income-legend" class="mt-3"></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Expense Categories (This Month)</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                <i class="fas fa-minus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <canvas id="expenseBreakdownChart" height="300"></canvas>
                        <div id="expense-legend" class="mt-3"></div>
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
                <canvas id="predictionChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>
@stop

@push('javascript')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Configuration
const config = {
    decimals: {{ config('corpwalletmanager.decimals', 2) }},
    colorActual: "{{ config('corpwalletmanager.color_actual', '#4cafef') }}",
    colorPredicted: "{{ config('corpwalletmanager.color_predicted', '#ef4444') }}",
    colorIncome: '#10b981',
    colorExpense: '#ef4444'
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

// Load actual wallet balance from EVE (this would need a new API endpoint)
function loadActualBalance() {
    // For now, we'll calculate it from the data we have
    // In production, this should fetch from corporation wallet API
    fetch(buildUrl('/corp-wallet-manager/api/wallet-actual'))
        .then(response => response.json())
        .then(data => {
            document.getElementById('actual-balance').textContent = formatISK(data.balance, true);
        })
        .catch(error => {
            // Fallback to calculated value
            document.getElementById('actual-balance').textContent = 'N/A';
        });
}

// Load today's changes
function loadTodayChanges() {
    fetch(buildUrl('/corp-wallet-manager/api/today'))
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
    fetch(buildUrl('/corp-wallet-manager/api/latest'))
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
    fetch(buildUrl('/corp-wallet-manager/api/division-current'))
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
    const endpoint = mode === 'actual' ? '/corp-wallet-manager/api/balance-history' : '/corp-wallet-manager/api/monthly-comparison?months=6';
    
    fetch(buildUrl(endpoint))
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

// Load income vs expense chart
function loadIncomeExpenseChart() {
    fetch(buildUrl('/corp-wallet-manager/api/income-expense?months=6'))
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('incomeExpenseChart').getContext('2d');
            
            if (incomeExpenseChart) {
                incomeExpenseChart.destroy();
            }
            
            incomeExpenseChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels || [],
                    datasets: [
                        {
                            label: 'Income',
                            data: data.income || [],
                            backgroundColor: config.colorIncome,
                            borderColor: config.colorIncome,
                            borderWidth: 1
                        },
                        {
                            label: 'Expenses',
                            data: data.expenses || [],
                            backgroundColor: config.colorExpense,
                            borderColor: config.colorExpense,
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' },
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
            console.error('Error loading income/expense chart:', error);
        });
}

// Load prediction chart
function loadPredictionChart() {
    fetch(buildUrl('/corp-wallet-manager/api/predictions?days=30'))
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
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-outline-secondary');
    });
    event.target.classList.remove('btn-outline-secondary');
    event.target.classList.add('btn-secondary');
    loadBalanceChart(mode);
}

// Load income breakdown pie chart
function loadIncomeBreakdown() {
    fetch(buildUrl('/corp-wallet-manager/api/transaction-breakdown?type=income'))
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('incomeBreakdownChart').getContext('2d');
            
            if (incomeBreakdownChart) {
                incomeBreakdownChart.destroy();
            }
            
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
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        const dataset = data.datasets[0];
                                        const total = dataset.data.reduce((a, b) => a + b, 0);
                                        return data.labels.map((label, i) => {
                                            const value = dataset.data[i];
                                            const percentage = ((value / total) * 100).toFixed(1);
                                            return {
                                                text: `${label}: ${percentage}%`,
                                                fillStyle: dataset.backgroundColor[i],
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${formatISK(value, true)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // Create detailed legend
            if (data.details) {
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
    fetch(buildUrl('/corp-wallet-manager/api/transaction-breakdown?type=expense'))
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('expenseBreakdownChart').getContext('2d');
            
            if (expenseBreakdownChart) {
                expenseBreakdownChart.destroy();
            }
            
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
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                generateLabels: function(chart) {
                                    const data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        const dataset = data.datasets[0];
                                        const total = dataset.data.reduce((a, b) => a + b, 0);
                                        return data.labels.map((label, i) => {
                                            const value = dataset.data[i];
                                            const percentage = ((value / total) * 100).toFixed(1);
                                            return {
                                                text: `${label}: ${percentage}%`,
                                                fillStyle: dataset.backgroundColor[i],
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${formatISK(value, true)} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            
            // Create detailed legend
            if (data.details) {
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

// Refresh all data
function refreshData() {
    loadActualBalance();
    loadTodayChanges();
    loadCurrentData();
    loadDivisionBreakdown();
    loadBalanceChart(currentChartMode);
    loadIncomeExpenseChart();
    loadPredictionChart();
    loadIncomeBreakdown();
    loadExpenseBreakdown();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    refreshData();
    
    // Auto-refresh every 60 seconds
    setInterval(refreshData, 60000);
});
</script>
@endpush
