<?php
// Updated MonthlyBalance.php
namespace Seat\CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyBalance extends Model
{
    protected $table = 'corpwalletmanager_monthly_balances';
    
    protected $fillable = [
        'corporation_id',
        'month',
        'balance'
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'corporation_id' => 'integer',
    ];
    
    public function corporation()
    {
        return $this->belongsTo(\Seat\Eveapi\Models\Corporation\CorporationInfo::class, 'corporation_id', 'corporation_id');
    }
}
