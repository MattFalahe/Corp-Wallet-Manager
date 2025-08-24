<?php
// 2025_08_01_000004_create_division_predictions_table.php  
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('corpwalletmanager_division_predictions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('corporation_id');
            $table->unsignedTinyInteger('division_id');
            $table->date('date');
            $table->decimal('predicted_balance', 20, 2);
            $table->timestamps();
            
            $table->index(['corporation_id', 'division_id', 'date']);
            $table->unique(['corporation_id', 'division_id', 'date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('corpwalletmanager_division_predictions');
    }
};
