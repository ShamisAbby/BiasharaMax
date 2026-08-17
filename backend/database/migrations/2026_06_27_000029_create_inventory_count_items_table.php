<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('inventory_count_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->decimal('expected_quantity', 14, 3);
            $table->decimal('counted_quantity', 14, 3)->nullable();
            $table->decimal('variance', 14, 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('inventory_count_id')->references('id')->on('inventory_counts')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->index('inventory_count_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_items');
    }
};
