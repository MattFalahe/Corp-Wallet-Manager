<?php

namespace MattFalahe\CorpWalletManager\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MattFalahe\CorpWalletManager\Services\InternalTransferService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InternalTransferApiController extends Controller
{
    protected $transferService;

    /**
     * Get internal transfer statistics
     */
    public function getStatistics(Request $request)
    {
        $corporationId = $request->get('corporation_id');
        $days = $request->get('days', 30);
        
        if (!$corporationId) {
            return response()->json(['error' => 'Corporation ID required'], 400);
        }

        $this->transferService = new InternalTransferService($corporationId);
        
        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        // Get overall statistics
        $stats = $this->transferService->calculateStatistics($corporationId, $startDate, $endDate);
        
        // Get daily data for chart
        $dailyData = $this->getDailyTransferData($corporationId, $startDate, $endDate);
        
        // Get most active division
        $mostActiveDiv = $this->getMostActiveDivision($corporationId, $startDate, $endDate);
        
        // Get transfer patterns
        $patterns = $this->transferService->getTransferPatterns($corporationId, 3);
        
        // Calculate total volume
        $volume = $stats['total_volume'] ?? 0;
        $count = array_sum(array_column($stats['by_category']->toArray(), 'total_count'));
        $net = $stats['net_internal'] ?? 0;

        return response()->json([
            'volume' => $volume,
            'count' => $count,
            'net' => $net,
            'most_active_division' => $mostActiveDiv,
            'daily_data' => $dailyData,
            'patterns' => $patterns,
            'by_category' => $stats['by_category'],
            'categories' => $stats['categories']
        ]);
    }

