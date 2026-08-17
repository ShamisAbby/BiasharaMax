<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The immutable customer debt ledger — mirrors the same append-only
     * design as `stock_movements`. Every charge (credit sale) and
     * payment against a customer's balance is recorded here and never
     * updated or deleted; `customers.current_balance` is a denormalized
     * running total kept in sync by CustomerDebtService for fast reads.
     */
    public function up(): void
    {
        Schema::create('customer_debt_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('customer_id');
            $table->uuid('sale_id')->nullable();
            $table->uuid('sale_payment_id')->nullable();

            $table->string('type', 20);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
            $table->foreign('sale_payment_id')->references('id')->on('sale_payments')->nullOnDelete();

            $table->index(['business_id', 'created_at']);
            $table->index(['customer_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_debt_transactions');
    }
};
