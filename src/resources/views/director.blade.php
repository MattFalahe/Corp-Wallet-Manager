@extends('web::layouts.app')

@section('title', 'CorpWallet Manager - Director')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">Live Wallet Balance</h3>
            </div>
            <div class="card-body">
                <canvas id="walletChart" height="100"></canvas>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Last 6 Months Comparison</h3>
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
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-streaming@2.0.0"></script>
@endpush

@section('scripts')
<script>
// Configuration from Laravel
const refreshInterval = {{ config('corpwalletmanager.refresh_interval', 60000) }};
const decimals = {{ config('corpwalletmanager.decimals', 2) }};
const colorActual = "{{ config('corpwalletmanager.color_actual', '#4cafef') }}";
const colorPredicted = "{{ config('corpwalletmanager.color_predicted', '#ef4444') }}";

// Progressive Live Chart
const ctxWallet = document.getElementById('walletChart').getContext('2d');
const walletChart = new Chart(ctxWallet, {
    type: 'line',
    data: { 
        datasets: [
            { 
                label: 'Actual Balance', 
                borderColor: colorActual, 
                backgroundColor: 'rgba(0,0,0,0)', 
                data: [] 
            },
            { 
                label: 'Predicted Balance', 
                borderColor: colorPredicted, 
                borderDash: [5,5], 
                backgroundColor: 'rgba(0,0,0,0)', 
                data: [] 
            }
        ]
    },
    options: {
        responsive: true,
        plugins: { 
            streaming: {
                duration: 24*60*60*1000, // 24 hours
                refresh: refreshInterval,
                onRefresh: function(chart) {
                    fetch('{{ route("corpwalletmanager.latest") }}')
                        .then(res => res.json())
                        .then(data => {
                            const now = Date.now();
                            chart.data.datasets[0].data.push({
                                x: now, 
                                y: parseFloat(data.balance.toFixed(decimals))
                            });
                            chart.data.datasets[1].data.push({
                                x: now, 
                                y: parseFloat(data.predicted.toFixed(decimals))
                            });
                        })
                        .catch(error => {
                            console.error('Error fetching latest data:', error);
                        });
                }
            }
        },
        scales: {
            x: { 
                type: 'realtime', 
                realtime: {
                    duration: 24*60*60*1000, 
                    refresh: refreshInterval, 
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
                        return new Intl.NumberFormat().format(value);
                    }
                }
            }
        }
    }
});

// 6-Month Comparison Chart
const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
fetch('{{ route("corpwalletmanager.monthly") }}')
    .then(res => res.json())
    .then(data => {
        new Chart(ctxMonthly, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Net Balance',
                    data: data.data,
                    backgroundColor: data.data.map(v => v >= 0 ? colorActual : colorPredicted)
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
                                return context.dataset.label + ': ' + new Intl.NumberFormat().format(context.parsed.y) + ' ISK';
                            }
                        }
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
