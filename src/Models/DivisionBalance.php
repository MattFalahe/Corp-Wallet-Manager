<?php
// DivisionBalance.php
namespace Seat\CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Model;

class DivisionBalance extends Model
{
    protected $table = 'corpwalletmanager_division_balances';
    
    protected $fillable = [
        'corporation_id',
        'division_id',
        'month',
        'balance'
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'corporation_id' => 'integer',
        'division_id' => 'integer',
    ];
    
    public function corporation()
    {
        return $this->belongsTo(\Seat\Eveapi\Models\Corporation\CorporationInfo::class, 'corporation_id', 'corporation_id');
    }
}
