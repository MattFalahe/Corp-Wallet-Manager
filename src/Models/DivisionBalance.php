<?php
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
    
    /**
     * Get the corporation that owns this balance record
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
     * Scope to filter by month
     */
    public function scopeForMonth($query, $month)
    {
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
