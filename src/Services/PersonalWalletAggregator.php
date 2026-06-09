<?php

namespace CorpWalletManager\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Builds per-character per-month aggregates of
 * `character_wallet_journals` for the My Personal Wallet tab.
 *
 * The hourly job (corpwalletmanager:compute-personal-wallet-aggregates)
 * runs aggregateForCharacter() for every character SeAT knows about.
 * The aggregator is incremental for prior periods (watermark-driven
 * via last_journal_id_seen) but always recomputes the current month
 * because the cost there is bounded by the month's journal volume.
 *
 * On a read the controller does a single small lookup against
 * `corpwalletmanager_personal_wallet_aggregates` per (character,
 * period) rather than scanning the raw journal live.
 */
class PersonalWalletAggregator
{
    /** Aggregate table the job writes and the read endpoint queries. */
    private const TABLE = 'corpwalletmanager_personal_wallet_aggregates';

    /**
     * Human-readable label for a SeAT `ref_type` slug. Mirrors the
     * controller's refTypeLabel so the JSON we write here matches
     * what the live endpoint used to compute on the fly.
     */
    private function refTypeLabel(string $refType): string
    {
        $special = [
            'bounty_prizes'                  => 'Bounty Prizes',
            'agent_mission_reward'           => 'Mission Reward',
            'agent_mission_time_bonus_reward' => 'Mission Time Bonus',
            'agent_mission_collateral_paid'  => 'Mission Collateral Paid',
            'agent_mission_collateral_refunded' => 'Mission Collateral Refunded',
            'corporation_account_withdrawal' => 'Corp Withdrawal',
            'player_donation'                => 'Player Donation',
            'player_trading'                 => 'Player Trade',
            'market_transaction'             => 'Market Transaction',
            'market_escrow'                  => 'Market Escrow',
            'broker_reimbursement'           => 'Broker Reimbursement',
            'transaction_tax'                => 'Sales Tax',
            'brokers_fee'                    => 'Broker Fee',
            'contract_price'                 => 'Contract Price',
            'contract_price_payment_corp'    => 'Contract Price (Corp)',
            'contract_reward'                => 'Contract Reward',
            'contract_collateral'            => 'Contract Collateral',
            'contract_deposit'               => 'Contract Deposit',
            'contract_deposit_refund'        => 'Contract Deposit Refund',
            'contract_brokers_fee'           => 'Contract Broker Fee',
            'office_rental_fee'              => 'Office Rental Fee',
            'jump_clone_installation_fee'    => 'Jump Clone Install Fee',
            'jump_clone_activation_fee'      => 'Jump Clone Activation Fee',
            'industry_job_tax'               => 'Industry Tax',
            'manufacturing'                  => 'Manufacturing',
            'researching_time_productivity'  => 'Research (Time)',
            'researching_material_productivity' => 'Research (Material)',
            'copying'                        => 'Copying',
            'invention'                      => 'Invention',
            'reaction'                       => 'Reaction',
            'reprocessing_tax'               => 'Reprocessing Tax',
            'docking_fee'                    => 'Docking Fee',
            'project_discovery_reward'       => 'Project Discovery',
            'ess_escrow_transfer'            => 'ESS Escrow',
            'planetary_construction'         => 'Planetary Construction',
            'planetary_export_tax'           => 'Planetary Export Tax',
            'planetary_import_tax'           => 'Planetary Import Tax',
        ];
        if (isset($special[$refType])) {
            return $special[$refType];
        }
        if ($refType === '') {
            return 'Unknown';
        }
        return ucwords(str_replace('_', ' ', $refType));
    }

    /**
     * Recompute aggregates for one character.
     *
     * With $backfillMonths null: refresh the current month plus any
     * prior month that has new journal rows past its stored
     * watermark. That's the hourly steady-state cost.
     *
     * With $backfillMonths set: rebuild the trailing N months
     * unconditionally (the operator-run command after upgrading).
     *
     * @return int  number of (character, period) rows written
     */
    public function aggregateForCharacter(int $characterId, ?int $backfillMonths = null): int
    {
        if ($characterId <= 0) {
            return 0;
        }

        if ($backfillMonths !== null && $backfillMonths > 0) {
            $periods = [];
            for ($i = 0; $i < $backfillMonths; $i++) {
                $periods[] = Carbon::now()->subMonthsNoOverflow($i)->format('Y-m');
            }
        } else {
            $periods = $this->periodsToRecompute($characterId);
        }

        $written = 0;
        foreach ($periods as $period) {
            if ($this->aggregateOnePeriod($characterId, $period)) {
                $written++;
            }
        }
        return $written;
    }

