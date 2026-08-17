<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_received_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('purchase_order_id');
            $table->uuid('branch_id')->nullable();
            $table->uuid('warehouse_id');

            $table->string('grn_number');
            $table->uuid('received_by')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();

            $table->unique(['business_id', 'grn_number']);
            $table->index(['business_id', 'purchase_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_received_notes');
    }
};
