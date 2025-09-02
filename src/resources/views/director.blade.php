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

        <div class="row mb-3">
            <div class="col-md-4">
                <div class="info-box">
                    <span class="info-box-icon bg-blue"><i class="fas fa-wallet"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Current Balance</span>
                        <span class="info-box-number" id="current-balance">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box">
                    <span class="info-box-icon bg-green"><i class="fas fa-chart-line"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Predicted Balance</span>
                        <span class="info-box-number" id="predicted-balance">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="info-box">
                    <span class="info-box-icon bg-yellow"><i class="fas fa-percent"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Monthly Change</span>
                        <span class="info-box-number" id="monthly-change">Loading...</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Monthly Balance Trend</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" onclick="refreshData()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" height="100"></canvas>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">30-Day Prediction</h3>
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
    colorPredicted: "{{ config('corpwalletmanager.color_predicted', '#ef4444') }}"
};

// Global chart variables
let monthlyChart = null;
let predictionChart = null;

// Helper function to format ISK values
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

// Load current balance data
function loadBalanceData() {
    fetch('{{ route("corpwalletmanager.latest") }}')
        .then(response => response.json())
        .then(data => {
            document.getElementById('current-balance').textContent = formatISK(data.balance);
            document.getElementById('predicted-balance').textContent = formatISK(data.predicted);
        })
        .catch(error => {
            console.error('Error loading balance:', error);
            document.getElementById('current-balance').textContent = 'Error';
            document.getElementById('predicted-balance').textContent = 'Error';
        });

    // Load summary data for monthly change
    fetch('{{ route("corpwalletmanager.summary") }}')
        .then(response => response.json())
        .then(data => {
            const changePercent = data.change.percent;
            const changeEl = document.getElementById('monthly-change');
            changeEl.textContent = changePercent.toFixed(2) + '%';
            
            // Color based on positive/negative
            if (changePercent >= 0) {
                changeEl.classList.remove('text-danger');
                changeEl.classList.add('text-success');
            } else {
                changeEl.classList.remove('text-success');
                changeEl.classList.add('text-danger');
            }
        })
        .catch(error => {
            console.error('Error loading summary:', error);
            document.getElementById('monthly-change').textContent = 'Error';
        });
}

// Load monthly comparison chart
function loadMonthlyChart() {
    fetch('{{ route("corpwalletmanager.monthly") }}?months=6')
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('monthlyChart').getContext('2d');
            
            if (monthlyChart) {
                monthlyChart.destroy();
            }
            
            monthlyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Monthly Balance',
                        data: data.data,
                        backgroundColor: data.data.map(value => 
                            value >= 0 ? config.colorActual : config.colorPredicted
                        ),
                        borderColor: data.data.map(value => 
                            value >= 0 ? config.colorActual : config.colorPredicted
                        ),
                        borderWidth: 1
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
                            beginAtZero: true,
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
            console.error('Error loading monthly chart:', error);
        });
}

// Load prediction chart
function loadPredictionChart() {
    fetch('{{ route("corpwalletmanager.predictions") }}?days=30')
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('predictionChart').getContext('2d');
            
            if (predictionChart) {
                predictionChart.destroy();
            }
            
            // If no prediction data, show a message
            if (!data.data || data.data.length === 0) {
                ctx.font = '20px Arial';
                ctx.textAlign = 'center';
                ctx.fillText('No prediction data available', ctx.canvas.width / 2, ctx.canvas.height / 2);
                return;
            }
            
            predictionChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Predicted Balance',
                        data: data.data,
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
                        legend: {
                            display: false
                        },
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
            console.error('Error loading prediction chart:', error);
        });
}

// Refresh all data
function refreshData() {
    loadBalanceData();
    loadMonthlyChart();
    loadPredictionChart();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    refreshData();
    
    // Auto-refresh every 60 seconds
    setInterval(refreshData, 60000);
});
</script>
@endpush
