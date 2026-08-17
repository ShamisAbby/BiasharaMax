<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();
            $table->uuid('warehouse_id');
            $table->string('batch_number');
            $table->date('manufactured_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('cost_price', 14, 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->index(['business_id', 'expiry_date']);
            $table->index(['product_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
