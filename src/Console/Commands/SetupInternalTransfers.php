<?php

namespace Seat\CorpWalletManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SetupInternalTransfers extends Command
{
    protected $signature = 'corpwalletmanager:setup-internal-transfers 
                            {--corporation= : Specific corporation ID}
                            {--days=30 : Number of days to process}';

    protected $description = 'Initialize internal transfer tracking system';

    public function handle()
    {
        $this->info('Setting up internal transfer tracking...');
        
        // Ensure tables exist
        if (!DB::getSchemaBuilder()->hasTable('corpwalletmanager_journal_metadata')) {
            $this->error('Required tables not found. Please run migrations first.');
            return 1;
        }

        $corporationId = $this->option('corporation');
        $days = $this->option('days');
        
        // Find and mark obvious internal transfers
        $this->detectObviousInternalTransfers($corporationId, $days);
        
        $this->info('Internal transfer setup complete!');
        return 0;
    }

    private function detectObviousInternalTransfers($corporationId, $days)
    {
        $startDate = Carbon::now()->subDays($days);
        
        // Find corporation_account_withdrawal pairs
        $query = DB::table('corporation_wallet_journals')
            ->where('ref_type', 'corporation_account_withdrawal')
            ->where('date', '>=', $startDate);
        
        if ($corporationId) {
            $query->where('corporation_id', $corporationId);
        }
        
        $withdrawals = $query->get();
        $matched = 0;
        
        foreach ($withdrawals as $withdrawal) {
            // Look for matching deposit
            $match = DB::table('corporation_wallet_journals')
                ->where('corporation_id', $withdrawal->corporation_id)
                ->where('id', '!=', $withdrawal->id)
                ->whereRaw('ABS(amount) = ?', [abs($withdrawal->amount)])
                ->where('amount', $withdrawal->amount > 0 ? '<' : '>', 0)
                ->whereBetween('date', [
                    Carbon::parse($withdrawal->date)->subMinute(),
                    Carbon::parse($withdrawal->date)->addMinute()
                ])
                ->first();
            
            if ($match) {
                // Mark both as internal
                DB::table('corpwalletmanager_journal_metadata')->updateOrInsert(
                    ['journal_id' => $withdrawal->id],
                    [
                        'corporation_id' => $withdrawal->corporation_id,
                        'is_internal_transfer' => true,
                        'internal_transfer_category' => 'division_transfer',
                        'matched_transfer_id' => $match->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
                
                DB::table('corpwalletmanager_journal_metadata')->updateOrInsert(
                    ['journal_id' => $match->id],
                    [
                        'corporation_id' => $match->corporation_id,
                        'is_internal_transfer' => true,
                        'internal_transfer_category' => 'division_transfer',
                        'matched_transfer_id' => $withdrawal->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
                
                $matched++;
            }
        }
        
        $this->info("Found and marked {$matched} internal transfer pairs");
    }
}
