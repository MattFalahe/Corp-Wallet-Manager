<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('corpwalletmanager_recalc_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_type');
            $table->unsignedBigInteger('corporation_id')->nullable();
            $table->enum('status', ['running', 'completed', 'failed']);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('records_processed')->default(0);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['job_type', 'corporation_id', 'status']);
            $table->index(['status', 'started_at']);
            $table->index('corporation_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('corpwalletmanager_recalc_logs');
    }
};
