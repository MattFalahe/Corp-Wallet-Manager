<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCorpwalletmanagerCharacterContributions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('corpwalletmanager_character_contributions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('corporation_id')->index();
            $table->bigInteger('character_id')->index();
            // YYYY-MM, matches the format used elsewhere in CWM (MonthlyBalance etc.).
            $table->string('period', 7);

            // Per-bucket positive contributions (the per-character "what did
            // they put into corp wallet" story).
            $table->decimal('ratting_amount', 20, 2)->default(0);
            $table->unsignedInteger('ratting_count')->default(0);
            $table->decimal('mission_amount', 20, 2)->default(0);
            $table->unsignedInteger('mission_count')->default(0);
            $table->decimal('tax_payment_amount', 20, 2)->default(0);
            $table->unsignedInteger('tax_payment_count')->default(0);
            $table->decimal('donation_voluntary_amount', 20, 2)->default(0);
            $table->unsignedInteger('donation_voluntary_count')->default(0);

            // Outgoing (the per-character "what did the corp pay them" story).
            $table->decimal('withdrawal_amount', 20, 2)->default(0);
            $table->unsignedInteger('withdrawal_count')->default(0);

            // Denormalised sum of the four contribution buckets above so the
            // top-contributors leaderboard can ORDER BY a single column.
            $table->decimal('total_contribution_amount', 20, 2)->default(0);

            $table->timestamps();

            // One row per (corp, character, month) — drives upsert semantics
            // and prevents the cache double-counting.
            $table->unique(['corporation_id', 'character_id', 'period'], 'cwm_char_contrib_unique');
            $table->index(['corporation_id', 'period'], 'cwm_char_contrib_corp_period');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('corpwalletmanager_character_contributions');
    }
}
