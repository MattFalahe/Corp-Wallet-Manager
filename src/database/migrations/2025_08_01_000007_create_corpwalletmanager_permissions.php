<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // Check if permissions don't already exist before creating
        $permissions = [
            'corporation.wallet_view',
            'corporation.member_view'
        ];

        foreach ($permissions as $permission) {
            if (!DB::table('permissions')->where('title', $permission)->exists()) {
                DB::table('permissions')->insert([
                    'title' => $permission,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        // Remove permissions on rollback
        DB::table('permissions')
            ->whereIn('title', ['corporation.wallet_view', 'corporation.member_view'])
            ->delete();
    }
};
