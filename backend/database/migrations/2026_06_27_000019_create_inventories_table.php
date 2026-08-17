<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('branch_id');
            $table->uuid('warehouse_id');
            $table->uuid('warehouse_location_id')->nullable();
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();

            // Mirrors product_variant_id, with '' standing in for NULL so
            // the unique index below actually applies to simple products.
            // Maintained by Inventory::booted, never set by callers.
            $table->char('variant_key', 36)->default('');

            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('reserved_quantity', 14, 3)->default(0);
            $table->decimal('minimum_stock', 14, 3)->nullable();
            $table->decimal('maximum_stock', 14, 3)->nullable();
            $table->decimal('reorder_level', 14, 3)->nullable();
            $table->decimal('average_cost', 14, 4)->default(0);
            $table->timestamp('last_counted_at')->nullable();

            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('warehouse_location_id')->references('id')->on('warehouse_locations')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();

            $table->index(['business_id', 'product_id']);
        });

        // Uniqueness: one stock row per (warehouse, product) for a simple
        // product, and one per (warehouse, variant) for a variant.
        //
        // A plain unique(warehouse_id, product_id, product_variant_id) does
        // not express that, because MySQL, MariaDB and Postgres all treat
        // NULLs as distinct in a unique index — two rows for the same simple
        // product in the same warehouse would not collide.
        //
        // Three approaches were tried against the real server before this
        // one, and both of the clever ones are dead ends on MariaDB:
        //
        //  1. A STORED generated column holding the key. MariaDB rejects
        //     CONCAT in a stored generated column (error 1901) because the
        //     persisted bytes would carry the writing session's collation.
        //     `if()`, `coalesce()` and `concat_ws()` are refused too.
        //  2. The same column as VIRTUAL. MariaDB accepts the column — and
        //     then refuses to build a unique index on it, with the same
        //     1901, because indexing forces it to materialise the value.
        //
        // So the key is an ordinary column, kept in step by the model (see
        // Inventory::booted). `variant_key` is the empty string for simple
        // products rather than NULL, which is the whole point: an empty
        // string compares equal to itself, so the index constrains those
        // rows instead of exempting them.
        //
        // One index, no per-driver branch. The branch is what made this
        // break in the first place — the MySQL path was chosen for MariaDB,
        // which is not the same database.
        Schema::table('inventories', function (Blueprint $table) {
            $table->unique(
                ['warehouse_id', 'product_id', 'variant_key'],
                'inventories_unique_stock_row',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
