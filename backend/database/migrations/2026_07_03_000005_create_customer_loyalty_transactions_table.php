<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable loyalty points ledger — same append-only design as
     * `customer_debt_transactions`. Points are awarded/redeemed manually
     * by staff (no fabricated earn-rate formula); `customers.loyalty_points`
     * is a denormalized running total kept in sync by CustomerLoyaltyService.
     */
    public function up(): void
    {
        Schema::create('customer_loyalty_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('customer_id');

            $table->string('type', 20);
            $table->integer('points');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->text('notes')->nullable();

            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();

            $table->index(['customer_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loyalty_transactions');
    }
};
