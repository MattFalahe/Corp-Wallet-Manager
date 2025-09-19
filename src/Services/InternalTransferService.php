<?php

namespace Seat\CorpWalletManager\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class InternalTransferService
{
    /**
     * EVE Online ref_types that typically indicate internal transfers
     */
    private const INTERNAL_TRANSFER_REF_TYPES = [
        'corporation_account_withdrawal',
        'corporation_dividend_payment', 
        'secure_container_transfer',
        'corporate_reward_payout',
        'project_discovery_reward',
    ];

    /**
     * Ref_types that are definitely internal division transfers
     */
    private const DIVISION_TRANSFER_REF_TYPES = [
        'corporation_account_withdrawal',
    ];

    protected $corporationId;
    protected $customRefTypes = [];

    public function __construct($corporationId = null)
    {
        $this->corporationId = $corporationId;
        $this->loadCustomRefTypes();
    }

    /**
     * Load custom ref_types from settings
     */
    private function loadCustomRefTypes()
    {
        $settings = DB::table('corpwalletmanager_settings')
            ->where('corporation_id', $this->corporationId ?? 0)
            ->first();

        if ($settings && $settings->internal_transfer_ref_types) {
            $this->customRefTypes = json_decode($settings->internal_transfer_ref_types, true) ?? [];
        }
    }

    /**
     * Detect if a transaction is an internal transfer
     */
    public function isInternalTransfer($transaction)
    {
        // Check our metadata table first
        $metadata = DB::table('corpwalletmanager_journal_metadata')
            ->where('journal_id', $transaction->id)
            ->first();
        
        if ($metadata && $metadata->is_internal_transfer) {
            return true;
        }
    
        // Detection logic for division transfers
        if ($this->isDivisionTransfer($transaction)) {
            return true;
        }
    
        // Check for known ref_types
        if ($this->detectByRefType($transaction)) {
            return true;
        }

        // Check for description patterns
        if ($this->detectByDescription($transaction)) {
            return true;
        }
    
        return false;
    }
    
    /**
     * Check if transaction is a division transfer
     */
    private function isDivisionTransfer($transaction)
    {
        // Division transfers have specific ref_types
        if (!in_array($transaction->ref_type, self::DIVISION_TRANSFER_REF_TYPES)) {
            return false;
        }
    
        // Look for matching opposite transaction within 1 minute
        $opposite = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $transaction->corporation_id)
            ->where('id', '!=', $transaction->id)
            ->whereRaw('ABS(amount) = ?', [abs($transaction->amount)])
            ->where('amount', $transaction->amount > 0 ? '<' : '>', 0)
            ->whereBetween('date', [
                Carbon::parse($transaction->date)->subMinute(),
                Carbon::parse($transaction->date)->addMinute()
            ])
            ->first();
    
        if ($opposite) {
            // Mark both as internal
            $this->markAsInternal($transaction, 'division_transfer', $opposite->id);
            $this->markAsInternal($opposite, 'division_transfer', $transaction->id);
            return true;
        }
    
