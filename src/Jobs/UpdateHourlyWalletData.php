<?php
namespace CorpWalletManager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use CorpWalletManager\Models\MonthlyBalance;
use CorpWalletManager\Models\DivisionBalance;
use CorpWalletManager\Models\RecalcLog;
use CorpWalletManager\Models\Settings;
use CorpWalletManager\Support\JournalFilters;

class UpdateHourlyWalletData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;
    public $tries = 3;

    public function tags(): array
    {
        return ['corpwalletmanager', 'wallet-sync', 'hourly'];
    }

    public function handle()
    {
        $logEntry = null;
        
        try {
            $logEntry = RecalcLog::create([
                'job_type' => 'hourly_update',
                'status' => RecalcLog::STATUS_RUNNING,
                'started_at' => now(),
            ]);

            // Get the corporation to update (from settings or all)
            $corporationId = Settings::getSetting('selected_corporation_id');
            
            // Update data for the last 24 hours only (efficient)
            $since = Carbon::now()->subHours(24);
            
            // Get unique months from the last 24 hours of data
            $monthsToUpdateQuery = DB::table('corporation_wallet_journals')
                ->where('date', '>=', $since)
                ->when($corporationId, function ($query) use ($corporationId) {
                    return $query->where('corporation_id', $corporationId);
                });

            if ($corporationId) {
                $monthsToUpdateQuery = JournalFilters::excludeInternalTransfers($monthsToUpdateQuery, (int) $corporationId);
            } else {
                $monthsToUpdateQuery = JournalFilters::excludeInternalTransfers($monthsToUpdateQuery);
            }

            $monthsToUpdate = $monthsToUpdateQuery
                ->selectRaw('DISTINCT DATE_FORMAT(date, "%Y-%m") as month')
                ->pluck('month')
                ->toArray();
            
            $processed = 0;
            
            // Process each month that has recent activity
            foreach ($monthsToUpdate as $month) {
                $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
                
                // Get corporations with activity in this month
                $corporationQuery = DB::table('corporation_wallet_journals')
                    ->whereMonth('date', $monthDate->month)
                    ->whereYear('date', $monthDate->year)
                    ->selectRaw('
                        corporation_id,
                        SUM(amount) as balance
                    ')
                    ->groupBy('corporation_id');

                if ($corporationId) {
                    $corporationQuery->where('corporation_id', $corporationId);
                    $corporationQuery = JournalFilters::excludeInternalTransfers($corporationQuery, (int) $corporationId);
                } else {
                    $corporationQuery = JournalFilters::excludeInternalTransfers($corporationQuery);
                }

                $corporations = $corporationQuery->get();
                
                foreach ($corporations as $corp) {
                    // Use updateOrCreate to handle duplicates gracefully
                    MonthlyBalance::updateOrCreate(
                        [
                            'corporation_id' => $corp->corporation_id,
                            'month' => $month
                        ],
                        [
                            'balance' => $corp->balance
                        ]
                    );
                    $processed++;
                }
                
                // Update division balances for this month
                $divisionQuery = DB::table('corporation_wallet_journals')
                    ->whereMonth('date', $monthDate->month)
                    ->whereYear('date', $monthDate->year)
                    ->selectRaw('
                        corporation_id,
                        division,
                        SUM(amount) as balance
                    ')
                    ->groupBy('corporation_id', 'division');

                if ($corporationId) {
                    $divisionQuery->where('corporation_id', $corporationId);
                    $divisionQuery = JournalFilters::excludeInternalTransfers($divisionQuery, (int) $corporationId);
                } else {
                    $divisionQuery = JournalFilters::excludeInternalTransfers($divisionQuery);
                }

                $divisions = $divisionQuery->get();
                
                foreach ($divisions as $div) {
                    // Use updateOrCreate to handle duplicates gracefully
                    DivisionBalance::updateOrCreate(
                        [
                            'corporation_id' => $div->corporation_id,
                            'division_id' => $div->division,
                            'month' => $month
                        ],
                        [
                            'balance' => $div->balance
                        ]
                    );
                    $processed++;
                }
            }
            
            $logEntry->update([
                'status' => RecalcLog::STATUS_COMPLETED,
                'completed_at' => now(),
                'records_processed' => $processed,
            ]);
            
            Log::info('UpdateHourlyWalletData completed', [
                'records_processed' => $processed,
                'months_updated' => $monthsToUpdate
            ]);
            
        } catch (\Exception $e) {
            if ($logEntry) {
                $logEntry->update([
                    'status' => RecalcLog::STATUS_FAILED,
                    'completed_at' => now(),
                    'error_message' => substr($e->getMessage(), 0, 1000),
                ]);
            }
            
            Log::error('UpdateHourlyWalletData failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}
