<?php
namespace Seat\CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
    
    /**
     * Get the corporation that owns this balance record
     */
    public function corporation()
    {
        try {
            return $this->belongsTo(\Seat\Eveapi\Models\Corporation\CorporationInfo::class, 'corporation_id', 'corporation_id');
        } catch (\Exception $e) {
            Log::warning('DivisionBalance: Corporation relationship error', [
                'balance_id' => $this->id,
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
     * Scope to filter by month
     */
    public function scopeForMonth($query, $month)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $query->whereRaw('1 = 0');
        }
        return $query->where('month', $month);
    }
    
    /**
     * Get formatted balance
     */
    public function getFormattedBalanceAttribute()
    {
        return number_format($this->balance, 2) . ' ISK';
    }
    
    /**
     * Get division name
     */
    public function getDivisionNameAttribute()
    {
        return "Division " . $this->division_id;
    }
}