        return false;
    }

    /**
     * Detect by ref_type patterns
     */
    private function detectByRefType($transaction)
    {
        $allRefTypes = array_merge(
            self::INTERNAL_TRANSFER_REF_TYPES,
            $this->customRefTypes
        );

        if (in_array($transaction->ref_type, $allRefTypes)) {
            // Additional validation for some types
            if ($transaction->ref_type === 'corporation_dividend_payment') {
                $this->markAsInternal($transaction, 'dividend');
                return true;
            }
            
            if ($transaction->ref_type === 'corporate_reward_payout') {
                $this->markAsInternal($transaction, 'reward');
                return true;
            }

            $this->markAsInternal($transaction, 'other_internal');
            return true;
        }

        return false;
    }

    /**
     * Detect internal transfers by description patterns
     */
    private function detectByDescription($transaction)
    {
        if (!isset($transaction->reason) || empty($transaction->reason)) {
            return false;
        }

        $patterns = [
            '/division.*transfer/i',
            '/internal.*transfer/i',
            '/wallet.*division/i',
            '/move.*funds.*division/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $transaction->reason)) {
                $this->markAsInternal($transaction, 'description_match');
                return true;
            }
        }

        return false;
    }
    
    /**
     * Mark a transaction as internal
     */
    private function markAsInternal($transaction, $category, $matchedId = null)
    {
        // Update metadata table
        DB::table('corpwalletmanager_journal_metadata')->updateOrInsert(
            ['journal_id' => $transaction->id],
            [
                'corporation_id' => $transaction->corporation_id,
                'is_internal_transfer' => true,
                'internal_transfer_category' => $category,
                'matched_transfer_id' => $matchedId,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
        
        // Update tracking table
        DB::table('corpwalletmanager_internal_transfers')->updateOrInsert(
            ['journal_id' => $transaction->id],
            [
                'corporation_id' => $transaction->corporation_id,
                'ref_type' => $transaction->ref_type,
                'category' => $category,
                'amount' => $transaction->amount,
                'division' => $transaction->division ?? null,
                'matched_journal_id' => $matchedId,
                'is_reconciled' => $matchedId !== null,
                'transaction_date' => $transaction->date,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
    }

    /**
     * Categorize the type of internal transfer
     */
    public function categorizeInternalTransfer($transaction)
    {
        // Check metadata first
        $metadata = DB::table('corpwalletmanager_journal_metadata')
            ->where('journal_id', $transaction->id)
            ->first();
        
        if ($metadata && $metadata->internal_transfer_category) {
            return $metadata->internal_transfer_category;
        }

        // Categorize based on ref_type
        if (in_array($transaction->ref_type, self::DIVISION_TRANSFER_REF_TYPES)) {
            return 'division_transfer';
        }

        if ($transaction->ref_type == 'corporation_dividend_payment') {
            return 'dividend';
        }

        if ($transaction->ref_type == 'corporate_reward_payout') {
            return 'reward';
        }

        return 'other_internal';
    }

    /**
     * Find matching internal transfer (for reconciliation)
     */
    public function findMatchingTransfer($transaction, $timeWindow = 60)
    {
        $searchTime = Carbon::parse($transaction->date);
        $amount = abs($transaction->amount);

        // Look for opposite transaction within time window
        $matching = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $transaction->corporation_id)
            ->where('id', '!=', $transaction->id)
            ->whereRaw('ABS(amount) = ?', [$amount])
            ->where('amount', $transaction->amount > 0 ? '<' : '>', 0)
            ->whereBetween('date', [
                $searchTime->copy()->subSeconds($timeWindow),
                $searchTime->copy()->addSeconds($timeWindow)
            ])
            ->first();

        return $matching;
    }

    /**
     * Find matching transfers (for finding pairs)
     */
    public function findMatchingTransfers($transaction, $timeWindow = 60)
    {
        return $this->findMatchingTransfer($transaction, $timeWindow);
    }

    /**
     * Calculate internal transfer statistics for a period
     */
    public function calculateStatistics($corporationId, $startDate, $endDate)
    {
        $stats = DB::table('corpwalletmanager_internal_transfers')
            ->where('corporation_id', $corporationId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_count,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_in,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_out,
                category,
                COUNT(DISTINCT DATE(transaction_date)) as active_days
            ')
            ->groupBy('category')
            ->get();

        return [
            'by_category' => $stats,
            'total_volume' => $stats->sum('total_in') + $stats->sum('total_out'),
            'net_internal' => $stats->sum('total_in') - $stats->sum('total_out'),
            'categories' => $stats->pluck('category')->unique()->values()
        ];
    }

    /**
     * Get internal transfer patterns for prediction adjustments
     */
    public function getTransferPatterns($corporationId, $months = 3)
    {
        $startDate = Carbon::now()->subMonths($months);

        // Get patterns from metadata table
        $patterns = DB::table('corporation_wallet_journals as j')
            ->join('corpwalletmanager_journal_metadata as m', 'j.id', '=', 'm.journal_id')
            ->where('m.corporation_id', $corporationId)
            ->where('m.is_internal_transfer', true)
            ->where('j.date', '>=', $startDate)
            ->selectRaw('
                DAYOFWEEK(j.date) as day_of_week,
                DAY(j.date) as day_of_month,
                HOUR(j.date) as hour_of_day,
                COUNT(*) as frequency,
                AVG(ABS(j.amount)) as avg_amount
            ')
            ->groupBy(DB::raw('DAYOFWEEK(j.date), DAY(j.date), HOUR(j.date)'))
            ->having('frequency', '>=', 2)
            ->get();

        return $this->analyzePatterns($patterns);
    }

    /**
     * Analyze transfer patterns for insights
     */
    private function analyzePatterns($patterns)
    {
        $insights = [
            'most_common_day' => null,
            'most_common_hour' => null,
            'recurring_amounts' => [],
            'pattern_strength' => 0
        ];

        if ($patterns->isEmpty()) {
            return $insights;
        }

        // Find most common day of week
        $byDay = $patterns->groupBy('day_of_week');
        $insights['most_common_day'] = $byDay->sortByDesc(function($group) {
            return $group->sum('frequency');
        })->keys()->first();

        // Find most common hour
        $byHour = $patterns->groupBy('hour_of_day');
        $insights['most_common_hour'] = $byHour->sortByDesc(function($group) {
            return $group->sum('frequency');
        })->keys()->first();

        // Identify recurring amounts
        $amounts = $patterns->pluck('avg_amount')->map(function($amount) {
            return round($amount / 1000000) * 1000000; // Round to nearest million
        });
        
        $insights['recurring_amounts'] = $amounts->countBy()->sortDesc()->take(3)->toArray();

        // Calculate pattern strength (0-1)
        $totalFrequency = $patterns->sum('frequency');
        $uniqueDays = $patterns->unique('day_of_month')->count();
        $insights['pattern_strength'] = min(1, $totalFrequency / ($uniqueDays * 10));

        return $insights;
    }

    /**
     * Check if we should exclude internal transfers based on settings
     */
    public function shouldExcludeFromCharts($corporationId)
    {
        $settings = DB::table('corpwalletmanager_settings')
            ->where('corporation_id', $corporationId)
            ->first();

        return $settings ? ($settings->exclude_internal_transfers_charts ?? true) : true;
    }
}
