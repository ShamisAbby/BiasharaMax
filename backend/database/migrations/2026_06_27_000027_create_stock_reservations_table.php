<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('warehouse_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->decimal('quantity', 14, 3);
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->index(['reference_type', 'reference_id']);
            $table->index(['product_id', 'warehouse_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
