<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('category_id')->nullable();
            $table->uuid('brand_id')->nullable();
            $table->uuid('unit_id')->nullable();
            $table->uuid('default_supplier_id')->nullable();

            $table->string('name');
            $table->string('slug');
            $table->string('sku');
            $table->string('barcode')->nullable();
            $table->string('custom_code')->nullable();
            $table->text('description')->nullable();

            $table->string('product_type', 20)->default('simple');
            $table->boolean('track_stock')->default(true);
            $table->boolean('has_expiry')->default(false);
            $table->boolean('has_batch')->default(false);
            $table->boolean('has_serial')->default(false);

            $table->decimal('cost_price', 14, 2)->default(0);
            $table->decimal('purchase_price', 14, 2)->default(0);
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->decimal('wholesale_price', 14, 2)->nullable();
            $table->decimal('minimum_price', 14, 2)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('last_purchase_price', 14, 2)->nullable();
            $table->timestamp('last_purchase_at')->nullable();

            $table->decimal('minimum_stock', 14, 3)->default(0);
            $table->decimal('maximum_stock', 14, 3)->nullable();
            $table->decimal('reorder_level', 14, 3)->default(0);

            $table->decimal('weight', 10, 3)->nullable();
            $table->string('weight_unit', 10)->nullable();
            $table->json('dimensions')->nullable();

            $table->string('status', 20)->default('active');
            $table->string('visibility', 20)->default('visible');

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('brand_id')->references('id')->on('brands')->nullOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->nullOnDelete();
            $table->foreign('default_supplier_id')->references('id')->on('suppliers')->nullOnDelete();

            $table->unique(['business_id', 'sku']);
            $table->unique(['business_id', 'barcode']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'product_type']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
