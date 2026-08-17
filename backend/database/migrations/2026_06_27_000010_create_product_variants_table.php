<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('product_id');
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->json('attributes')->nullable();
            $table->decimal('cost_price', 14, 2)->nullable();
            $table->decimal('selling_price', 14, 2)->nullable();
            $table->decimal('wholesale_price', 14, 2)->nullable();
            $table->string('status', 20)->default('active');
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->unique(['business_id', 'sku']);
            $table->unique(['business_id', 'barcode']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
