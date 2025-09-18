<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Seat\Web\Models\Acl\Permission;

return new class extends Migration
{
    /**
     * The permissions to insert
     */
    private $permissions = [
        'corpwalletmanager.view',
        'corpwalletmanager.director_view',
        'corpwalletmanager.member_view',
        'corpwalletmanager.settings',
    ];

    /**
     * Run the migrations.
     */
    public function up()
    {
        // Create permissions in the database
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate([
                'title' => $permission
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Remove our permissions
        Permission::whereIn('title', $this->permissions)->delete();
    }
};
