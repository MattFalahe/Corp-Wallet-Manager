<?php
// Updated Prediction.php
namespace Seat\CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Model;

class Prediction extends Model
{
    protected $table = 'corpwalletmanager_predictions';
    
    protected $fillable = [
        'corporation_id',
        'date',
        'predicted_balance'
    ];

    protected $casts = [
        'predicted_balance' => 'decimal:2',
        'corporation_id' => 'integer',
        'date' => 'date',
    ];
    
    public function corporation()
    {
        return $this->belongsTo(\Seat\Eveapi\Models\Corporation\CorporationInfo::class, 'corporation_id', 'corporation_id');
    }
}
