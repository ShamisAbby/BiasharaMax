<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('sale_id');
            $table->uuid('customer_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->uuid('warehouse_id')->nullable();

            $table->string('return_number');
            $table->string('status', 20)->default('pending');
            $table->string('reason', 30);
            $table->string('refund_method', 20)->nullable();
            $table->decimal('refund_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();

            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();

            $table->unique(['business_id', 'return_number']);
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};
