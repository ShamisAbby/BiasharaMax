<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->nullable();
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('attribute_value_id');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('attribute_value_id')->references('id')->on('attribute_values')->cascadeOnDelete();
            $table->unique(['product_id', 'product_variant_id', 'attribute_value_id'], 'pav_unique');
        });

        // Exactly one of product_id / product_variant_id must be set: an
        // attribute value belongs either to a base product (e.g. "Material:
        // Cotton" on a simple product) or to one specific variant, never both.
        // Portable as-is: MySQL 8.0.16+ and Postgres both support the same
        // ALTER TABLE ... ADD CONSTRAINT ... CHECK (...) syntax with only
        // IS NULL/IS NOT NULL/AND/OR, no engine-specific functions. MySQL
        // versions before 8.0.16 parse but silently ignore CHECK constraints
        // — this requires 8.0.16+.
        DB::statement(<<<'SQL'
            ALTER TABLE product_attribute_values
            ADD CONSTRAINT pav_exactly_one_owner
            CHECK (
                (product_id IS NOT NULL AND product_variant_id IS NULL)
                OR (product_id IS NULL AND product_variant_id IS NOT NULL)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_attribute_values');
    }
};
