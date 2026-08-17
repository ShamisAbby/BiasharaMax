<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('purchase_order_id');
            $table->uuid('supplier_id')->nullable();

            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 30)->default('cash');
            $table->string('reference_number')->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->text('notes')->nullable();

            $table->uuid('paid_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();

            $table->index(['business_id', 'paid_at']);
            $table->index('purchase_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
