<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // Add access logs table
        Schema::create('corpwalletmanager_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('corporation_id')->nullable();
            $table->string('view_type', 50); // 'member', 'director', 'settings'
            $table->timestamp('accessed_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'accessed_at'], 'cwm_access_user_time_idx');
            $table->index(['corporation_id', 'accessed_at'], 'cwm_access_corp_time_idx');
            $table->index('view_type', 'cwm_access_view_idx');
        });
        
        // Add default member view settings
        $defaultSettings = [
            // Section visibility
            'member_show_health' => '1',
            'member_show_trends' => '1',
            'member_show_activity' => '1',
            'member_show_goals' => '1',
            'member_show_milestones' => '1',
            'member_show_balance' => '1', // Show actual ISK values
            'member_show_performance' => '1',
            
            // Data settings
            'member_data_delay' => '0', // 0 = realtime, 24 = 24hr delay, etc
            
            // Goal targets
            'goal_savings_target' => '1000000000', // 1B ISK default
            'goal_activity_target' => '1000', // 1000 transactions
            'goal_growth_target' => '10', // 10% growth
        ];
        
        foreach ($defaultSettings as $key => $value) {
            DB::table('corpwalletmanager_settings')->insert([
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('corpwalletmanager_access_logs');
        
        // Remove member settings
        DB::table('corpwalletmanager_settings')
            ->whereIn('key', [
                'member_show_health',
                'member_show_trends',
                'member_show_activity',
                'member_show_goals',
                'member_show_milestones',
                'member_show_balance',
                'member_show_performance',
                'member_data_delay',
                'goal_savings_target',
                'goal_activity_target',
                'goal_growth_target',
            ])
            ->delete();
    }
};
