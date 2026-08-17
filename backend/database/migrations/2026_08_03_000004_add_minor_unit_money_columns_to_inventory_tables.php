<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of the money-format migration (docs/ADR/0002-money-format-migration.md)
 * for Inventory — fourth in the rollout order.
 *
 * Two scales here, per the ADR's Section 2: most columns are plain integer
 * minor units (x100 for a 2-decimal-place currency), but `unit_cost` (stock
 * movements, adjustment/transfer items) and `average_cost` (inventories) are
 * decimal(14,4) — 4 decimal places, deliberately higher precision than the
 * currency's minor unit, for weighted-average costing accuracy across many
 * small inbound movements. Those convert to integer *micros*
 * (minor-unit x 10,000, i.e. source value x 1,000,000) instead, so they don't
 * lose the precision they were given the extra decimal places for.
 * `total_cost` is a real, invoice-comparable amount, so it stays at the
 * standard minor-unit scale even though it lives on the same table as
 * `unit_cost`.
 */
return new class extends Migration
{
    private array $minorTables = [
        'products' => ['cost_price', 'purchase_price', 'selling_price', 'wholesale_price', 'minimum_price', 'last_purchase_price'],
        'product_variants' => ['cost_price', 'selling_price', 'wholesale_price'],
        'product_supplier' => ['supplier_cost_price'],
        'product_batches' => ['cost_price'],
    ];

    /** table => [column => new column name] for the x1,000,000 (micros) scale. */
    private array $microsColumns = [
        'stock_movements' => ['unit_cost' => 'unit_cost_micros'],
        'stock_adjustment_items' => ['unit_cost' => 'unit_cost_micros'],
        'stock_transfer_items' => ['unit_cost' => 'unit_cost_micros'],
        'inventories' => ['average_cost' => 'average_cost_micros'],
    ];

    public function up(): void
    {
        foreach ($this->minorTables as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->bigInteger("{$column}_minor")->nullable()->after($column);
                }
            });

            foreach ($columns as $column) {
                DB::statement("update {$table} set {$column}_minor = round({$column} * 100) where {$column} is not null");
            }
        }

        // stock_movements.total_cost is a standard minor-unit amount, not a
        // per-unit cost — handled separately from its sibling unit_cost.
        Schema::table('stock_movements', function (Blueprint $blueprint) {
            $blueprint->bigInteger('total_cost_minor')->nullable()->after('total_cost');
        });
        DB::statement('update stock_movements set total_cost_minor = round(total_cost * 100) where total_cost is not null');

        foreach ($this->microsColumns as $table => $mapping) {
            Schema::table($table, function (Blueprint $blueprint) use ($mapping) {
                foreach ($mapping as $newColumn) {
                    $blueprint->bigInteger($newColumn)->nullable();
                }
            });

            foreach ($mapping as $oldColumn => $newColumn) {
                DB::statement("update {$table} set {$newColumn} = round({$oldColumn} * 1000000) where {$oldColumn} is not null");
            }
        }
    }

    public function down(): void
    {
        foreach ($this->minorTables as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->dropColumn("{$column}_minor");
                }
            });
        }

        Schema::table('stock_movements', function (Blueprint $blueprint) {
            $blueprint->dropColumn('total_cost_minor');
        });

        foreach ($this->microsColumns as $table => $mapping) {
            Schema::table($table, function (Blueprint $blueprint) use ($mapping) {
                foreach ($mapping as $newColumn) {
                    $blueprint->dropColumn($newColumn);
                }
            });
        }
    }
};
