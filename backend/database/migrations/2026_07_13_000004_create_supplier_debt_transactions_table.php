<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The immutable supplier debt ledger — mirrors customer_debt_transactions.
     * Every bill (goods received) and payment against a supplier's balance is
     * recorded here and never updated or deleted; suppliers.current_balance is
     * a denormalized running total kept in sync by the services that write here.
     */
    public function up(): void
    {
        Schema::create('supplier_debt_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('supplier_id');
            $table->uuid('purchase_order_id')->nullable();
            $table->uuid('supplier_payment_id')->nullable();

            $table->string('type', 20);
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->cascadeOnDelete();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
            $table->foreign('supplier_payment_id')->references('id')->on('supplier_payments')->nullOnDelete();

            $table->index(['business_id', 'created_at']);
            $table->index(['supplier_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_debt_transactions');
    }
};