    /**
     * Recompute aggregates for one (character, period) tuple.
     * Idempotent (upsert by the cwm_pwa_unique key).
     */
    public function aggregateOnePeriod(int $characterId, string $period): bool
    {
        if ($characterId <= 0) {
            return false;
        }
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            return false;
        }

        [$yearStr, $monthStr] = explode('-', $period);
        $periodStart = Carbon::createFromDate((int) $yearStr, (int) $monthStr, 1)->startOfMonth();
        $periodEnd   = $periodStart->copy()->endOfMonth();

        // 1) Period totals + transaction count.
        $totals = DB::table('character_wallet_journals')
            ->where('character_id', $characterId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS income_total, ' .
                'COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END), 0) AS expense_total, ' .
                'COUNT(*) AS txn_count, ' .
                'COALESCE(MAX(id), 0) AS max_id'
            )
            ->first();

        $incomeTotal  = (float) ($totals->income_total ?? 0);
        $expenseTotal = (float) ($totals->expense_total ?? 0);
        $txnCount     = (int) ($totals->txn_count ?? 0);
        $maxId        = (int) ($totals->max_id ?? 0);
        $netFlow      = $incomeTotal - $expenseTotal;

        // 2) Top 5 income ref_types for this character / period.
        $topIncomeRefRows = DB::table('character_wallet_journals')
            ->where('character_id', $characterId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('amount', '>', 0)
            ->groupBy('ref_type')
            ->selectRaw('ref_type, SUM(amount) AS amount, COUNT(*) AS cnt')
            ->orderByDesc('amount')
            ->limit(5)
            ->get();
        $topIncomeRefTypes = [];
        foreach ($topIncomeRefRows as $r) {
            $rt = (string) ($r->ref_type ?? '');
            $topIncomeRefTypes[] = [
                'ref_type' => $rt,
                'label'    => $this->refTypeLabel($rt),
                'amount'   => (float) $r->amount,
                'count'    => (int) $r->cnt,
            ];
        }

        // 3) Top 5 expense ref_types for this character / period.
        $topExpenseRefRows = DB::table('character_wallet_journals')
            ->where('character_id', $characterId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('amount', '<', 0)
            ->groupBy('ref_type')
            ->selectRaw('ref_type, SUM(-amount) AS amount, COUNT(*) AS cnt')
            ->orderByDesc('amount')
            ->limit(5)
            ->get();
        $topExpenseRefTypes = [];
        foreach ($topExpenseRefRows as $r) {
            $rt = (string) ($r->ref_type ?? '');
            $topExpenseRefTypes[] = [
                'ref_type' => $rt,
                'label'    => $this->refTypeLabel($rt),
                'amount'   => (float) $r->amount,
                'count'    => (int) $r->cnt,
            ];
        }

        // 4) Top 5 biggest income transactions for this character / period.
        $topIncomeTxnRows = DB::table('character_wallet_journals')
            ->where('character_id', $characterId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('amount', '>', 0)
            ->orderByDesc('amount')
            ->limit(5)
            ->get(['ref_type', 'amount', 'description', 'reason', 'date']);
        $topIncomeTransactions = [];
        foreach ($topIncomeTxnRows as $r) {
            $rt = (string) ($r->ref_type ?? '');
            $topIncomeTransactions[] = [
                'date'           => (string) (Carbon::parse($r->date)->format('Y-m-d H:i')),
                'character_id'   => $characterId,
                'ref_type'       => $rt,
                'ref_type_label' => $this->refTypeLabel($rt),
                'amount'         => (float) $r->amount,
                'description'    => (string) ($r->description ?? ''),
                // Player-typed memo on donations etc. Distinct from CCP's
                // auto-generated description — this is what the human wrote
                // in the "Reason" field when making the transfer.
                'reason'         => (string) ($r->reason ?? ''),
            ];
        }

        // 5) Top 5 biggest expense transactions for this character / period.
        // (smallest negative = largest expense, hence orderBy ASC)
        $topExpenseTxnRows = DB::table('character_wallet_journals')
            ->where('character_id', $characterId)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->where('amount', '<', 0)
            ->orderBy('amount', 'asc')
            ->limit(5)
            ->get(['ref_type', 'amount', 'description', 'reason', 'date']);
        $topExpenseTransactions = [];
        foreach ($topExpenseTxnRows as $r) {
            $rt = (string) ($r->ref_type ?? '');
            $topExpenseTransactions[] = [
                'date'           => (string) (Carbon::parse($r->date)->format('Y-m-d H:i')),
                'character_id'   => $characterId,
                'ref_type'       => $rt,
                'ref_type_label' => $this->refTypeLabel($rt),
                'amount'         => abs((float) $r->amount),
                'description'    => (string) ($r->description ?? ''),
                'reason'         => (string) ($r->reason ?? ''),
            ];
        }

        // 6) End-of-month running balance (the journal stores SeAT's
        // running balance AFTER each transaction). Take the last row
        // whose date is on or before the end of the period; that gives
        // us the closing balance even for prior months. Single
        // (character_id, date)-indexed lookup, one row.
        $balanceRow = DB::table('character_wallet_journals')
            ->where('character_id', $characterId)
            ->where('date', '<=', $periodEnd)
            ->orderByDesc('date')
            ->limit(1)
            ->value('balance');
        $endOfMonthBalance = $balanceRow === null ? null : (float) $balanceRow;

        $now = Carbon::now();

        // Upsert keyed on the cwm_pwa_unique index. updateOrInsert
        // handles the create + update both sides in one round-trip.
        DB::table(self::TABLE)->updateOrInsert(
            [
                'character_id' => $characterId,
                'period'       => $period,
            ],
            [
                'income_total'             => $incomeTotal,
                'expense_total'            => $expenseTotal,
                'net_flow'                 => $netFlow,
                'transaction_count'        => $txnCount,
                'end_of_month_balance'     => $endOfMonthBalance,
                'top_income_ref_types'     => json_encode($topIncomeRefTypes),
                'top_expense_ref_types'    => json_encode($topExpenseRefTypes),
                'top_income_transactions'  => json_encode($topIncomeTransactions),
                'top_expense_transactions' => json_encode($topExpenseTransactions),
                'last_journal_id_seen'     => $maxId,
                'updated_at'               => $now,
                'created_at'               => $now,
            ]
        );

        return true;
    }

    /**
     * Which periods need recomputation for this character.
     *
     * Always the current calendar month (cheap, journal rows for a
     * month are bounded). Plus any prior period whose stored
     * watermark is below the current MAX(id) of journal rows in that
     * period, i.e. SeAT delivered new history rows after we last
     * computed (back-dated ESI corrections / fresh paginated history
     * pages on a long-quiet character).
     */
    public function periodsToRecompute(int $characterId): array
    {
        $periods = [Carbon::now()->format('Y-m')];

        $rows = DB::table(self::TABLE)
            ->where('character_id', $characterId)
            ->where('period', '!=', $periods[0])
            ->get(['period', 'last_journal_id_seen']);

        if ($rows->isEmpty()) {
            return $periods;
        }

        foreach ($rows as $r) {
            $period = (string) $r->period;
            if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
                continue;
            }
            [$yearStr, $monthStr] = explode('-', $period);
            $start = Carbon::createFromDate((int) $yearStr, (int) $monthStr, 1)->startOfMonth();
            $end   = $start->copy()->endOfMonth();

            $maxId = (int) (DB::table('character_wallet_journals')
                ->where('character_id', $characterId)
                ->whereBetween('date', [$start, $end])
                ->max('id') ?? 0);

            if ($maxId > (int) $r->last_journal_id_seen) {
                $periods[] = $period;
            }
        }

        return array_values(array_unique($periods));
    }

    /**
     * Convenience: aggregate the current month for every character
     * SeAT has a non-deleted refresh token for. Used by the hourly job
     * and by the artisan command when --character is not supplied.
     *
     * @return array{characters:int,rows:int,errors:int}
     */
    public function aggregateAllCharacters(?int $backfillMonths = null): array
    {
        $characterIds = DB::table('refresh_tokens')
            ->whereNull('deleted_at')
            ->pluck('character_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id >= 90000000)
            ->unique()
            ->values();

        $rows   = 0;
        $errors = 0;
        $chars  = $characterIds->count();

        foreach ($characterIds as $characterId) {
            try {
                $rows += $this->aggregateForCharacter($characterId, $backfillMonths);
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('PersonalWalletAggregator: character failed', [
                    'character_id' => $characterId,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        return [
            'characters' => $chars,
            'rows'       => $rows,
            'errors'     => $errors,
        ];
    }
}
