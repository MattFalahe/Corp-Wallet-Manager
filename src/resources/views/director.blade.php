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

        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Live Wallet Balance</h3>
                <div class="card-tools">
                    <span id="connection-status" class="badge badge-secondary">Connecting...</span>
                </div>
            </div>
            <div class="card-body">
                <canvas id="walletChart" height="100"></canvas>
                <div id="chart-error" class="alert alert-warning" style="display: none;">
                    <i class="fa fa-exclamation-triangle"></i> Unable to load chart data. Please check your connection.
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Last 6 Months Comparison</h3>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" height="100"></canvas>
                <div id="monthly-chart-error" class="alert alert-warning" style="display: none;">
                    <i class="fa fa-exclamation-triangle"></i> Unable to load monthly data. Please check your connection.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@2.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-streaming@2.0.0/dist/chartjs-plugin-streaming.min.js"></script>
@endpush

@section('scripts')
<script>
// Configuration from Laravel with fallbacks
const config = {
    refreshInterval: {{ config('corpwalletmanager.refresh_interval', 60000) }},
    decimals: {{ config('corpwalletmanager.decimals', 2) }},
    colorActual: "{{ config('corpwalletmanager.color_actual', '#4cafef') }}",
    colorPredicted: "{{ config('corpwalletmanager.color_predicted', '#ef4444') }}"
};

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
        return value + ' ISK';
    }
}

// Connection status tracking
let connectionRetries = 0;
const maxRetries = 5;

function updateConnectionStatus(status) {
    const statusEl = document.getElementById('connection-status');
    switch (status) {
        case 'connected':
            statusEl.className = 'badge badge-success';
            statusEl.textContent = 'Connected';
            connectionRetries = 0;
            break;
        case 'connecting':
            statusEl.className = 'badge badge-warning';
            statusEl.textContent = 'Connecting...';
            break;
        case 'error':
            statusEl.className = 'badge badge-danger';
            statusEl.textContent = 'Connection Error';
            break;
    }
}

// Progressive Live Chart with error handling
const ctxWallet = document.getElementById('walletChart');
let walletChart;

try {
    walletChart = new Chart(ctxWallet, {
        type: 'line',
        data: { 
            datasets: [
                { 
                    label: 'Actual Balance', 
                    borderColor: config.colorActual, 
                    backgroundColor: 'rgba(0,0,0,0)', 
                    data: [],
                    tension: 0.1
                },
                { 
                    label: 'Predicted Balance', 
                    borderColor: config.colorPredicted, 
                    borderDash: [5,5], 
                    backgroundColor: 'rgba(0,0,0,0)', 
                    data: [],
                    tension: 0.1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { 
                streaming: {
                    duration: 24*60*60*1000, // 24 hours
                    refresh: config.refreshInterval,
                    delay: 2000,
                    onRefresh: function(chart) {
                        updateConnectionStatus('connecting');
                        
                        fetch('{{ route("corpwalletmanager.latest") }}', {
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
                            
                            const now = Date.now();
                            const balance = parseFloat(data.balance) || 0;
                            const predicted = parseFloat(data.predicted) || 0;
                            
                            chart.data.datasets[0].data.push({
                                x: now, 
                                y: parseFloat(balance.toFixed(config.decimals))
                            });
                            chart.data.datasets[1].data.push({
                                x: now, 
                                y: parseFloat(predicted.toFixed(config.decimals))
                            });
                            
                            updateConnectionStatus('connected');
                            document.getElementById('chart-error').style.display = 'none';
                        })
                        .catch(error => {
                            console.error('Chart data fetch error:', error);
                            updateConnectionStatus('error');
                            connectionRetries++;
                            
                            if (connectionRetries >= maxRetries) {
                                document.getElementById('chart-error').style.display = 'block';
                            }
                        });
                    }
                },
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                x: { 
                    type: 'realtime', 
                    realtime: {
                        duration: 24*60*60*1000, 
                        refresh: config.refreshInterval, 
                        delay: 2000
                    }, 
                    title: {
                        display: true,
                        text: 'Time'
                    } 
                },
                y: { 
                    title: {
                        display: true,
                        text: 'Balance (ISK)'
                    },
                    ticks: {
                        callback: function(value) {
                            try {
                                return new Intl.NumberFormat().format(value);
                            } catch (e) {
                                return value;
                            }
                        }
                    }
                }
            }
        }
    });
} catch (error) {
    console.error('Failed to initialize wallet chart:', error);
    document.getElementById('chart-error').style.display = 'block';
}

// 6-Month Comparison Chart with error handling
const ctxMonthly = document.getElementById('monthlyChart');
let monthlyChart;

fetch('{{ route("corpwalletmanager.monthly") }}', {
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
    
    try {
        monthlyChart = new Chart(ctxMonthly, {
            type: 'bar',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Net Balance',
                    data: (data.data || []).map(v => parseFloat(v) || 0),
                    backgroundColor: (data.data || []).map(v => 
                        (parseFloat(v) || 0) >= 0 ? config.colorActual : config.colorPredicted
                    )
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Balance (ISK)'
                        },
                        ticks: {
                            callback: function(value) {
                                try {
                                    return new Intl.NumberFormat().format(value);
                                } catch (e) {
                                    return value;
                                }
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + formatISK(context.parsed.y);
                            }
                        }
                    },
                    legend: {
                        display: false
                    }
                }
            }
        });
    } catch (error) {
        console.error('Failed to create monthly chart:', error);
        document.getElementById('monthly-chart-error').style.display = 'block';
    }
})
.catch(error => {
    console.error('Monthly data fetch error:', error);
    document.getElementById('monthly-chart-error').style.display = 'block';
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (walletChart) {
        walletChart.destroy();
    }
    if (monthlyChart) {
        monthlyChart.destroy();
    }
});
</script>
@endsection
