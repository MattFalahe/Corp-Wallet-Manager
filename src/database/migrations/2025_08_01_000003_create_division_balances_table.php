<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('corpwalletmanager_division_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('corporation_id');
            $table->unsignedTinyInteger('division_id');
            $table->string('month'); // Format: Y-m
            $table->decimal('balance', 20, 2);
            $table->timestamps();
            
            // Indexes with shorter names
            $table->index(['corporation_id', 'division_id', 'month'], 'cwm_div_bal_corp_div_month_idx');
            $table->unique(['corporation_id', 'division_id', 'month'], 'cwm_div_bal_corp_div_month_unq');
            $table->index(['corporation_id', 'month'], 'cwm_div_bal_corp_month_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('corpwalletmanager_division_balances');
    }
};
