<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-character, per-month precomputed aggregate of
 * `character_wallet_journals` that backs the My Personal Wallet tab.
 *
 * The hourly job (corpwalletmanager:compute-personal-wallet-aggregates)
 * populates one row per (character_id, period). On a tab open the
 * controller now does a single small lookup against this table per
 * character the viewer owns, rather than scanning the raw journal live.
 *
 * Forward-only with a Schema::hasTable guard so re-running the
 * migration set on a partially upgraded install is safe.
 */
class CreateCorpwalletmanagerPersonalWalletAggregates extends Migration
{
    public function up()
    {
        if (Schema::hasTable('corpwalletmanager_personal_wallet_aggregates')) {
            return;
        }

        Schema::create('corpwalletmanager_personal_wallet_aggregates', function (Blueprint $table) {
            $table->bigIncrements('id');

            // SeAT character_id is the source of truth. User resolution
            // happens at read time via refresh_tokens so a character that
            // changes accounts (sale, transfer) does not orphan rows.
            $table->bigInteger('character_id');

            // YYYY-MM, matches the format used elsewhere in CWM.
            $table->string('period', 7);

            // Per-character per-period totals.
            $table->decimal('income_total', 22, 2)->default(0);
            $table->decimal('expense_total', 22, 2)->default(0);
            $table->decimal('net_flow', 22, 2)->default(0);
            $table->unsignedInteger('transaction_count')->default(0);

            // End-of-month running balance (read from journal.balance on
            // the last row of the month). Drives the 6-month sparkline.
            $table->decimal('end_of_month_balance', 22, 2)->nullable();

            // Pre-sorted top-N JSON arrays. Storing as JSON keeps the
            // schema simple and the rows < 16KB even for chatty traders.
            // top_income_ref_types: [{ref_type, label, amount, count}, ...top 5]
            // top_expense_ref_types: same
            // top_income_transactions: [{date, ref_type, label, amount, description}, ...top 5]
            // top_expense_transactions: same
            $table->json('top_income_ref_types')->nullable();
            $table->json('top_expense_ref_types')->nullable();
            $table->json('top_income_transactions')->nullable();
            $table->json('top_expense_transactions')->nullable();

            // Watermark for incremental updates. Last
            // `character_wallet_journals.id` seen for this (character,
            // period). The hourly job only rescans rows newer than this
            // for the current period; prior periods only get re-scanned
            // when newer journal rows for them appear (which can happen
            // after a late SeAT sync).
            $table->unsignedBigInteger('last_journal_id_seen')->default(0);

            $table->timestamps();

            $table->unique(['character_id', 'period'], 'cwm_pwa_unique');
            $table->index(['character_id', 'period'], 'cwm_pwa_char_period');
        });
    }

    public function down()
    {
        Schema::dropIfExists('corpwalletmanager_personal_wallet_aggregates');
    }
}
