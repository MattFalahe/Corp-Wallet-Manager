<?php
namespace Seat\CorpWalletManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
            Log::warning('DivisionBalance: Failed to get division name', [
                'corporation_id' => $this->corporation_id,
                'division_id' => $this->division_id,
                'error' => $e->getMessage()
            ]);
            
            return "Division {$this->division_id}";
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
