<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('stock_transfer_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('product_batch_id')->nullable();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 4)->nullable();
            $table->timestamps();

            $table->foreign('stock_transfer_id')->references('id')->on('stock_transfers')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('product_batch_id')->references('id')->on('product_batches')->nullOnDelete();
            $table->index('stock_transfer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
    }
};
