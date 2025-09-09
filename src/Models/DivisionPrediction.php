<?php
namespace Seat\CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
        try {
            return $this->belongsTo(\Seat\Eveapi\Models\Corporation\CorporationInfo::class, 'corporation_id', 'corporation_id');
        } catch (\Exception $e) {
            Log::warning('DivisionPrediction: Corporation relationship error', [
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
     * Scope to filter by division
     */
    public function scopeForDivision($query, $divisionId)
    {
        if (!is_numeric($divisionId)) {
            return $query->whereRaw('1 = 0');
        }
        return $query->where('division_id', $divisionId);
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
            Log::warning('DivisionPrediction: Invalid date range', [
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
     * Get division name from corporation_divisions table
     */
    public function getDivisionNameAttribute()
    {
        try {
            // Try to get the actual division name from corporation_divisions
            $division = DB::table('corporation_divisions')
                ->where('corporation_id', $this->corporation_id)
                ->where('division', $this->division_id)
                ->first();
            
            if ($division && !empty($division->name)) {
                return $division->name;
            }
            
            // Fallback to default names
            $defaultNames = [
                1 => 'Master Wallet',
                2 => '2nd Wallet Division',
                3 => '3rd Wallet Division',
                4 => '4th Wallet Division',
                5 => '5th Wallet Division',
                6 => '6th Wallet Division',
                7 => '7th Wallet Division',
            ];
            
            return $defaultNames[$this->division_id] ?? "Division {$this->division_id}";
            
        } catch (\Exception $e) {
            Log::warning('DivisionPrediction: Failed to get division name', [
                'corporation_id' => $this->corporation_id,
                'division_id' => $this->division_id,
                'error' => $e->getMessage()
            ]);
            
            return "Division {$this->division_id}";
        }
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
     * Get the actual division info from corporation_divisions
     */
    public function divisionInfo()
    {
        return DB::table('corporation_divisions')
            ->where('corporation_id', $this->corporation_id)
            ->where('division', $this->division_id)
            ->first();
    }
}
