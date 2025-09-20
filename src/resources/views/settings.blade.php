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

        <form action="{{ route('corpwalletmanager.settings.update') }}" method="POST">
            @csrf
            
            <!-- Main Settings Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">CorpWallet Manager Settings</h3>
                </div>
                <div class="card-body">
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
                </div>
            </div>

            <!-- Member View Settings Card -->
            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title">Member View Settings</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Section Visibility</h5>
                            <p class="text-muted">Control which sections are visible in the member view</p>
                            
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_health" 
                                           name="member_show_health" value="1" 
                                           {{ ($settings['member_show_health'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_health">
                                        Show Health Status
                                    </label>
                                </div>
                            </div>
            
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_trends" 
                                           name="member_show_trends" value="1" 
                                           {{ ($settings['member_show_trends'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_trends">
                                        Show Trend Charts
                                    </label>
                                </div>
                            </div>
            
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_activity" 
                                           name="member_show_activity" value="1" 
                                           {{ ($settings['member_show_activity'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_activity">
                                        Show Activity Metrics
                                    </label>
                                </div>
                            </div>
            
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_goals" 
                                           name="member_show_goals" value="1" 
                                           {{ ($settings['member_show_goals'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_goals">
                                        Show Corporation Goals
                                    </label>
                                </div>
                            </div>
            
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_milestones" 
                                           name="member_show_milestones" value="1" 
                                           {{ ($settings['member_show_milestones'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_milestones">
                                        Show Milestones & Events
                                    </label>
                                </div>
                            </div>
            
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_balance" 
                                           name="member_show_balance" value="1" 
                                           {{ ($settings['member_show_balance'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_balance">
                                        Show Actual ISK Values
                                    </label>
                                    <small class="form-text text-muted d-block">
                                        Uncheck to show normalized trends instead of actual amounts
                                    </small>
                                </div>
                            </div>
            
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="member_show_performance" 
                                           name="member_show_performance" value="1" 
                                           {{ ($settings['member_show_performance'] ?? true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="member_show_performance">
                                        Show Performance Metrics
                                    </label>
                                </div>
                            </div>
                        </div>
            
                        <div class="col-md-6">
                            <h5>Goal Settings</h5>
                            <p class="text-muted">Set targets for corporation goals</p>
                            
                            <div class="form-group">
                                <label for="goal_savings_target">Savings Target (ISK)</label>
                                <input type="number" class="form-control" id="goal_savings_target" 
                                       name="goal_savings_target" 
                                       value="{{ $settings['goal_savings_target'] ?? 1000000000 }}"
                                       min="0" step="1000000">
                                <small class="text-muted">Monthly savings goal in ISK</small>
                            </div>
            
                            <div class="form-group">
                                <label for="goal_activity_target">Activity Target</label>
                                <input type="number" class="form-control" id="goal_activity_target" 
                                       name="goal_activity_target" 
                                       value="{{ $settings['goal_activity_target'] ?? 1000 }}"
                                       min="0">
                                <small class="text-muted">Target number of monthly transactions</small>
                            </div>
            
                            <div class="form-group">
                                <label for="goal_growth_target">Growth Target (%)</label>
                                <input type="number" class="form-control" id="goal_growth_target" 
                                       name="goal_growth_target" 
                                       value="{{ $settings['goal_growth_target'] ?? 10 }}"
                                       min="0" max="100" step="0.1">
                                <small class="text-muted">Monthly growth percentage target</small>
                            </div>
            
                            <div class="form-group">
                                <label for="member_data_delay">Data Delay</label>
                                <select class="form-control" id="member_data_delay" name="member_data_delay">
                                    <option value="0" {{ ($settings['member_data_delay'] ?? '0') == '0' ? 'selected' : '' }}>
                                        Real-time
                                    </option>
                                    <option value="24" {{ ($settings['member_data_delay'] ?? '0') == '24' ? 'selected' : '' }}>
                                        24 hours delayed
                                    </option>
                                    <option value="48" {{ ($settings['member_data_delay'] ?? '0') == '48' ? 'selected' : '' }}>
                                        48 hours delayed
                                    </option>
                                    <option value="168" {{ ($settings['member_data_delay'] ?? '0') == '168' ? 'selected' : '' }}>
                                        1 week delayed
                                    </option>
                                </select>
                                <small class="text-muted">Delay data shown to members for operational security</small>
                            </div>
            
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> <strong>Member View Note:</strong>
                                <ul class="mb-0 mt-2">
                                    <li>The member view permission controls access to this view</li>
                                    <li>You can disable entire sections to customize what members see</li>
                                    <li>Data delay helps protect operational security</li>
                                    <li>Goals encourage member engagement</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Internal Transfer Settings -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-exchange-alt"></i> Internal Transfer Settings
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="exclude_internal_transfers_charts">
                                    <input type="checkbox" 
                                           name="exclude_internal_transfers_charts" 
                                           id="exclude_internal_transfers_charts"
                                           value="1"
                                           {{ old('exclude_internal_transfers_charts', $settings['exclude_internal_transfers_charts'] ?? true) ? 'checked' : '' }}>
                                    Exclude Internal Transfers from Charts
                                </label>
                                <small class="form-text text-muted">
                                    When enabled, internal division transfers won't affect income/expense charts
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label for="show_internal_transfers_separately">
                                    <input type="checkbox" 
                                           name="show_internal_transfers_separately" 
                                           id="show_internal_transfers_separately"
                                           value="1"
                                           {{ old('show_internal_transfers_separately', $settings['show_internal_transfers_separately'] ?? true) ? 'checked' : '' }}>
                                    Show Internal Transfers as Separate Category
                                </label>
                                <small class="form-text text-muted">
                                    Display internal transfers as a distinct category in transaction breakdowns
                                </small>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Internal Transfer Detection Status</label>
                                <div class="info-box bg-info">
                                    <span class="info-box-icon"><i class="fas fa-sync"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Detected Transfers</span>
                                        <span class="info-box-number" id="internal-transfer-count">Loading...</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <button type="button" class="btn btn-sm btn-warning" onclick="runInternalTransferBackfill()">
                                    <i class="fas fa-history"></i> Run Internal Transfer Detection
                                </button>
                                <small class="form-text text-muted">
                                    Scan historical data for internal transfers
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Save Button Row -->
            <div class="card mt-3">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Save All Settings
                    </button>
                    
                    <button type="button" class="btn btn-warning" onclick="resetSettings()">
                        <i class="fa fa-refresh"></i> Reset to Defaults
                    </button>
                </div>
            </div>
        </form>

        <!-- Maintenance Card -->
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

        <!-- Job Status Card -->
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

        <!-- Access Logs Card -->
        <div class="card mt-3">
            <div class="card-header">
                <h3 class="card-title">Access Logs</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-sm btn-tool" onclick="loadAccessLogs()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>View</th>
                                <th>Corporation</th>
                                <th>Accessed</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody id="access-logs-table">
                            <tr>
                                <td colspan="5" class="text-center text-muted">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-2">
                    <small class="text-muted">Showing last 50 access logs</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Fix SeAT's mixed content issue first
(function() {
    // Wait for jQuery to be available
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

function loadAccessLogs() {
    // Build URL with proper protocol
    const url = window.location.protocol + '//' + window.location.host + '/corp-wallet-manager/settings/access-logs';
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const tbody = document.getElementById('access-logs-table');
            
            if (data.logs && data.logs.length > 0) {
                let html = '';
                data.logs.forEach(log => {
                    html += `
                        <tr>
                            <td>${log.user}</td>
                            <td><span class="badge badge-info">${log.view}</span></td>
                            <td>${log.corporation}</td>
                            <td>${log.accessed_at}</td>
                            <td><small>${log.ip_address}</small></td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No access logs found</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error loading access logs:', error);
            document.getElementById('access-logs-table').innerHTML = 
                '<tr><td colspan="5" class="text-center text-muted">Access logs not available yet. Run migrations if needed.</td></tr>';
        });
}
    // Load internal transfer stats
    function loadInternalTransferStats() {
        $.ajax({
            url: '{{ route("corpwalletmanager.api.internal.stats") }}',
            data: {
                corporation_id: $('#selected_corporation_id').val() || null,
                days: 30
            },
            success: function(data) {
                $('#internal-transfer-count').text(data.count || '0');
            },
            error: function() {
                $('#internal-transfer-count').text('Error loading');
            }
        });
    }
    
    // Run internal transfer backfill
    function runInternalTransferBackfill() {
        if (!confirm('This will scan the last 30 days of transactions. Continue?')) {
            return;
        }
        
        $.ajax({
            url: '{{ route("corpwalletmanager.settings.internal-backfill") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                corporation_id: $('#selected_corporation_id').val()
            },
            success: function(response) {
                alert('Internal transfer detection started. Check job status for progress.');
                setTimeout(loadInternalTransferStats, 5000);
            },
            error: function(xhr) {
                alert('Failed to start detection: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }
        });
    }
    
    // Load stats on page load
    $(document).ready(function() {
        loadInternalTransferStats();
        
        // Reload when corporation changes
        $('#selected_corporation_id').change(function() {
            loadInternalTransferStats();
        });
    });

// Auto-refresh job status every 30 seconds
setInterval(refreshJobStatus, 30000);

// Auto-refresh access logs every 60 seconds
setInterval(loadAccessLogs, 60000);

// Initial load
document.addEventListener('DOMContentLoaded', function() {
    refreshJobStatus();
    loadAccessLogs();
});
</script>
@endsection
