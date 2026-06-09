<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CreateCorpwalletmanagerWebhooksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('corpwalletmanager_webhooks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->text('webhook_url');
            // NULL corporation_id = global webhook (receives every corp's reports).
            $table->bigInteger('corporation_id')->nullable()->index();
            $table->boolean('is_enabled')->default(true);
            // Discord role snowflake (or <@&id> form) mentioned on delivery.
            $table->string('discord_role_id', 32)->nullable();
            // Per-report-type subscriptions.
            $table->boolean('notify_weekly_report')->default(true);
            $table->boolean('notify_monthly_report')->default(true);
            $table->boolean('notify_on_demand_report')->default(true);
            // Delivery health.
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        $this->importLegacyWebhook();
    }

    /**
     * One-time fold-in of the pre-3.0 single global Discord webhook
     * (stored as discord_webhook_* rows in corpwalletmanager_settings)
     * into a first-class webhook row, so installs upgrading from 2.x keep
     * their configured Discord delivery without re-entering anything.
     *
     * The legacy settings rows are intentionally left in place as dormant
     * data — this migration never deletes them.
     *
     * @return void
     */
    private function importLegacyWebhook(): void
    {
        try {
            if (! Schema::hasTable('corpwalletmanager_settings')) {
                return;
            }

            $setting = fn (string $key) => DB::table('corpwalletmanager_settings')
                ->where('key', $key)
                ->value('value');

            $url = trim((string) $setting('discord_webhook_url'));
            if ($url === '') {
                return; // Nothing was configured pre-3.0.
            }

            $truthy = fn ($value) => in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);

            DB::table('corpwalletmanager_webhooks')->insert([
                'name'                    => 'Imported Discord Webhook',
                'webhook_url'             => $url,
                'corporation_id'          => null,
                'is_enabled'              => $truthy($setting('discord_webhook_enabled')),
                'discord_role_id'         => null,
                'notify_weekly_report'    => $truthy($setting('discord_weekly_report')),
                'notify_monthly_report'   => $truthy($setting('discord_monthly_report')),
                // On-demand delivery was the only path that actually worked
                // pre-3.0, so preserve it as enabled.
                'notify_on_demand_report' => true,
                'success_count'           => 0,
                'failure_count'           => 0,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Corp Wallet Manager] Legacy webhook import skipped: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('corpwalletmanager_webhooks');
    }
}
