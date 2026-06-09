<?php

use Illuminate\Database\Migrations\Migration;

// Intentionally a no-op. Permissions are registered via the service provider's
// registerPermissions() call; SeAT's AccessController lazily firstOrCreate's
// Permission rows on role assignment. The original up() duplicated that work,
// and the original down() did a destructive whereIn delete. This file is
// retained so Laravel's migrations table stays consistent on already-released
// installs.
return new class extends Migration
{
    public function up() {}

    public function down() {}
};
