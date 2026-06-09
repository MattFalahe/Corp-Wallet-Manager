<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add an "industry" bucket to the character contribution cache.
 *
 * Corp wallets receive industry facility tax (ref_type =
 * industry_job_tax) every time a member runs an industry job on a
 * corp-owned structure. Pre-existing buckets (ratting / mission /
 * tax_payment / donation_voluntary) do not capture this — industry
 * tax is its own activity, paid by corp members for using corp
 * infrastructure. Tracking it lets the Top Contributors leaderboard
 * surface manufacturers and reactors alongside ratters.
 *
 * Forward-only additive change. Default 0 so existing rows are
 * unaffected; the next applyDelta pass picks up the new columns
 * naturally.
 */
class AddIndustryBucketToContributions extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('corpwalletmanager_character_contributions')) {
            return;
        }

        Schema::table('corpwalletmanager_character_contributions', function (Blueprint $table) {
            if (! Schema::hasColumn('corpwalletmanager_character_contributions', 'industry_amount')) {
                $table->decimal('industry_amount', 20, 2)->default(0)->after('donation_voluntary_count');
            }
            if (! Schema::hasColumn('corpwalletmanager_character_contributions', 'industry_count')) {
                $table->unsignedInteger('industry_count')->default(0)->after('industry_amount');
            }
        });
    }

    public function down()
    {
        if (! Schema::hasTable('corpwalletmanager_character_contributions')) {
            return;
        }

        Schema::table('corpwalletmanager_character_contributions', function (Blueprint $table) {
            if (Schema::hasColumn('corpwalletmanager_character_contributions', 'industry_count')) {
                $table->dropColumn('industry_count');
            }
            if (Schema::hasColumn('corpwalletmanager_character_contributions', 'industry_amount')) {
                $table->dropColumn('industry_amount');
            }
        });
    }
}
