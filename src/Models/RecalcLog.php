<?php
// RecalcLog.php
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
    
    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    
    public function corporation()
    {
        return $this->belongsTo(\Seat\Eveapi\Models\Corporation\CorporationInfo::class, 'corporation_id', 'corporation_id');
    }
}
