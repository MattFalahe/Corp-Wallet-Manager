<?php

// DivisionPrediction.php  
namespace Seat\CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Model;

class DivisionPrediction extends Model
{
    protected $table = 'corpwalletmanager_division_predictions';
    
    protected $fillable = [
        'corporation_id',
        'division_id', 
        'date',
        'predicted_balance'
    ];

    protected $casts = [
        'predicted_balance' => 'decimal:2',
        'corporation_id' => 'integer',
        'division_id' => 'integer',
        'date' => 'date',
    ];
    
    public function corporation()
    {
        return $this->belongsTo(\Seat\Eveapi\Models\Corporation\CorporationInfo::class, 'corporation_id', 'corporation_id');
    }
}
