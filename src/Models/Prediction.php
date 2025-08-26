<?php
namespace Seat\CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
    
    /**
     * Get the corporation that owns this prediction
     */
    public function corporation()
    {
        try {
            return $this->belongsTo(\Seat\Eveapi\Models\Corporation\CorporationInfo::class, 'corporation_id', 'corporation_id');
        } catch (\Exception $e) {
            Log::warning('Prediction: Corporation relationship error', [
                'prediction_id' => $this->id,
                'corporation_id' => $this->corporation_id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Scope to filter by corporation
     */
    public function scopeForCorporation($query, $corporationId)
    {
        if (!is_numeric($corporationId)) {
            return $query->whereRaw('1 = 0');
        }
        return $query->where('corporation_id', $corporationId);
    }
    
    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        try {
            if ($endDate) {
                return $query->whereBetween('date', [$startDate, $endDate]);
            }
            return $query->where('date', '>=', $startDate);
        } catch (\Exception $e) {
            Log::warning('Prediction: Invalid date range', [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'error' => $e->getMessage()
            ]);
            return $query->whereRaw('1 = 0');
        }
    }
    
    /**
     * Scope for future predictions only
     */
    public function scopeFuture($query)
    {
        return $query->where('date', '>', now());
    }
    
    /**
     * Get formatted predicted balance
     */
    public function getFormattedPredictedBalanceAttribute()
    {
        return number_format($this->predicted_balance, 2) . ' ISK';
    }
    
    /**
     * Get days until prediction date
     */
    public function getDaysUntilAttribute()
    {
        try {
            return now()->diffInDays($this->date, false);
        } catch (\Exception $e) {
            return null;
        }
    }
}
