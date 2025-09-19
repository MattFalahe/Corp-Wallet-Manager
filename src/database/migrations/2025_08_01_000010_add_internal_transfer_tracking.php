<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInternalTransferTracking extends Migration
{
    public function up()
    {
        // Create metadata table to track which journal entries are internal transfers
        Schema::create('corpwalletmanager_journal_metadata', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('journal_id')->unique()->index();
            $table->bigInteger('corporation_id')->index();
            $table->boolean('is_internal_transfer')->default(false);
            $table->string('internal_transfer_category', 50)->nullable();
            $table->bigInteger('matched_transfer_id')->nullable()->index();
            $table->timestamps();
            
            $table->index(['corporation_id', 'is_internal_transfer']);
            $table->index(['corporation_id', 'internal_transfer_category']);
        });

        // Create table to track internal transfers with full details
        Schema::create('corpwalletmanager_internal_transfers', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('corporation_id')->index();
            $table->bigInteger('journal_id')->unique();
            $table->string('ref_type', 100)->index();
            $table->string('category', 50)->nullable()->index();
            $table->decimal('amount', 20, 2);
            $table->integer('division')->nullable();
            $table->integer('to_division')->nullable();
            $table->bigInteger('matched_journal_id')->nullable()->index();
            $table->boolean('is_reconciled')->default(false);
            $table->timestamp('transaction_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index(['corporation_id', 'transaction_date']);
            $table->index(['corporation_id', 'category']);
            $table->index(['corporation_id', 'is_reconciled']);
        });

        // Add internal transfer columns to daily summaries (if table exists)
        if (Schema::hasTable('corpwalletmanager_daily_summaries')) {
            Schema::table('corpwalletmanager_daily_summaries', function (Blueprint $table) {
                if (!Schema::hasColumn('corpwalletmanager_daily_summaries', 'internal_transfers_in')) {
                    $table->decimal('internal_transfers_in', 20, 2)->default(0)->after('total_expenses');
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
                if (!Schema::hasColumn('corpwalletmanager_daily_summaries', 'real_expenses')) {
                    $table->decimal('real_expenses', 20, 2)->default(0)->after('real_income')
                        ->comment('Expenses excluding internal transfers');
                }
            });
        }

        // Add internal transfer columns to monthly balances
        Schema::table('corpwalletmanager_monthly_balances', function (Blueprint $table) {
            if (!Schema::hasColumn('corpwalletmanager_monthly_balances', 'internal_transfers_total')) {
                $table->decimal('internal_transfers_total', 20, 2)->default(0)->after('balance');
            }
            if (!Schema::hasColumn('corpwalletmanager_monthly_balances', 'real_income')) {
                $table->decimal('real_income', 20, 2)->default(0)->after('internal_transfers_total');
            }
            if (!Schema::hasColumn('corpwalletmanager_monthly_balances', 'real_expenses')) {
                $table->decimal('real_expenses', 20, 2)->default(0)->after('real_income');
            }
        });

        // Add internal transfer columns to division balances
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
            if (!Schema::hasColumn('corpwalletmanager_division_balances', 'real_expenses')) {
                $table->decimal('real_expenses', 20, 2)->default(0)->after('real_income');
            }
        });

        // Add settings for internal transfer handling
        Schema::table('corpwalletmanager_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('corpwalletmanager_settings', 'corporation_id')) {
                $table->bigInteger('corporation_id')->nullable()->after('id');
                $table->index('corporation_id');
            }
            if (!Schema::hasColumn('corpwalletmanager_settings', 'exclude_internal_transfers_charts')) {
                $table->boolean('exclude_internal_transfers_charts')->default(true)->after('value');
            }
            if (!Schema::hasColumn('corpwalletmanager_settings', 'show_internal_transfers_separately')) {
                $table->boolean('show_internal_transfers_separately')->default(true)->after('exclude_internal_transfers_charts');
            }
            if (!Schema::hasColumn('corpwalletmanager_settings', 'internal_transfer_ref_types')) {
                $table->text('internal_transfer_ref_types')->nullable()->after('show_internal_transfers_separately');
            }
        });

        // Create table for internal transfer patterns/rules
        Schema::create('corpwalletmanager_transfer_patterns', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('corporation_id')->index();
            $table->string('pattern_type', 50);
            $table->string('pattern_value', 255);
            $table->string('category', 50)->nullable();
            $table->integer('confidence_score')->default(100);
            $table->boolean('is_active')->default(true);
            $table->integer('match_count')->default(0);
            $table->timestamp('last_matched_at')->nullable();
            $table->timestamps();
            
            $table->index(['corporation_id', 'pattern_type', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('corpwalletmanager_journal_metadata');
        Schema::dropIfExists('corpwalletmanager_internal_transfers');
        Schema::dropIfExists('corpwalletmanager_transfer_patterns');

        if (Schema::hasTable('corpwalletmanager_daily_summaries')) {
            Schema::table('corpwalletmanager_daily_summaries', function (Blueprint $table) {
                $columns = [
                    'internal_transfers_in',
                    'internal_transfers_out', 
                    'internal_transfer_count',
                    'real_income',
                    'real_expenses'
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('corpwalletmanager_daily_summaries', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::table('corpwalletmanager_monthly_balances', function (Blueprint $table) {
            $columns = ['internal_transfers_total', 'real_income', 'real_expenses'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('corpwalletmanager_monthly_balances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('corpwalletmanager_division_balances', function (Blueprint $table) {
            $columns = ['internal_transfers_in', 'internal_transfers_out', 'real_income', 'real_expenses'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('corpwalletmanager_division_balances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('corpwalletmanager_settings', function (Blueprint $table) {
            $columns = [
                'corporation_id',
                'exclude_internal_transfers_charts',
                'show_internal_transfers_separately',
                'internal_transfer_ref_types'
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('corpwalletmanager_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
