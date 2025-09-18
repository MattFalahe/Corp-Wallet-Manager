<?php

namespace MattFalahe\CorpWalletManager\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class InternalTransferService
{
    /**
     * EVE Online ref_types that typically indicate internal transfers
     * These need to be verified against actual EVE API data
     */
    private const INTERNAL_TRANSFER_REF_TYPES = [
        'corporation_account_withdrawal',
        'corporation_dividend_payment', 
        'secure_container_transfer',
        'corporate_reward_payout',
        'project_discovery_reward',
        // Add more as identified from actual data
    ];

    /**
     * Ref_types that are definitely internal division transfers
     */
    private const DIVISION_TRANSFER_REF_TYPES = [
        'corporation_account_withdrawal',  // Division to division transfer
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
     * Check if a journal entry is already marked as internal transfer
     */
    public function isMarkedAsInternal($journalId)
    {
        return DB::table('corpwalletmanager_internal_transfers')
            ->where('journal_id', $journalId)
            ->exists();
    }

    /**
     * Detect if a transaction is an internal transfer
     */
    public function isInternalTransfer($transaction)
    {
        // Check if already marked
        if ($this->isMarkedAsInternal($transaction->id)) {
            return true;
        }

        // Check if it's a known internal transfer ref_type
        $allRefTypes = array_merge(
            self::INTERNAL_TRANSFER_REF_TYPES,
            $this->customRefTypes
        );

        if (in_array($transaction->ref_type, $allRefTypes)) {
            // Additional validation: check if first and second party are the same (corporation)
            if ($transaction->first_party_id == $transaction->second_party_id) {
                return true;
            }

            // For division transfers, check if it's the same corporation but different divisions
            if (in_array($transaction->ref_type, self::DIVISION_TRANSFER_REF_TYPES)) {
                return $this->isSameCorporationTransfer($transaction);
            }
        }

        // Pattern matching for description-based detection
        return $this->detectByDescription($transaction);
    }

    /**
     * Check if transaction is between divisions of the same corporation
     */
    private function isSameCorporationTransfer($transaction)
    {
        // In EVE, division transfers within same corp have specific patterns
        // Check by wallet keys (Master Wallet = 1000, divisions = 1001-1006)
        if (isset($transaction->division)) {
            // Look for a matching opposite transaction
            $opposite = DB::table('corporation_wallet_journals')
                ->where('corporation_id', $transaction->corporation_id)
                ->where('id', '!=', $transaction->id)
                ->whereRaw('ABS(amount) = ?', [abs($transaction->amount)])
                ->whereRaw('amount * ? < 0', [$transaction->amount]) // Opposite sign
                ->whereBetween('date', [
                    Carbon::parse($transaction->date)->subSeconds(60),
                    Carbon::parse($transaction->date)->addSeconds(60)
                ])
                ->where('ref_type', $transaction->ref_type)
                ->first();

            return $opposite !== null;
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
                return true;
            }
        }

        return false;
    }

