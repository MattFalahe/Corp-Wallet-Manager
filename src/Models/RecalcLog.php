<?php
namespace Seat\CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Model;

class RecalcLog extends Model
{
    protected $table = 'corpwalletmanager_recalc_logs';
    
    protected $fillable = [
        'job_type',
        'corporation_id',
        'status',
        'started_at',
        'completed_at',
        'error_message',
        'records_processed'
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'corporation_id' => 'integer',
        'records_processed' => 'integer',
    ];
    
    // Status constants
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    
    /**
     * Get the corporation that this log entry relates to
     */
    public function corporation()
    {
        return $this->belongsTo(\Seat\Eveapi\Models\Corporation\CorporationInfo::class, 'corporation_id', 'corporation_id');
    }
    
    /**
     * Scope to filter by status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }
    
    /**
     * Scope to filter by job type
     */
    public function scopeJobType($query, $jobType)
    {
        return $query->where('job_type', $jobType);
    }
    
    /**
     * Scope for running jobs
     */
    public function scopeRunning($query)
    {
        return $query->where('status', self::STATUS_RUNNING);
    }
    
    /**
     * Scope for completed jobs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }
    
    /**
     * Scope for failed jobs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }
    
    /**
     * Scope for recent logs (last 24 hours)
     */
    public function scopeRecent($query)
    {
        return $query->where('started_at', '>=', now()->subHours(24));
    }
    
    /**
     * Get the duration of the job execution
     */
    public function getDurationAttribute()
    {
        if (!$this->completed_at) {
            return null;
        }
        
        return $this->started_at->diffInSeconds($this->completed_at);
    }
    
    /**
     * Get formatted duration
     */
    public function getFormattedDurationAttribute()
    {
        $duration = $this->duration;
        if (!$duration) {
            return 'N/A';
        }
        
        if ($duration < 60) {
            return $duration . 's';
        } elseif ($duration < 3600) {
            return round($duration / 60, 1) . 'm';
        } else {
            return round($duration / 3600, 1) . 'h';
        }
    }
    
    /**
     * Check if job is still running
     */
    public function getIsRunningAttribute()
    {
        return $this->status === self::STATUS_RUNNING;
    }
    
    /**
     * Check if job completed successfully
     */
    public function getIsCompletedAttribute()
    {
        return $this->status === self::STATUS_COMPLETED;
    }
    
    /**
     * Check if job failed
     */
    public function getIsFailedAttribute()
    {
        return $this->status === self::STATUS_FAILED;
    }
    
    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeClassAttribute()
    {
        switch ($this->status) {
            case self::STATUS_RUNNING:
                return 'badge-warning';
            case self::STATUS_COMPLETED:
                return 'badge-success';
            case self::STATUS_FAILED:
                return 'badge-danger';
            default:
                return 'badge-secondary';
        }
    }
    
    /**
     * Get job type display name
     */
    public function getJobTypeDisplayAttribute()
    {
        switch ($this->job_type) {
            case 'wallet_backfill':
                return 'Wallet Backfill';
            case 'daily_prediction':
                return 'Daily Prediction';
            case 'division_backfill':
                return 'Division Backfill';
            case 'division_prediction':
                return 'Division Prediction';
            default:
                return ucwords(str_replace('_', ' ', $this->job_type));
        }
    }
}
