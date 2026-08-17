<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // A plain unique(warehouse_id, product_id, product_variant_id) would
        // not work here: both Postgres and MySQL treat NULL as distinct in a
        // unique index, so two stock rows for the same simple (non-variant)
        // product in the same warehouse would NOT violate it.
        //
        // Note `in_array` rather than `=== 'mysql'`. Laravel 11 added a
        // separate `mariadb` driver, and a MariaDB host reached through the
        // older `mysql` driver reports `mysql` too — so this branch has to
        // catch both names or a MariaDB server silently takes the Postgres
        // path and fails on `WHERE` in a CREATE INDEX.
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            // MySQL has no partial/filtered unique index. Emulate the same
            // "unique only for rows matching this condition" behavior with a
            // generated column that's NULL for rows we don't want to
            // constrain — MySQL's unique index (like Postgres's) treats NULL
            // as distinct, so those rows are exempt, the same net effect as
            // a partial index's WHERE clause. char(73) = 36 (uuid) + 1 (':')
            // + 36 (uuid).
            //
            // VIRTUAL, not STORED, and this is the whole difficulty.
            //
            // MariaDB refuses CONCAT() inside a STORED generated column —
            // error 1901, "Function or expression cannot be used in the
            // GENERATED ALWAYS clause". The reason is that a stored column
            // persists bytes whose collation would depend on the session
            // that wrote them, so MariaDB will not commit to it. Computed
            // at read time it is fine, and MariaDB 10.2+ and MySQL 5.7+ can
            // both index a virtual column, so the unique constraint still
            // holds. Verified against MariaDB 11.8: `case ... concat` is
            // accepted as VIRTUAL and rejected as STORED, as are `if()`,
            // `coalesce()` and `concat_ws()`.
            //
            // The cost is a little CPU per row read instead of per row
            // written. Do not "optimise" this back to STORED.
            DB::statement(
                "alter table inventories add column simple_product_key char(73) as (
                    case when product_variant_id is null then concat(warehouse_id, ':', product_id) else null end
                ) virtual"
            );
            DB::statement(
                'create unique index inventories_unique_simple_product on inventories (simple_product_key)'
            );

            DB::statement(
                "alter table inventories add column variant_key char(73) as (
                    case when product_variant_id is not null then concat(warehouse_id, ':', product_variant_id) else null end
                ) virtual"
            );
            DB::statement(
                'create unique index inventories_unique_variant on inventories (variant_key)'
            );
        } else {
            DB::statement(
                'CREATE UNIQUE INDEX inventories_unique_simple_product ON inventories (warehouse_id, product_id) WHERE product_variant_id IS NULL'
            );
            DB::statement(
                'CREATE UNIQUE INDEX inventories_unique_variant ON inventories (warehouse_id, product_variant_id) WHERE product_variant_id IS NOT NULL'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
