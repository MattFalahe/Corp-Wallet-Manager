<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // Check if the table exists and columns don't already exist
        if (Schema::hasTable('corpwalletmanager_predictions')) {
            Schema::table('corpwalletmanager_predictions', function (Blueprint $table) {
                // Only add columns if they don't exist
                if (!Schema::hasColumn('corpwalletmanager_predictions', 'confidence')) {
                    $table->decimal('confidence', 5, 2)->default(50)->after('predicted_balance');
                }
                
                if (!Schema::hasColumn('corpwalletmanager_predictions', 'lower_bound')) {
                    $table->decimal('lower_bound', 20, 2)->nullable()->after('confidence');
                }
                
                if (!Schema::hasColumn('corpwalletmanager_predictions', 'upper_bound')) {
                    $table->decimal('upper_bound', 20, 2)->nullable()->after('lower_bound');
                }
                
                if (!Schema::hasColumn('corpwalletmanager_predictions', 'prediction_method')) {
                    $table->string('prediction_method', 50)->default('simple')->after('upper_bound');
                }
                
                if (!Schema::hasColumn('corpwalletmanager_predictions', 'metadata')) {
                $table->text('metadata')->nullable()->after('prediction_method');
            }
            });
            
            // Add new composite index if it doesn't exist
            Schema::table('corpwalletmanager_predictions', function (Blueprint $table) {
                $indexName = 'corpwalletmanager_predictions_corp_date_conf_idx';
                $existingIndexes = collect(Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('corpwalletmanager_predictions'));
                
                if (!$existingIndexes->has($indexName)) {
                    $table->index(['corporation_id', 'date', 'confidence'], $indexName);
                }
            });
        }
        
        // Create the new metrics table if it doesn't exist
        if (!Schema::hasTable('corpwalletmanager_prediction_metrics')) {
            Schema::create('corpwalletmanager_prediction_metrics', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('corporation_id')->unique();
                $table->timestamp('prediction_date');
                $table->integer('data_points_used');
                $table->decimal('average_confidence', 5, 2);
                $table->decimal('volatility_factor', 10, 4);
                $table->decimal('trend_strength', 10, 4);
                $table->timestamps();
                
                $table->index('corporation_id');
            });
        }
    }

    public function down()
    {
        // Remove the new columns
        if (Schema::hasTable('corpwalletmanager_predictions')) {
            Schema::table('corpwalletmanager_predictions', function (Blueprint $table) {
                // Drop index first
                $indexName = 'corpwalletmanager_predictions_corp_date_conf_idx';
                $existingIndexes = collect(Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('corpwalletmanager_predictions'));
                
                if ($existingIndexes->has($indexName)) {
                    $table->dropIndex($indexName);
                }
                
                // Drop columns if they exist
                $columnsToRemove = ['confidence', 'lower_bound', 'upper_bound', 'prediction_method', 'metadata'];
                foreach ($columnsToRemove as $column) {
                    if (Schema::hasColumn('corpwalletmanager_predictions', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
        
        // Drop the metrics table
        Schema::dropIfExists('corpwalletmanager_prediction_metrics');
    }
};
