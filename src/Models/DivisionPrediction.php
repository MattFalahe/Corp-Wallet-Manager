<?php
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
    
    /**
     * Get the corporation that owns this prediction
     */
    public function corporation()
    {
        return $this->belongsTo(\Seat\Eveapi\Models\Corporation\CorporationInfo::class, 'corporation_id', 'corporation_id');
    }
    
    /**
     * Scope to filter by corporation
     */
    public function scopeForCorporation($query, $corporationId)
    {
        return $query->where('corporation_id', $corporationId);
    }
    
    /**
     * Scope to filter by division
     */
    public function scopeForDivision($query, $divisionId)
    {
        return $query->where('division_id', $divisionId);
    }
    
    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        if ($endDate) {
            return $query->whereBetween('date', [$startDate, $endDate]);
        }
        return $query->where('date', '>=', $startDate);
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
     * Get division name
     */
    public function getDivisionNameAttribute()
    {
        return "Division " . $this->division_id;
    }
    
    /**
     * Get days until prediction date
     */
    public function getDaysUntilAttribute()
    {
        return now()->diffInDays($this->date, false);
    }
}