    /**
     * Categorize the type of internal transfer
     */
    public function categorizeInternalTransfer($transaction)
    {
        if (!$this->isInternalTransfer($transaction)) {
            return null;
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

        // Default category
        return 'other_internal';
    }

    /**
     * Mark a transaction as internal transfer in our plugin table
     */
    public function markAsInternalTransfer($transaction, $category = null)
    {
        if (!$category) {
            $category = $this->categorizeInternalTransfer($transaction);
        }

        // Check if already exists
        $existing = DB::table('corpwalletmanager_internal_transfers')
            ->where('journal_id', $transaction->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        // Find matching transfer for reconciliation
        $matchedId = null;
        $matching = $this->findMatchingTransfer($transaction);
        if ($matching) {
            $matchedId = $matching->id;
        }

        // Insert into our tracking table
        $id = DB::table('corpwalletmanager_internal_transfers')->insertGetId([
            'corporation_id' => $transaction->corporation_id,
            'journal_id' => $transaction->id,
            'ref_type' => $transaction->ref_type,
            'category' => $category,
            'amount' => $transaction->amount,
            'division' => $transaction->division ?? null,
            'to_division' => $matching->division ?? null,
            'matched_journal_id' => $matchedId,
            'is_reconciled' => $matchedId !== null,
            'transaction_date' => $transaction->date,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);

        // Update pattern matching statistics
        $this->updatePatternStatistics($transaction->ref_type, $transaction->corporation_id);

        return $id;
    }

    /**
     * Process and mark internal transfers in a batch of transactions
     */
    public function processTransactionBatch($transactions)
    {
        $stats = [
            'total' => count($transactions),
            'internal' => 0,
            'by_category' => []
        ];

        foreach ($transactions as $transaction) {
            if ($this->isInternalTransfer($transaction)) {
                $category = $this->categorizeInternalTransfer($transaction);
                $this->markAsInternalTransfer($transaction, $category);
                
                $stats['internal']++;
                $stats['by_category'][$category] = ($stats['by_category'][$category] ?? 0) + 1;
            }
        }

        Log::info('Internal transfer processing completed', $stats);
        return $stats;
    }

    /**
     * Find matching internal transfer (for reconciliation)
     */
    public function findMatchingTransfer($transaction, $timeWindow = 60)
    {
        if (!$this->isInternalTransfer($transaction)) {
            return null;
        }

        $searchTime = Carbon::parse($transaction->date);
        $amount = abs($transaction->amount);

        // Look for opposite transaction within time window
        $matching = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $transaction->corporation_id)
            ->where('id', '!=', $transaction->id)
            ->whereRaw('ABS(amount) = ?', [$amount])
            ->whereRaw('amount * ? < 0', [$transaction->amount]) // Opposite sign
            ->whereBetween('date', [
                $searchTime->copy()->subSeconds($timeWindow),
                $searchTime->copy()->addSeconds($timeWindow)
            ])
            ->where('ref_type', $transaction->ref_type)
            ->first();

        return $matching;
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
            'net_internal' => $stats->sum('total_in') - $stats->sum('total_out'), // Should be ~0
            'categories' => $stats->pluck('category')->unique()->values()
        ];
    }

    /**
     * Get internal transfer patterns for prediction adjustments
     */
    public function getTransferPatterns($corporationId, $months = 3)
    {
        $startDate = Carbon::now()->subMonths($months);

        // Join with our tracking table to get internal transfers
        $patterns = DB::table('corporation_wallet_journals as j')
            ->join('corpwalletmanager_internal_transfers as it', 'j.id', '=', 'it.journal_id')
            ->where('it.corporation_id', $corporationId)
            ->where('it.transaction_date', '>=', $startDate)
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
     * Update pattern statistics for machine learning
     */
    private function updatePatternStatistics($refType, $corporationId)
    {
        DB::table('corpwalletmanager_transfer_patterns')
            ->updateOrInsert(
                [
                    'corporation_id' => $corporationId,
                    'pattern_type' => 'ref_type',
                    'pattern_value' => $refType
                ],
                [
                    'match_count' => DB::raw('match_count + 1'),
                    'last_matched_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]
            );
    }

    /**
     * Get internal transfers with core journal data
     */
    public function getInternalTransfersWithJournals($corporationId, $startDate = null, $endDate = null)
    {
        $query = DB::table('corpwalletmanager_internal_transfers as it')
            ->join('corporation_wallet_journals as j', 'it.journal_id', '=', 'j.id')
            ->where('it.corporation_id', $corporationId)
            ->select([
                'it.*',
                'j.date',
                'j.ref_type',
                'j.amount',
                'j.balance',
                'j.reason',
                'j.first_party_id',
                'j.second_party_id',
                'j.division'
            ]);

        if ($startDate && $endDate) {
            $query->whereBetween('it.transaction_date', [$startDate, $endDate]);
        }

        return $query->get();
    }

    /**
     * Check if we should exclude internal transfers based on settings
     */
    public function shouldExcludeFromCharts($corporationId)
    {
        $settings = DB::table('corpwalletmanager_settings')
            ->where('corporation_id', $corporationId)
            ->first();

        return $settings->exclude_internal_transfers_charts ?? true;
    }
}
