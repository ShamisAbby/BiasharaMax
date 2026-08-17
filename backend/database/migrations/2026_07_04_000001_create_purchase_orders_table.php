<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('branch_id')->nullable();
            $table->uuid('warehouse_id')->nullable();
            $table->uuid('supplier_id');

            $table->string('po_number');
            $table->string('status', 20)->default('draft');

            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('shipping_cost', 14, 2)->default(0);
            $table->decimal('other_charges', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();

            $table->unique(['business_id', 'po_number']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
