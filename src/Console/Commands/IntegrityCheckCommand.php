<?php

namespace CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use CorpWalletManager\Models\MonthlyBalance;
use CorpWalletManager\Models\DivisionBalance;
use CorpWalletManager\Models\Prediction;
use CorpWalletManager\Models\DivisionPrediction;
use CorpWalletManager\Models\Settings;
use CorpWalletManager\Models\RecalcLog;

class IntegrityCheckCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'corpwalletmanager:integrity-check 
                            {--fix : Automatically fix issues found}
                            {--detailed : Show detailed information and statistics}
                            {--table= : Check specific table only}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and fix data integrity issues in Corp Wallet Manager tables';

    protected $errors = [];
    protected $warnings = [];
    protected $fixed = [];
    protected $stats = [];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  Corp Wallet Manager - Integrity Check');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        $autoFix = $this->option('fix');
        $detailed = $this->option('detailed');
        $specificTable = $this->option('table');

        if ($autoFix) {
            $this->warn('⚠️  Auto-fix mode enabled - issues will be corrected automatically');
            if (!$this->confirm('Are you sure you want to proceed?')) {
                $this->info('Integrity check cancelled.');
                return 0;
            }
            $this->newLine();
        }

        // Run checks
        $this->line('Starting integrity checks...');
        $this->newLine();

        if (!$specificTable || $specificTable === 'structure') {
            $this->checkTableStructure();
        }

        if (!$specificTable || $specificTable === 'monthly_balances') {
            $this->checkMonthlyBalances($autoFix, $detailed);
        }

        if (!$specificTable || $specificTable === 'division_balances') {
            $this->checkDivisionBalances($autoFix, $detailed);
        }

        if (!$specificTable || $specificTable === 'predictions') {
            $this->checkPredictions($autoFix, $detailed);
        }

        if (!$specificTable || $specificTable === 'division_predictions') {
            $this->checkDivisionPredictions($autoFix, $detailed);
        }

        if (!$specificTable || $specificTable === 'reports') {
            $this->checkReports($autoFix, $detailed);
        }

        if (!$specificTable || $specificTable === 'settings') {
            $this->checkSettings($detailed);
        }

        if (!$specificTable || $specificTable === 'logs') {
            $this->checkRecalcLogs($autoFix, $detailed);
        }

        if (!$specificTable || $specificTable === 'orphaned') {
            $this->checkOrphanedRecords($autoFix, $detailed);
        }

        if (!$specificTable || $specificTable === 'dates') {
            $this->checkDateConsistency($detailed);
        }

        // Display summary
        $this->displaySummary();

        return 0;
    }

    protected function checkTableStructure()
    {
        $this->info('🔍 Checking table structure...');

        $requiredTables = [
            'corpwalletmanager_monthly_balances',
            'corpwalletmanager_division_balances',
            'corpwalletmanager_predictions',
            'corpwalletmanager_division_predictions',
            'corpwalletmanager_settings',
            'corpwalletmanager_recalc_logs',
            'corpwalletmanager_reports',
        ];

        foreach ($requiredTables as $table) {
            if (Schema::hasTable($table)) {
                $this->line("  ✅ Table '$table' exists");
            } else {
                $this->error("  ❌ Table '$table' is missing!");
                $this->errors[] = "Missing table: $table";
            }
        }

        $this->newLine();
    }

    protected function checkMonthlyBalances($autoFix, $detailed)
    {
        $this->info('🔍 Checking monthly balances...');

        // Check for duplicates
        $duplicates = DB::table('corpwalletmanager_monthly_balances')
            ->select('corporation_id', 'month', DB::raw('COUNT(*) as count'))
            ->groupBy('corporation_id', 'month')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->count() > 0) {
            $this->error("  ❌ Found {$duplicates->count()} duplicate entries");
            
            foreach ($duplicates as $dup) {
                $this->warn("    ⚠️  Corporation {$dup->corporation_id}, Month {$dup->month}: {$dup->count} entries");
                
                if ($autoFix) {
                    $this->fixDuplicateMonthlyBalances($dup->corporation_id, $dup->month);
                }
            }
        } else {
            $this->line('  ✅ No duplicate entries found');
        }

        // Get statistics
        $count = DB::table('corpwalletmanager_monthly_balances')->count();
        $oldest = DB::table('corpwalletmanager_monthly_balances')->min('month');
        $newest = DB::table('corpwalletmanager_monthly_balances')->max('month');
        
        $this->stats['monthly_balances'] = [
            'count' => $count,
            'oldest' => $oldest,
            'newest' => $newest,
        ];

        if ($detailed) {
            $this->line("  📊 Statistics:");
            $this->line("    Total records: $count");
            $this->line("    Date range: $oldest to $newest");
        }

        $this->newLine();
    }

    protected function checkDivisionBalances($autoFix, $detailed)
    {
        $this->info('🔍 Checking division balances...');

        // Check for duplicates
        $duplicates = DB::table('corpwalletmanager_division_balances')
            ->select('corporation_id', 'division_id', 'month', DB::raw('COUNT(*) as count'))
            ->groupBy('corporation_id', 'division_id', 'month')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->count() > 0) {
            $this->error("  ❌ Found {$duplicates->count()} duplicate entries");
            
            foreach ($duplicates as $dup) {
                $this->warn("    ⚠️  Corp {$dup->corporation_id}, Division {$dup->division_id}, Month {$dup->month}: {$dup->count} entries");
                
                if ($autoFix) {
                    $this->fixDuplicateDivisionBalances($dup->corporation_id, $dup->division_id, $dup->month);
                }
            }
        } else {
            $this->line('  ✅ No duplicate entries found');
        }

        // Get statistics
        $count = DB::table('corpwalletmanager_division_balances')->count();
        $divisionCount = DB::table('corpwalletmanager_division_balances')
            ->distinct('division_id')
            ->count('division_id');
        
        $this->stats['division_balances'] = [
            'count' => $count,
            'divisions' => $divisionCount,
        ];

        if ($detailed) {
            $this->line("  📊 Statistics:");
            $this->line("    Total records: $count");
            $this->line("    Unique divisions: $divisionCount");
        }

        $this->newLine();
    }

    protected function checkPredictions($autoFix, $detailed)
    {
        $this->info('🔍 Checking predictions...');

        // Check for duplicates
        $duplicates = DB::table('corpwalletmanager_predictions')
            ->select('corporation_id', 'date', DB::raw('COUNT(*) as count'))
            ->groupBy('corporation_id', 'date')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->count() > 0) {
            $this->error("  ❌ Found {$duplicates->count()} duplicate predictions");
            
            foreach ($duplicates as $dup) {
                $this->warn("    ⚠️  Corporation {$dup->corporation_id}, Date {$dup->date}: {$dup->count} entries");
                
                if ($autoFix) {
                    $this->fixDuplicatePredictions($dup->corporation_id, $dup->date);
                }
            }
        } else {
            $this->line('  ✅ No duplicate predictions found');
        }

        // Check for predictions without balance data
        $orphanedPredictions = DB::table('corpwalletmanager_predictions as p')
            ->leftJoin('corpwalletmanager_monthly_balances as mb', function($join) {
                $join->on('p.corporation_id', '=', 'mb.corporation_id')
                     ->whereRaw('YEAR(p.date) = YEAR(STR_TO_DATE(CONCAT(mb.month, "-01"), "%Y-%m-%d"))')
                     ->whereRaw('MONTH(p.date) = MONTH(STR_TO_DATE(CONCAT(mb.month, "-01"), "%Y-%m-%d"))');
            })
            ->whereNull('mb.id')
            ->count();

        if ($orphanedPredictions > 0) {
            $this->warn("  ⚠️  Found $orphanedPredictions predictions without corresponding balance data");
            $this->warnings[] = "$orphanedPredictions predictions lack balance data";
        } else {
            $this->line('  ✅ All predictions have corresponding balance data');
        }

        // Get statistics
        $count = DB::table('corpwalletmanager_predictions')->count();
        $futureCount = DB::table('corpwalletmanager_predictions')
            ->where('date', '>', now())
            ->count();
        
        $this->stats['predictions'] = [
            'count' => $count,
            'future' => $futureCount,
        ];

        if ($detailed) {
            $this->line("  📊 Statistics:");
            $this->line("    Total predictions: $count");
            $this->line("    Future predictions: $futureCount");
        }

        $this->newLine();
    }

    protected function checkDivisionPredictions($autoFix, $detailed)
    {
        $this->info('🔍 Checking division predictions...');

        // Check for duplicates
        $duplicates = DB::table('corpwalletmanager_division_predictions')
            ->select('corporation_id', 'division_id', 'date', DB::raw('COUNT(*) as count'))
            ->groupBy('corporation_id', 'division_id', 'date')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->count() > 0) {
            $this->error("  ❌ Found {$duplicates->count()} duplicate division predictions");
            
            foreach ($duplicates as $dup) {
                $this->warn("    ⚠️  Corp {$dup->corporation_id}, Div {$dup->division_id}, Date {$dup->date}: {$dup->count} entries");
                
                if ($autoFix) {
                    $this->fixDuplicateDivisionPredictions($dup->corporation_id, $dup->division_id, $dup->date);
                }
            }
        } else {
            $this->line('  ✅ No duplicate division predictions found');
        }

        // Get statistics
        $count = DB::table('corpwalletmanager_division_predictions')->count();
        
        $this->stats['division_predictions'] = [
            'count' => $count,
        ];

        if ($detailed) {
            $this->line("  📊 Statistics:");
            $this->line("    Total records: $count");
        }

        $this->newLine();
    }

    protected function checkReports($autoFix, $detailed)
    {
        $this->info('🔍 Checking reports...');

        if (!Schema::hasTable('corpwalletmanager_reports')) {
            $this->warn('  ⚠️  Reports table does not exist (may be normal for older versions)');
            $this->newLine();
            return;
        }

        // Check for duplicates
        $duplicates = DB::table('corpwalletmanager_reports')
            ->select('corporation_id', 'report_type', 'created_at', DB::raw('COUNT(*) as count'))
            ->groupBy('corporation_id', 'report_type', 'created_at')
            ->having('count', '>', 1)
            ->get();

        if ($duplicates->count() > 0) {
            $this->error("  ❌ Found {$duplicates->count()} duplicate reports");
            
            foreach ($duplicates as $dup) {
                $this->warn("    ⚠️  Corp {$dup->corporation_id}, Type {$dup->report_type}, Created {$dup->created_at}: {$dup->count} entries");
                
                if ($autoFix) {
                    $this->fixDuplicateReports($dup->corporation_id, $dup->report_type, $dup->created_at);
                }
            }
        } else {
            $this->line('  ✅ No duplicate reports found');
        }

        // Get statistics
        $count = DB::table('corpwalletmanager_reports')->count();
        
        $this->stats['reports'] = [
            'count' => $count,
        ];

        if ($detailed) {
            $this->line("  📊 Statistics:");
            $this->line("    Total reports: $count");
        }

        $this->newLine();
    }

    protected function checkSettings($detailed)
    {
        $this->info('🔍 Checking settings...');

        $settingsCount = DB::table('corpwalletmanager_settings')->count();
        
        if ($settingsCount === 0) {
            $this->warn('  ⚠️  No settings found - plugin may need initialization');
            $this->warnings[] = 'Settings table is empty';
        } else {
            $this->line("  ✅ Found $settingsCount settings");
        }

        // Check if selected corporation exists
        $selectedCorp = Settings::getSetting('selected_corporation_id');
        if ($selectedCorp) {
            $corpExists = DB::table('corporation_infos')->where('corporation_id', $selectedCorp)->exists();
            if (!$corpExists) {
                $this->error("  ❌ Selected corporation ($selectedCorp) does not exist in database!");
                $this->errors[] = "Invalid corporation ID in settings: $selectedCorp";
            } else {
                $this->line("  ✅ Selected corporation ($selectedCorp) is valid");
            }
        }

        if ($detailed) {
            $this->line("  📊 Settings:");
            $settings = DB::table('corpwalletmanager_settings')->get();
            foreach ($settings as $setting) {
                $this->line("    {$setting->key}: {$setting->value}");
            }
        }

        $this->newLine();
    }

    protected function checkRecalcLogs($autoFix, $detailed)
    {
        $this->info('🔍 Checking recalculation logs...');

        // Check for stuck jobs (running for > 1 hour)
        $stuckJobs = DB::table('corpwalletmanager_recalc_logs')
            ->where('status', RecalcLog::STATUS_RUNNING)
            ->where('started_at', '<', now()->subHour())
            ->get();

        if ($stuckJobs->count() > 0) {
            $this->error("  ❌ Found {$stuckJobs->count()} stuck jobs (running for > 1 hour)");
            
            foreach ($stuckJobs as $job) {
                $duration = now()->diffInMinutes($job->started_at);
                $this->warn("    ⚠️  Job #{$job->id} ({$job->job_type}) running for {$duration} minutes");
                
                if ($autoFix) {
                    DB::table('corpwalletmanager_recalc_logs')
                        ->where('id', $job->id)
                        ->update([
                            'status' => RecalcLog::STATUS_FAILED,
                            'completed_at' => now(),
                            'error_message' => 'Marked as failed by integrity check - job was stuck',
                        ]);
                    $this->fixed[] = "Marked stuck job #{$job->id} as failed";
                }
            }
        } else {
            $this->line('  ✅ No stuck jobs found');
        }

        // Clean up old logs (optional)
        $oldLogs = DB::table('corpwalletmanager_recalc_logs')
            ->where('created_at', '<', now()->subMonths(3))
            ->count();

        if ($oldLogs > 100) {
            $this->warn("  ⚠️  Found $oldLogs old log entries (>3 months)");
            $this->warnings[] = "$oldLogs old log entries could be cleaned up";
        }

        // Get statistics
        $total = DB::table('corpwalletmanager_recalc_logs')->count();
        $completed = DB::table('corpwalletmanager_recalc_logs')->where('status', RecalcLog::STATUS_COMPLETED)->count();
        $failed = DB::table('corpwalletmanager_recalc_logs')->where('status', RecalcLog::STATUS_FAILED)->count();
        
        $this->stats['logs'] = [
            'total' => $total,
            'completed' => $completed,
            'failed' => $failed,
        ];

        if ($detailed) {
            $this->line("  📊 Log Statistics:");
            $this->line("    Total: $total");
            $this->line("    Completed: $completed");
            $this->line("    Failed: $failed");
        }

        $this->newLine();
    }

    protected function checkOrphanedRecords($autoFix, $detailed)
    {
        $this->info('🔍 Checking for orphaned records...');

        // Check for balances with non-existent corporations
        $orphanedBalances = DB::table('corpwalletmanager_monthly_balances as mb')
            ->leftJoin('corporation_infos as c', 'mb.corporation_id', '=', 'c.corporation_id')
            ->whereNull('c.corporation_id')
            ->count();

        if ($orphanedBalances > 0) {
            $this->warn("  ⚠️  Found $orphanedBalances monthly balance records for non-existent corporations");
            $this->warnings[] = "$orphanedBalances orphaned monthly balance records";
        } else {
            $this->line('  ✅ No orphaned monthly balances');
        }

        // Check for division balances with non-existent corporations
        $orphanedDivBalances = DB::table('corpwalletmanager_division_balances as db')
            ->leftJoin('corporation_infos as c', 'db.corporation_id', '=', 'c.corporation_id')
            ->whereNull('c.corporation_id')
            ->count();

        if ($orphanedDivBalances > 0) {
            $this->warn("  ⚠️  Found $orphanedDivBalances division balance records for non-existent corporations");
            $this->warnings[] = "$orphanedDivBalances orphaned division balance records";
        } else {
            $this->line('  ✅ No orphaned division balances');
        }

        $this->newLine();
    }

    protected function checkDateConsistency($detailed)
    {
        $this->info('🔍 Checking date consistency...');

        // Check for future-dated balances (shouldn't happen)
        $futureBalances = DB::table('corpwalletmanager_monthly_balances')
            ->where('month', '>', now()->format('Y-m'))
            ->count();

        if ($futureBalances > 0) {
            $this->warn("  ⚠️  Found $futureBalances balance records with future dates");
            $this->warnings[] = "$futureBalances future-dated balances";
        } else {
            $this->line('  ✅ No future-dated balances');
        }

        // Check for very old predictions (> 1 year old)
        $oldPredictions = DB::table('corpwalletmanager_predictions')
            ->where('date', '<', now()->subYear())
            ->count();

        if ($oldPredictions > 50) {
            $this->warn("  ⚠️  Found $oldPredictions predictions older than 1 year");
            $this->warnings[] = "$oldPredictions old predictions could be cleaned up";
        }

        $this->newLine();
    }

    protected function fixDuplicateMonthlyBalances($corporationId, $month)
    {
        // Keep the most recent record, delete others
        $records = DB::table('corpwalletmanager_monthly_balances')
            ->where('corporation_id', $corporationId)
            ->where('month', $month)
            ->orderBy('updated_at', 'desc')
            ->get();

        $keepId = $records->first()->id;
        
        DB::table('corpwalletmanager_monthly_balances')
            ->where('corporation_id', $corporationId)
            ->where('month', $month)
            ->where('id', '!=', $keepId)
            ->delete();

        $deletedCount = $records->count() - 1;
        $this->fixed[] = "Removed $deletedCount duplicate monthly balance(s) for corp $corporationId, month $month";
        $this->line("    ✅ Fixed: Kept most recent record, deleted $deletedCount duplicate(s)");
    }

    protected function fixDuplicateDivisionBalances($corporationId, $divisionId, $month)
    {
        $records = DB::table('corpwalletmanager_division_balances')
            ->where('corporation_id', $corporationId)
            ->where('division_id', $divisionId)
            ->where('month', $month)
            ->orderBy('updated_at', 'desc')
            ->get();

        $keepId = $records->first()->id;
        
        DB::table('corpwalletmanager_division_balances')
            ->where('corporation_id', $corporationId)
            ->where('division_id', $divisionId)
            ->where('month', $month)
            ->where('id', '!=', $keepId)
            ->delete();

        $deletedCount = $records->count() - 1;
        $this->fixed[] = "Removed $deletedCount duplicate division balance(s)";
        $this->line("    ✅ Fixed: Deleted $deletedCount duplicate(s)");
    }

    protected function fixDuplicatePredictions($corporationId, $predictionDate)
    {
        $records = DB::table('corpwalletmanager_predictions')
            ->where('corporation_id', $corporationId)
            ->where('date', $predictionDate)
            ->orderBy('created_at', 'desc')
            ->get();

        $keepId = $records->first()->id;
        
        DB::table('corpwalletmanager_predictions')
            ->where('corporation_id', $corporationId)
            ->where('date', $predictionDate)
            ->where('id', '!=', $keepId)
            ->delete();

        $deletedCount = $records->count() - 1;
        $this->fixed[] = "Removed $deletedCount duplicate prediction(s)";
        $this->line("    ✅ Fixed: Deleted $deletedCount duplicate(s)");
    }

    protected function fixDuplicateDivisionPredictions($corporationId, $divisionId, $predictionDate)
    {
        $records = DB::table('corpwalletmanager_division_predictions')
            ->where('corporation_id', $corporationId)
            ->where('division_id', $divisionId)
            ->where('date', $predictionDate)
            ->orderBy('created_at', 'desc')
            ->get();

        $keepId = $records->first()->id;
        
        DB::table('corpwalletmanager_division_predictions')
            ->where('corporation_id', $corporationId)
            ->where('division_id', $divisionId)
            ->where('date', $predictionDate)
            ->where('id', '!=', $keepId)
            ->delete();

        $deletedCount = $records->count() - 1;
        $this->fixed[] = "Removed $deletedCount duplicate division prediction(s)";
        $this->line("    ✅ Fixed: Deleted $deletedCount duplicate(s)");
    }

    protected function fixDuplicateReports($corporationId, $reportType, $createdAt)
    {
        $records = DB::table('corpwalletmanager_reports')
            ->where('corporation_id', $corporationId)
            ->where('report_type', $reportType)
            ->where('created_at', $createdAt)
            ->orderBy('id', 'desc')
            ->get();

        $keepId = $records->first()->id;
        
        DB::table('corpwalletmanager_reports')
            ->where('corporation_id', $corporationId)
            ->where('report_type', $reportType)
            ->where('created_at', $createdAt)
            ->where('id', '!=', $keepId)
            ->delete();

        $deletedCount = $records->count() - 1;
        $this->fixed[] = "Removed $deletedCount duplicate report(s)";
        $this->line("    ✅ Fixed: Deleted $deletedCount duplicate(s)");
    }

    protected function displaySummary()
    {
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('  Summary');
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        // Display statistics
        if (!empty($this->stats)) {
            $this->line('📊 Database Statistics:');
            foreach ($this->stats as $table => $data) {
                $this->line("  $table:");
                foreach ($data as $key => $value) {
                    $this->line("    $key: $value");
                }
            }
            $this->newLine();
        }

        // Display errors
        if (!empty($this->errors)) {
            $this->error("❌ Errors Found: " . count($this->errors));
            foreach ($this->errors as $error) {
                $this->error("  • $error");
            }
            $this->newLine();
        } else {
            $this->line('✅ No critical errors found');
            $this->newLine();
        }

        // Display warnings
        if (!empty($this->warnings)) {
            $this->warn("⚠️  Warnings: " . count($this->warnings));
            foreach ($this->warnings as $warning) {
                $this->warn("  • $warning");
            }
            $this->newLine();
        } else {
            $this->line('✅ No warnings');
            $this->newLine();
        }

        // Display fixes
        if (!empty($this->fixed)) {
            $this->info("🔧 Issues Fixed: " . count($this->fixed));
            foreach ($this->fixed as $fix) {
                $this->info("  • $fix");
            }
            $this->newLine();
        }

        // Overall status
        if (empty($this->errors) && empty($this->warnings)) {
            $this->info('🎉 All checks passed! Database is in good health.');
        } elseif (empty($this->errors)) {
            $this->warn('⚠️  Database is functional but has some warnings.');
        } else {
            $this->error('❌ Database has issues that need attention.');
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
    }
}
