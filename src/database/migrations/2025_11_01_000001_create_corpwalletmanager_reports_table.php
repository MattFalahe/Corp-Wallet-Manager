<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCorpwalletmanagerReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('corpwalletmanager_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('corporation_id')->index();
            $table->string('report_type', 50); // executive, financial, division, custom
            $table->date('date_from');
            $table->date('date_to');
            $table->longText('data'); // JSON data
            $table->timestamps();
            
            $table->index(['corporation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('corpwalletmanager_reports');
    }
}
