<?php

namespace MattFalahe\CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use MattFalahe\CorpWalletManager\Services\InternalTransferService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BackfillInternalTransfers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'corpwalletmanager:backfill-internal 
                            {--corporation=all : Corporation ID or "all" for all corporations}
                            {--months=12 : Number of months to backfill}
                            {--dry-run : Run without making changes}
                            {--analyze : Show analysis of detected patterns}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Identify and flag internal transfers in historical wallet data';

    protected $transferService;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $corporationId = $this->option('corporation');
        $months = (int) $this->option('months');
        $dryRun = $this->option('dry-run');
        $analyze = $this->option('analyze');

        $this->info('═══════════════════════════════════════════════════════════');
        $this->info(' Internal Transfer Backfill Process');
        $this->info('═══════════════════════════════════════════════════════════');
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        // Get corporations to process
        $corporations = $this->getCorporations($corporationId);
        
        if ($corporations->isEmpty()) {
            $this->error('No corporations found to process');
            return 1;
        }

        $this->info("📊 Processing {$corporations->count()} corporation(s) for {$months} months of data\n");

        $totalStats = [
            'corporations' => 0,
            'transactions' => 0,
            'internal_transfers' => 0,
            'by_type' => []
        ];

        foreach ($corporations as $corp) {
            $this->processCorpration($corp, $months, $dryRun, $analyze, $totalStats);
        }

        // Display summary
        $this->displaySummary($totalStats);

        return 0;
    }

    /**
     * Get corporations to process
     */
    private function getCorporations($corporationId)
    {
        if ($corporationId === 'all') {
            return DB::table('corporations')
                ->select('corporation_id', 'name')
                ->get();
        }

        return DB::table('corporations')
            ->where('corporation_id', $corporationId)
            ->select('corporation_id', 'name')
            ->get();
    }

    /**
     * Process a single corporation
     */
    private function processCorpration($corp, $months, $dryRun, $analyze, &$totalStats)
    {
        $this->info("\n🏢 Processing: {$corp->name} (ID: {$corp->corporation_id})");
        
        $this->transferService = new InternalTransferService($corp->corporation_id);
        
        if ($analyze) {
            $this->analyzePatterns($corp->corporation_id, $months);
        }

        $startDate = Carbon::now()->subMonths($months);
        $stats = $this->detectInternalTransfers($corp->corporation_id, $startDate, $dryRun);
        
        // Update total statistics
        $totalStats['corporations']++;
        $totalStats['transactions'] += $stats['processed'];
        $totalStats['internal_transfers'] += $stats['internal'];
        
        foreach ($stats['by_type'] as $type => $count) {
            $totalStats['by_type'][$type] = ($totalStats['by_type'][$type] ?? 0) + $count;
        }

        // Display corporation results
        $this->displayCorporationResults($corp->name, $stats);

        // Update daily summaries if not dry run
        if (!$dryRun && $stats['internal'] > 0) {
            $this->updateDailySummaries($corp->corporation_id, $startDate);
        }
    }

    /**
     * Detect and flag internal transfers
     */
    private function detectInternalTransfers($corporationId, $startDate, $dryRun)
    {
        $batchSize = 1000;
        $processed = 0;
        $internal = 0;
        $byType = [];
        $examples = [];

        $this->output->write('  ⏳ Scanning transactions');

        do {
            $transactions = DB::table('corporation_wallet_journals')
                ->where('corporation_id', $corporationId)
                ->where('date', '>=', $startDate)
                ->where(function($query) {
                    $query->whereNull('is_internal_transfer')
                          ->orWhere('is_internal_transfer', false);
                })
                ->limit($batchSize)
                ->get();

            if ($transactions->isEmpty()) {
                break;
            }

            foreach ($transactions as $transaction) {
                if ($this->transferService->isInternalTransfer($transaction)) {
                    $category = $this->transferService->categorizeInternalTransfer($transaction);
                    $internal++;
                    $byType[$category] = ($byType[$category] ?? 0) + 1;

                    // Collect examples for display
                    if (count($examples) < 5) {
                        $examples[] = [
                            'date' => Carbon::parse($transaction->date)->format('Y-m-d H:i'),
                            'amount' => number_format(abs($transaction->amount), 2),
                            'ref_type' => $transaction->ref_type,
                            'category' => $category
                        ];
                    }

                    if (!$dryRun) {
                        DB::table('corporation_wallet_journals')
                            ->where('id', $transaction->id)
                            ->update([
                                'is_internal_transfer' => true,
                                'internal_transfer_category' => $category
                            ]);
                    }
                }
            }

            $processed += $transactions->count();
            $this->output->write('.');

        } while ($transactions->count() == $batchSize);

        $this->output->writeln(" ✓");

        // Display examples if found
        if (!empty($examples) && $this->output->isVerbose()) {
            $this->info("\n  📝 Sample Internal Transfers Detected:");
            $this->table(
                ['Date', 'Amount', 'Ref Type', 'Category'],
                $examples
            );
        }

        return [
            'processed' => $processed,
            'internal' => $internal,
            'by_type' => $byType
        ];
    }

    /**
     * Analyze transfer patterns
     */
    private function analyzePatterns($corporationId, $months)
    {
        $this->info("\n  📈 Analyzing Transfer Patterns:");
        
        $patterns = $this->transferService->getTransferPatterns($corporationId, $months);
        
        if ($patterns['pattern_strength'] > 0) {
            $this->line("  • Pattern Strength: " . round($patterns['pattern_strength'] * 100, 1) . "%");
            
            if ($patterns['most_common_day']) {
                $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $this->line("  • Most Common Day: " . $days[$patterns['most_common_day'] - 1]);
            }
            
            if ($patterns['most_common_hour']) {
                $this->line("  • Most Common Hour: " . $patterns['most_common_hour'] . ":00");
            }
            
            if (!empty($patterns['recurring_amounts'])) {
                $this->line("  • Recurring Amounts:");
                foreach ($patterns['recurring_amounts'] as $amount => $frequency) {
                    $this->line("    - " . number_format($amount) . " ISK ({$frequency} times)");
                }
            }
        } else {
            $this->line("  • No strong patterns detected");
        }
    }

    /**
     * Update daily summaries with internal transfer data
     */
    private function updateDailySummaries($corporationId, $startDate)
    {
        $this->output->write('  ⏳ Updating daily summaries');

        DB::statement("
            UPDATE corpwalletmanager_daily_summaries ds
            INNER JOIN (
                SELECT 
                    corporation_id,
                    DATE(date) as day,
                    SUM(CASE WHEN amount > 0 AND is_internal_transfer = 1 THEN amount ELSE 0 END) as internal_in,
                    SUM(CASE WHEN amount < 0 AND is_internal_transfer = 1 THEN ABS(amount) ELSE 0 END) as internal_out,
                    COUNT(CASE WHEN is_internal_transfer = 1 THEN 1 END) as internal_count
                FROM corporation_wallet_journals
                WHERE corporation_id = ?
                AND date >= ?
                GROUP BY DATE(date)
            ) AS transfers ON ds.corporation_id = transfers.corporation_id 
                           AND ds.date = transfers.day
            SET 
                ds.internal_transfers_in = transfers.internal_in,
                ds.internal_transfers_out = transfers.internal_out,
                ds.internal_transfer_count = transfers.internal_count
            WHERE ds.corporation_id = ?
        ", [$corporationId, $startDate, $corporationId]);

        $this->output->writeln(" ✓");
    }

    /**
     * Display corporation results
     */
    private function displayCorporationResults($corpName, $stats)
    {
        $percentage = $stats['processed'] > 0 
            ? round(($stats['internal'] / $stats['processed']) * 100, 2) 
            : 0;

        $this->info("  ✅ Processed {$stats['processed']} transactions");
        $this->info("  📍 Found {$stats['internal']} internal transfers ({$percentage}%)");
        
        if (!empty($stats['by_type'])) {
            $this->info("  📊 By Category:");
            foreach ($stats['by_type'] as $type => $count) {
                $typePercentage = round(($count / $stats['internal']) * 100, 1);
                $this->line("     • " . str_replace('_', ' ', ucfirst($type)) . ": {$count} ({$typePercentage}%)");
            }
        }
    }

    /**
     * Display final summary
     */
    private function displaySummary($stats)
    {
        $this->info("\n═══════════════════════════════════════════════════════════");
        $this->info(" 📊 FINAL SUMMARY");
        $this->info("═══════════════════════════════════════════════════════════");
        
        $this->info(" Corporations Processed: {$stats['corporations']}");
        $this->info(" Total Transactions: " . number_format($stats['transactions']));
        $this->info(" Internal Transfers: " . number_format($stats['internal_transfers']));
        
        if ($stats['transactions'] > 0) {
            $percentage = round(($stats['internal_transfers'] / $stats['transactions']) * 100, 2);
            $this->info(" Overall Percentage: {$percentage}%");
        }
        
        if (!empty($stats['by_type'])) {
            $this->info("\n 📈 Breakdown by Type:");
            arsort($stats['by_type']);
            foreach ($stats['by_type'] as $type => $count) {
                $this->line("   • " . str_replace('_', ' ', ucfirst($type)) . ": " . number_format($count));
            }
        }
        
        $this->info("\n✨ Process completed successfully!");
    }
}
