<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manually-recorded payments. BiasharaMax has no payment gateway
     * integration yet, so renewals are settled outside the system (cash,
     * bank transfer, mobile money) and a SuperAdmin logs them here —
     * this is real transaction history, not a fabricated revenue figure.
     */
    public function up(): void
    {
        Schema::create('subscription_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('subscription_id');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('TZS');
            $table->string('billing_cycle', 20);
            $table->string('status', 20)->default('paid');
            $table->string('payment_method', 30)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('recorded_by')->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
            $table->foreign('recorded_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index(['business_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_transactions');
    }
};
