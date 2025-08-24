<?php
// 2025_08_01_000003_create_division_balances_table.php
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
            
            $table->index(['corporation_id', 'division_id', 'month']);
            $table->unique(['corporation_id', 'division_id', 'month']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('corpwalletmanager_division_balances');
    }
};
