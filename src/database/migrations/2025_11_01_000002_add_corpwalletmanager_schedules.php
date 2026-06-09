<?php

use Illuminate\Database\Migrations\Migration;

// Intentionally a no-op. Schedules are now managed by ScheduleSeeder, which is
// wired via registerDatabaseSeeders() in the service provider. This file is
// retained so Laravel's migrations table stays consistent on already-released
// installs; those installs already applied the original schedule rows, and the
// seeder's firstOrCreate is idempotent against them.
return new class extends Migration
{
    public function up() {}

    public function down() {}
};
