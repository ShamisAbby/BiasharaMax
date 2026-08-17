<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_received_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('goods_received_note_id');
            $table->uuid('purchase_order_item_id');
            $table->uuid('product_id');
            $table->uuid('product_variant_id')->nullable();

            $table->decimal('quantity_received', 14, 3)->default(0);
            $table->decimal('quantity_damaged', 14, 3)->default(0);
            $table->decimal('quantity_rejected', 14, 3)->default(0);

            $table->string('batch_number')->nullable();
            $table->date('manufactured_date')->nullable();
            $table->date('expiry_date')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('goods_received_note_id')->references('id')->on('goods_received_notes')->cascadeOnDelete();
            $table->foreign('purchase_order_item_id')->references('id')->on('purchase_order_items')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->nullOnDelete();

            $table->index('purchase_order_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_received_items');
    }
};
