<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sale_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('product_batch_id')->nullable();

            // Snapshotted at sale time so historical sales remain accurate
            // even if the product is later renamed, repriced, or deleted.
            $table->string('product_name');
            $table->string('product_sku')->nullable();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);

            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('product_batch_id')->references('id')->on('product_batches')->nullOnDelete();

            $table->index('sale_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
