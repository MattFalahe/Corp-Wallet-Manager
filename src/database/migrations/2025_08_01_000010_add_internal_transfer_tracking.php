<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInternalTransferTracking extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Create a new table to track internal transfers without modifying core tables
        Schema::create('corpwalletmanager_internal_transfers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('corporation_id')->index();
            $table->bigInteger('journal_id')->unique(); // References corporation_wallet_journals.id
            $table->string('ref_type', 100)->index();
            $table->string('category', 50)->nullable()->index();
            $table->decimal('amount', 20, 2);
            $table->integer('division')->nullable();
            $table->integer('to_division')->nullable();
            $table->bigInteger('matched_journal_id')->nullable()->index(); // For paired transfers
            $table->boolean('is_reconciled')->default(false);
            $table->timestamp('transaction_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Composite indexes for performance
            $table->index(['corporation_id', 'transaction_date']);
            $table->index(['corporation_id', 'category']);
            $table->index(['corporation_id', 'is_reconciled']);
        });

        // Add internal transfer columns to daily summaries (our table)
        Schema::table('corpwalletmanager_daily_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('corpwalletmanager_daily_summaries', 'internal_transfers_in')) {
                $table->decimal('internal_transfers_in', 20, 2)->default(0)->after('total_expense');
            }
            if (!Schema::hasColumn('corpwalletmanager_daily_summaries', 'internal_transfers_out')) {
                $table->decimal('internal_transfers_out', 20, 2)->default(0)->after('internal_transfers_in');
            }
            if (!Schema::hasColumn('corpwalletmanager_daily_summaries', 'internal_transfer_count')) {
                $table->integer('internal_transfer_count')->default(0)->after('internal_transfers_out');
            }
            if (!Schema::hasColumn('corpwalletmanager_daily_summaries', 'real_income')) {
                $table->decimal('real_income', 20, 2)->default(0)->after('internal_transfer_count')
                    ->comment('Income excluding internal transfers');
            }
            if (!Schema::hasColumn('corpwalletmanager_daily_summaries', 'real_expense')) {
                $table->decimal('real_expense', 20, 2)->default(0)->after('real_income')
                    ->comment('Expenses excluding internal transfers');
            }
        });

        // Add internal transfer columns to monthly balances (our table)
        Schema::table('corpwalletmanager_monthly_balances', function (Blueprint $table) {
            if (!Schema::hasColumn('corpwalletmanager_monthly_balances', 'internal_transfers_total')) {
                $table->decimal('internal_transfers_total', 20, 2)->default(0)->after('average_balance');
            }
            if (!Schema::hasColumn('corpwalletmanager_monthly_balances', 'real_income')) {
                $table->decimal('real_income', 20, 2)->default(0)->after('internal_transfers_total');
            }
            if (!Schema::hasColumn('corpwalletmanager_monthly_balances', 'real_expense')) {
                $table->decimal('real_expense', 20, 2)->default(0)->after('real_income');
            }
        });

        // Add internal transfer columns to division balances (our table)
        Schema::table('corpwalletmanager_division_balances', function (Blueprint $table) {
            if (!Schema::hasColumn('corpwalletmanager_division_balances', 'internal_transfers_in')) {
                $table->decimal('internal_transfers_in', 20, 2)->default(0)->after('balance');
            }
            if (!Schema::hasColumn('corpwalletmanager_division_balances', 'internal_transfers_out')) {
                $table->decimal('internal_transfers_out', 20, 2)->default(0)->after('internal_transfers_in');
            }
            if (!Schema::hasColumn('corpwalletmanager_division_balances', 'real_income')) {
                $table->decimal('real_income', 20, 2)->default(0)->after('internal_transfers_out');
            }
            if (!Schema::hasColumn('corpwalletmanager_division_balances', 'real_expense')) {
                $table->decimal('real_expense', 20, 2)->default(0)->after('real_income');
            }
        });

        // Add settings for internal transfer handling (our table)
        Schema::table('corpwalletmanager_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('corpwalletmanager_settings', 'exclude_internal_transfers_charts')) {
                $table->boolean('exclude_internal_transfers_charts')->default(true)->after('use_precomputed_monthly');
            }
            if (!Schema::hasColumn('corpwalletmanager_settings', 'show_internal_transfers_separately')) {
                $table->boolean('show_internal_transfers_separately')->default(true)->after('exclude_internal_transfers_charts');
            }
            if (!Schema::hasColumn('corpwalletmanager_settings', 'internal_transfer_ref_types')) {
                $table->text('internal_transfer_ref_types')->nullable()->after('show_internal_transfers_separately');
            }
        });

        // Create a table for internal transfer patterns/rules
        Schema::create('corpwalletmanager_transfer_patterns', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('corporation_id')->index();
            $table->string('pattern_type', 50); // 'ref_type', 'description', 'amount', 'schedule'
            $table->string('pattern_value', 255);
            $table->string('category', 50)->nullable();
            $table->integer('confidence_score')->default(100); // 0-100
            $table->boolean('is_active')->default(true);
            $table->integer('match_count')->default(0);
            $table->timestamp('last_matched_at')->nullable();
            $table->timestamps();
            
            $table->index(['corporation_id', 'pattern_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('corpwalletmanager_internal_transfers');
        Schema::dropIfExists('corpwalletmanager_transfer_patterns');

        Schema::table('corpwalletmanager_daily_summaries', function (Blueprint $table) {
            $table->dropColumn([
                'internal_transfers_in',
                'internal_transfers_out', 
                'internal_transfer_count',
                'real_income',
                'real_expense'
            ]);
        });

        Schema::table('corpwalletmanager_monthly_balances', function (Blueprint $table) {
            $table->dropColumn([
                'internal_transfers_total',
                'real_income',
                'real_expense'
            ]);
        });

        Schema::table('corpwalletmanager_division_balances', function (Blueprint $table) {
            $table->dropColumn([
                'internal_transfers_in',
                'internal_transfers_out',
                'real_income',
                'real_expense'
            ]);
        });

        Schema::table('corpwalletmanager_settings', function (Blueprint $table) {
            $table->dropColumn([
                'exclude_internal_transfers_charts',
                'show_internal_transfers_separately',
                'internal_transfer_ref_types'
            ]);
        });
    }
}
