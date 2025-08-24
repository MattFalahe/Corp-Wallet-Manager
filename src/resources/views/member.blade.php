@extends('web::layouts.app')

@section('title', 'CorpWallet Manager - Member View')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Corporation Wallet Overview</h3>
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
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Monthly Balance Trend</h3>
            </div>
            <div class="card-body">
                <canvas id="monthlyChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>
@endsection

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endpush

@section('scripts')
<script>
// Configuration
const refreshInterval = {{ config('corpwalletmanager.refresh_interval', 60000) }};
const decimals = {{ config('corpwalletmanager.decimals', 2) }};
const colorActual = "{{ config('corpwalletmanager.color_actual', '#4cafef') }}";
const colorPredicted = "{{ config('corpwalletmanager.color_predicted', '#ef4444') }}";

// Helper function to format ISK values
function formatISK(value) {
    return new Intl.NumberFormat('en-US', {
        style: 'decimal',
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(value) + ' ISK';
}

// Update balance info boxes
function updateBalanceInfo() {
    fetch('{{ route("corpwalletmanager.latest") }}')
        .then(res => res.json())
        .then(data => {
            document.getElementById('current-balance').textContent = formatISK(data.balance);
            document.getElementById('predicted-balance').textContent = formatISK(data.predicted);
        })
        .catch(error => {
            console.error('Error fetching balance data:', error);
            document.getElementById('current-balance').textContent = 'Error loading';
            document.getElementById('predicted-balance').textContent = 'Error loading';
        });
}

// Initial load
updateBalanceInfo();

// Refresh balance info periodically
setInterval(updateBalanceInfo, refreshInterval);

// Monthly comparison chart
const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
fetch('{{ route("corpwalletmanager.monthly") }}')
    .then(res => res.json())
    .then(data => {
        new Chart(ctxMonthly, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Monthly Balance',
                    data: data.data,
                    borderColor: colorActual,
                    backgroundColor: colorActual + '20',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Balance (ISK)'
                        },
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat().format(value);
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
                                return 'Balance: ' + formatISK(context.parsed.y);
                            }
                        }
                    },
                    legend: {
                        display: false
                    }
                }
            }
        });
    })
    .catch(error => {
        console.error('Error fetching monthly data:', error);
    });
</script>
@endsection
