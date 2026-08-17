<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of the money-format migration (docs/ADR/0002-money-format-migration.md)
 * for customer/supplier balance tracking — second in the rollout order.
 * Additive + backfill only; see 2026_08_03_000001 for the pattern this repeats.
 */
return new class extends Migration
{
    private array $tables = [
        'customers' => ['credit_limit', 'current_balance'],
        'customer_debt_transactions' => ['amount', 'balance_before', 'balance_after'],
        'loyalty_tiers' => ['minimum_spend'],
        'suppliers' => ['credit_limit', 'current_balance'],
        'supplier_debt_transactions' => ['amount', 'balance_before', 'balance_after'],
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->bigInteger("{$column}_minor")->nullable()->after($column);
                }
            });

            foreach ($columns as $column) {
                DB::statement("update {$table} set {$column}_minor = round({$column} * 100) where {$column} is not null");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->dropColumn("{$column}_minor");
                }
            });
        }
    }
};
