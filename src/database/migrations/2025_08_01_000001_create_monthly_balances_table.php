<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('corpwalletmanager_monthly_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('corporation_id')->nullable();
            $table->string('month'); // Format: Y-m (e.g., 2025-01)
            $table->decimal('balance', 20, 2);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['corporation_id', 'month']);
            $table->unique(['corporation_id', 'month']);
            $table->index('month');
        });
    }

    public function down()
    {
        Schema::dropIfExists('corpwalletmanager_monthly_balances');
    }
};
