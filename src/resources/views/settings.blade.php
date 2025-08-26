@extends('web::layouts.app')

@section('title', 'CorpWallet Manager - Settings')

@section('content')
<div class="row">
    <div class="col-12">
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">CorpWallet Manager Settings</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('corpwalletmanager.settings.update') }}" method="POST">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Display Settings</h5>
                            
                            <div class="form-group">
                                <label for="refresh_interval">Chart Refresh Interval (ms)</label>
                                <input type="number" class="form-control" id="refresh_interval" 
                                       name="refresh_interval" value="{{ $settings['refresh_interval'] }}" 
                                       min="5000" max="300000" step="1000">
                                <small class="text-muted">How often charts update (5000-300000ms)</small>
                            </div>

                            <div class="form-group">
                                <label for="decimals">Decimal Places</label>
                                <input type="number" class="form-control" id="decimals" 
                                       name="decimals" value="{{ $settings['decimals'] }}" 
                                       min="0" max="8">
                                <small class="text-muted">Number of decimal places for ISK values</small>
                            </div>

                            <div class="row">
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="color_actual">Actual Balance Color</label>
                                        <input type="color" class="form-control" id="color_actual" 
                                               name="color_actual" value="{{ $settings['color_actual'] }}">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <label for="color_predicted">Predicted Balance Color</label>
                                        <input type="color" class="form-control" id="color_predicted" 
                                               name="color_predicted" value="{{ $settings['color_predicted'] }}">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h5>Performance Settings</h5>
                            
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="hidden" name="use_precomputed_predictions" value="0">
                                    <input type="checkbox" class="form-check-input" id="use_precomputed_predictions" 
                                           name="use_precomputed_predictions" value="1" 
                                           {{ $settings['use_precomputed_predictions'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="use_precomputed_predictions">
                                        Use Precomputed Predictions
                                    </label>
                                    <small class="form-text text-muted d-block">
                                        Use cached predictions instead of calculating on-the-fly
                                    </small>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="form-check">
                                    <input type="hidden" name="use_precomputed_monthly_balances" value="0">
                                    <input type="checkbox" class="form-check-input" id="use_precomputed_monthly_balances" 
                                           name="use_precomputed_monthly_balances" value="1" 
                                           {{ $settings['use_precomputed_monthly_balances'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="use_precomputed_monthly_balances">
                                        Use Precomputed Monthly Balances
                                    </label>
                                    <small class="form-text text-muted d-block">
                                        Use cached monthly balances instead of calculating on-the-fly
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Save Settings
                        </button>
                        
                        <button type="button" class="btn btn-warning" onclick="resetSettings()">
                            <i class="fa fa-refresh"></i> Reset to Defaults
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Maintenance</h3>
            </div>
            <div class="card-body">
                <p>Use these tools to manually trigger data processing jobs.</p>
                
                <div class="row">
                    <div class="col-md-6">
                        <h5>Basic Jobs</h5>
                        <form action="{{ route('corpwalletmanager.settings.backfill') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-info mb-2">
                                <i class="fa fa-database"></i> Wallet Backfill
                            </button>
                        </form>
                        
                        <form action="{{ route('corpwalletmanager.settings.prediction') }}" method="POST" class="d-inline ml-2">
                            @csrf
                            <button type="submit" class="btn btn-success mb-2">
                                <i class="fa fa-calculator"></i> Compute Predictions
                            </button>
                        </form>
                    </div>
                    
                    <div class="col-md-6">
                        <h5>Division Jobs</h5>
                        <form action="{{ route('corpwalletmanager.settings.division-backfill') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-info mb-2">
                                <i class="fa fa-th"></i> Division Backfill
                            </button>
                        </form>
                        
                        <form action="{{ route('corpwalletmanager.settings.division-prediction') }}" method="POST" class="d-inline ml-2">
                            @csrf
                            <button type="submit" class="btn btn-success mb-2">
                                <i class="fa fa-chart-bar"></i> Division Predictions
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Job Status</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Job Type</th>
                                <th>Status</th>
                                <th>Started</th>
                                <th>Duration</th>
                                <th>Records</th>
                                <th>Corporation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentLogs as $log)
                                <tr>
                                    <td>{{ $log->job_type_display }}</td>
                                    <td>
                                        <span class="badge {{ $log->status_badge_class }}">
                                            {{ ucfirst($log->status) }}
                                        </span>
                                    </td>
                                    <td>{{ $log->started_at->format('Y-m-d H:i:s') }}</td>
                                    <td>{{ $log->formatted_duration }}</td>
                                    <td>{{ number_format($log->records_processed) }}</td>
                                    <td>
                                        @if($log->corporation)
                                            {{ $log->corporation->name ?? 'N/A' }}
                                        @else
                                            All
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No jobs found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function resetSettings() {
    if (confirm('Are you sure you want to reset all settings to defaults? This cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '{{ route("corpwalletmanager.settings.reset") }}';
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        
        form.appendChild(csrfToken);
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-refresh job status every 30 seconds
setInterval(function() {
    fetch('{{ route("corpwalletmanager.settings.job-status") }}')
        .then(response => response.json())
        .then(data => {
            // Update running jobs count if you want to show it somewhere
            console.log('Running jobs:', data.running_jobs);
        })
        .catch(error => {
            console.error('Error fetching job status:', error);
        });
}, 30000);
</script>
@endsection
