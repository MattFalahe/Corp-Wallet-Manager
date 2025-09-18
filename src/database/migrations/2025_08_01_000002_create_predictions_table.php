<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('corpwalletmanager_predictions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('corporation_id')->nullable();
            $table->date('date');
            $table->decimal('predicted_balance', 20, 2);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['corporation_id', 'date']);
            $table->unique(['corporation_id', 'date']);
            $table->index('date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('corpwalletmanager_predictions');
    }
};