    /**
     * Get daily transfer data for charts
     */
    private function getDailyTransferData($corporationId, $startDate, $endDate)
    {
        $data = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->where('is_internal_transfer', true)
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('
                DATE(date) as date,
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as internal_in,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as internal_out,
                COUNT(*) as count
            ')
            ->groupBy(DB::raw('DATE(date)'))
            ->orderBy('date')
            ->get();

        return $data->map(function($item) {
            return [
                'date' => Carbon::parse($item->date)->format('M d'),
                'internal_in' => (float) $item->internal_in,
                'internal_out' => (float) $item->internal_out,
                'count' => (int) $item->count
            ];
        });
    }

    /**
     * Get most active division for internal transfers
     */
    private function getMostActiveDivision($corporationId, $startDate, $endDate)
    {
        $result = DB::table('corporation_wallet_journals as j')
            ->join('corporation_divisions as d', function($join) {
                $join->on('j.corporation_id', '=', 'd.corporation_id')
                     ->on('j.division', '=', 'd.division');
            })
            ->where('j.corporation_id', $corporationId)
            ->where('j.is_internal_transfer', true)
            ->whereBetween('j.date', [$startDate, $endDate])
            ->selectRaw('
                d.name,
                COUNT(*) as transfer_count,
                SUM(ABS(j.amount)) as volume
            ')
            ->groupBy('d.name')
            ->orderByDesc('transfer_count')
            ->first();

        return $result ? $result->name : null;
    }

    /**
     * Save internal transfer settings
     */
    public function saveSettings(Request $request)
    {
        $corporationId = $request->get('corporation_id');
        $settings = $request->get('settings');
        
        if (!$corporationId || !$settings) {
            return response()->json(['error' => 'Invalid parameters'], 400);
        }

        // Update settings
        $updated = DB::table('corpwalletmanager_settings')
            ->updateOrInsert(
                ['corporation_id' => $corporationId],
                [
                    'exclude_internal_transfers_charts' => $settings['exclude_internal_transfers_charts'] ?? true,
                    'show_internal_transfers_separately' => $settings['show_internal_transfers_separately'] ?? true,
                    'internal_transfer_ref_types' => json_encode($settings['custom_ref_types'] ?? []),
                    'updated_at' => Carbon::now()
                ]
            );

        // Clear cache for this corporation
        \Cache::tags(['corp_wallet_' . $corporationId])->flush();

        return response()->json([
            'success' => true,
            'message' => 'Settings saved successfully'
        ]);
    }

    /**
     * Analyze internal transfers for a specific period
     */
    public function analyze(Request $request)
    {
        $corporationId = $request->get('corporation_id');
        $startDate = $request->get('start_date', Carbon::now()->subMonth()->toDateString());
        $endDate = $request->get('end_date', Carbon::now()->toDateString());
        
        if (!$corporationId) {
            return response()->json(['error' => 'Corporation ID required'], 400);
        }

        $this->transferService = new InternalTransferService($corporationId);

        // Get all internal transfers for the period
        $transfers = DB::table('corporation_wallet_journals')
            ->where('corporation_id', $corporationId)
            ->where('is_internal_transfer', true)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        // Find matched pairs
        $pairs = [];
        $unmatched = [];
        $processed = [];

        foreach ($transfers as $transfer) {
            if (in_array($transfer->id, $processed)) {
                continue;
            }

            $match = $this->transferService->findMatchingTransfers($transfer);
            
            if ($match) {
                $pairs[] = [
                    'date' => $transfer->date,
                    'amount' => abs($transfer->amount),
                    'from_division' => $transfer->amount < 0 ? $transfer->division : $match->division,
                    'to_division' => $transfer->amount > 0 ? $transfer->division : $match->division,
                    'ref_type' => $transfer->ref_type,
                    'category' => $transfer->internal_transfer_category
                ];
                $processed[] = $transfer->id;
                $processed[] = $match->id;
            } else {
                $unmatched[] = [
                    'date' => $transfer->date,
                    'amount' => $transfer->amount,
                    'division' => $transfer->division,
                    'ref_type' => $transfer->ref_type,
                    'category' => $transfer->internal_transfer_category
                ];
            }
        }

        // Calculate reconciliation status
        $totalTransfers = count($transfers);
        $matchedCount = count($pairs) * 2;
        $unmatchedCount = count($unmatched);
        $reconciliationRate = $totalTransfers > 0 
            ? round(($matchedCount / $totalTransfers) * 100, 2) 
            : 100;

        return response()->json([
            'period' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'summary' => [
                'total_transfers' => $totalTransfers,
                'matched_pairs' => count($pairs),
                'unmatched' => $unmatchedCount,
                'reconciliation_rate' => $reconciliationRate
            ],
            'matched_pairs' => array_slice($pairs, 0, 20), // Limit to 20 for display
            'unmatched_transfers' => array_slice($unmatched, 0, 10), // Limit to 10 for display
            'recommendations' => $this->generateRecommendations($reconciliationRate, $unmatched)
        ]);
    }

    /**
     * Generate recommendations based on analysis
     */
    private function generateRecommendations($reconciliationRate, $unmatched)
    {
        $recommendations = [];

        if ($reconciliationRate < 90) {
            $recommendations[] = [
                'type' => 'warning',
                'message' => 'Low reconciliation rate detected. Some internal transfers may not be properly matched.',
                'action' => 'Review unmatched transfers and verify ref_type classifications.'
            ];
        }

        if (count($unmatched) > 0) {
            $refTypes = array_unique(array_column($unmatched, 'ref_type'));
            if (count($refTypes) > 0) {
                $recommendations[] = [
                    'type' => 'info',
                    'message' => 'Unmatched transfers found with ref_types: ' . implode(', ', array_slice($refTypes, 0, 3)),
                    'action' => 'Consider adding these ref_types to custom internal transfer types if they represent internal movements.'
                ];
            }
        }

        if ($reconciliationRate === 100 && count($unmatched) === 0) {
            $recommendations[] = [
                'type' => 'success',
                'message' => 'All internal transfers are properly matched and reconciled.',
                'action' => 'No action required. System is working correctly.'
            ];
        }

        return $recommendations;
    }

    /**
     * Get division-to-division transfer matrix
     */
    public function getTransferMatrix(Request $request)
    {
        $corporationId = $request->get('corporation_id');
        $days = $request->get('days', 30);
        
        if (!$corporationId) {
            return response()->json(['error' => 'Corporation ID required'], 400);
        }

        $startDate = Carbon::now()->subDays($days);

        // Get divisions
        $divisions = DB::table('corporation_divisions')
            ->where('corporation_id', $corporationId)
            ->pluck('name', 'division')
            ->toArray();

        // Build transfer matrix
        $matrix = [];
        foreach ($divisions as $fromDiv => $fromName) {
            foreach ($divisions as $toDiv => $toName) {
                if ($fromDiv === $toDiv) continue;
                
                $transfers = DB::table('corporation_wallet_journals as j1')
                    ->where('j1.corporation_id', $corporationId)
                    ->where('j1.is_internal_transfer', true)
                    ->where('j1.division', $fromDiv)
                    ->where('j1.amount', '<', 0)
                    ->where('j1.date', '>=', $startDate)
                    ->whereExists(function($query) use ($corporationId, $toDiv) {
                        $query->select(DB::raw(1))
                              ->from('corporation_wallet_journals as j2')
                              ->whereRaw('j2.corporation_id = j1.corporation_id')
                              ->whereRaw('j2.is_internal_transfer = 1')
                              ->whereRaw('j2.division = ?', [$toDiv])
                              ->whereRaw('ABS(j2.amount) = ABS(j1.amount)')
                              ->whereRaw('j2.amount > 0')
                              ->whereRaw('ABS(TIMESTAMPDIFF(SECOND, j2.date, j1.date)) <= 60');
                    })
                    ->selectRaw('COUNT(*) as count, SUM(ABS(amount)) as volume')
                    ->first();

                if ($transfers->count > 0) {
                    $matrix[] = [
                        'from' => $fromName,
                        'to' => $toName,
                        'count' => $transfers->count,
                        'volume' => $transfers->volume
                    ];
                }
            }
        }

        return response()->json([
            'divisions' => array_values($divisions),
            'matrix' => $matrix,
            'period_days' => $days
        ]);
    }
}
