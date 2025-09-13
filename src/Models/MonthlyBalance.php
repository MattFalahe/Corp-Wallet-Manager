<?php
namespace Seat\CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
    
    /**
     * Get the corporation that owns this balance record
     */
    public function corporation()
    {
        try {
            return $this->belongsTo(\Seat\Eveapi\Models\Corporation\CorporationInfo::class, 'corporation_id', 'corporation_id');
        } catch (\Exception $e) {
            Log::warning('MonthlyBalance: Corporation relationship error', [
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
            return $query->whereRaw('1 = 0'); // Return empty results for invalid ID
        }
        return $query->where('corporation_id', $corporationId);
    }
    
    /**
     * Scope to filter by month
     */
    public function scopeForMonth($query, $month)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $query->whereRaw('1 = 0'); // Return empty results for invalid month format
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
}
