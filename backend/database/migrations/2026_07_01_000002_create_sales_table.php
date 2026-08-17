<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('branch_id');
            $table->uuid('warehouse_id');
            $table->uuid('customer_id')->nullable();

            $table->string('sale_number');
            $table->string('status', 20)->default('completed');
            $table->string('payment_status', 20)->default('paid');

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('balance_due', 14, 2)->default(0);

            $table->text('notes')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->uuid('voided_by')->nullable();
            $table->string('void_reason')->nullable();

            $table->uuid('sold_by')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();

            $table->unique(['business_id', 'sale_number']);
            $table->index(['business_id', 'status', 'created_at']);
            $table->index(['business_id', 'payment_status']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
