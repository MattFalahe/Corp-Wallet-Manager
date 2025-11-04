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
        'predicted_balance',
        'confidence',
        'lower_bound',
        'upper_bound',
        'prediction_method',
        'metadata'
    ];

    protected $casts = [
        'predicted_balance' => 'decimal:2',
        'confidence' => 'decimal:2',
        'lower_bound' => 'decimal:2',
        'upper_bound' => 'decimal:2',
        'corporation_id' => 'integer',
        'date' => 'date',
        'metadata' => 'array', // Laravel will handle text-to-array conversion
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

     /**
     * Get confidence level as text
     */
    public function getConfidenceLevelAttribute()
    {
        if ($this->confidence >= 80) return 'High';
        if ($this->confidence >= 60) return 'Medium';
        if ($this->confidence >= 40) return 'Low';
        return 'Very Low';
    }
    
    /**
     * Get prediction range
     */
    public function getPredictionRangeAttribute()
    {
        if ($this->lower_bound && $this->upper_bound) {
            return [
                'lower' => $this->lower_bound,
                'predicted' => $this->predicted_balance,
                'upper' => $this->upper_bound,
                'spread' => $this->upper_bound - $this->lower_bound
            ];
        }
        
        return null;
    }
    
    /**
     * Check if prediction is high confidence
     */
    public function isHighConfidence()
    {
        return $this->confidence >= 80;
    }
    
    /**
     * Get metadata value
     */
    public function getMetadataValue($key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }
}
