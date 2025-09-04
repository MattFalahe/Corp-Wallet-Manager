@extends('web::layouts.app')

@section('title', 'CorpWallet Manager - Settings')

@section('content')
<div class="row">
    <div class="col-12">
        @if(session('success'))
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> {{ session('success') }}
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> {{ session('warning') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">
                <i class="fas fa-times-circle"></i> {{ session('error') }}
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
                                <label for="selected_corporation_id">Corporation</label>
                                <select class="form-control" id="selected_corporation_id" name="selected_corporation_id">
                                    <option value="">All Corporations</option>
                                    @foreach($corporations as $corp)
                                        <option value="{{ $corp->corporation_id }}" 
                                                {{ $settings['selected_corporation_id'] == $corp->corporation_id ? 'selected' : '' }}>
                                            {{ $corp->name }} ({{ $corp->corporation_id }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Select which corporation to display data for</small>
                            </div>

                            <div class="form-group">
                                <label for="refresh_minutes">Chart Refresh Interval</label>
                                <select class="form-control" id="refresh_minutes" name="refresh_minutes">
                                    <option value="0" {{ $settings['refresh_minutes'] == '0' ? 'selected' : '' }}>No Auto Refresh</option>
                                    <option value="5" {{ $settings['refresh_minutes'] == '5' ? 'selected' : '' }}>5 Minutes</option>
                                    <option value="15" {{ $settings['refresh_minutes'] == '15' ? 'selected' : '' }}>15 Minutes</option>
                                    <option value="30" {{ $settings['refresh_minutes'] == '30' ? 'selected' : '' }}>30 Minutes</option>
                                    <option value="60" {{ $settings['refresh_minutes'] == '60' ? 'selected' : '' }}>60 Minutes</option>
                                </select>
                                <small class="text-muted">How often charts update automatically</small>
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

                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> <strong>Corporation Selection:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>Select a specific corporation to view only their data</li>
                                    <li>Choose "All Corporations" to see aggregate data</li>
                                    <li>This affects all views and maintenance jobs</li>
                                </ul>
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
                <div class="card-tools">
                    <span class="badge badge-info" id="selected-corp-badge">
                        @if($settings['selected_corporation_id'])
                            Corp ID: {{ $settings['selected_corporation_id'] }}
                        @else
                            All Corporations
                        @endif
                    </span>
                </div>
            </div>
            <div class="card-body">
                <p>Use these tools to manually trigger data processing jobs. 
                   @if($settings['selected_corporation_id'])
                       <strong>Jobs will run for Corporation ID: {{ $settings['selected_corporation_id'] }}</strong>
                   @else
                       <strong>Jobs will run for all corporations.</strong>
                   @endif
                </p>
                
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
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-tool" onclick="refreshJobStatus()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div id="running-jobs-alert" class="alert alert-warning d-none">
                    <i class="fas fa-spinner fa-spin"></i> <span id="running-count">0</span> job(s) currently running...
                </div>
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
                        <tbody id="job-status-table">
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

function refreshJobStatus() {
    fetch('{{ route("corpwalletmanager.settings.job-status") }}')
        .then(response => response.json())
        .then(data => {
            // Update running jobs alert
            const alertDiv = document.getElementById('running-jobs-alert');
            const runningCount = document.getElementById('running-count');
            
            if (data.running_jobs > 0) {
                alertDiv.classList.remove('d-none');
                runningCount.textContent = data.running_jobs;
            } else {
                alertDiv.classList.add('d-none');
            }
            
            // Update job table if we have recent jobs
            if (data.recent_jobs && data.recent_jobs.length > 0) {
                const tbody = document.getElementById('job-status-table');
                let html = '';
                
                data.recent_jobs.forEach(job => {
                    let badgeClass = 'badge-secondary';
                    if (job.status === 'running') badgeClass = 'badge-warning';
                    else if (job.status === 'completed') badgeClass = 'badge-success';
                    else if (job.status === 'failed') badgeClass = 'badge-danger';
                    
                    html += `
                        <tr>
                            <td>${job.job_type}</td>
                            <td><span class="badge ${badgeClass}">${job.status}</span></td>
                            <td>${job.started_at}</td>
                            <td>${job.duration}</td>
                            <td>${job.records_processed.toLocaleString()}</td>
                            <td>${job.corporation_id || 'All'}</td>
                        </tr>
                    `;
                });
                
                tbody.innerHTML = html;
            }
        })
        .catch(error => {
            console.error('Error fetching job status:', error);
        });
}

// Auto-refresh job status every 30 seconds
setInterval(refreshJobStatus, 30000);

// Initial load
document.addEventListener('DOMContentLoaded', function() {
    refreshJobStatus();
});
</script>
@endsection
