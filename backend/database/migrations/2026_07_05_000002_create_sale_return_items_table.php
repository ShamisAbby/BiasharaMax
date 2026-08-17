<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sale_return_id');
            $table->uuid('sale_item_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('product_batch_id')->nullable();

            $table->decimal('quantity_returned', 14, 3);
            $table->string('condition', 20)->default('good');
            $table->boolean('restock')->default(true);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_refund_amount', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('sale_return_id')->references('id')->on('sale_returns')->cascadeOnDelete();
            $table->foreign('sale_item_id')->references('id')->on('sale_items')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->nullOnDelete();
            $table->foreign('product_batch_id')->references('id')->on('product_batches')->nullOnDelete();

            $table->index('sale_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
    }
};
