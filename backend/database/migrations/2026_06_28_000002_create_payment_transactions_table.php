<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-wide payment ledger. Distinct from `subscription_transactions`
     * (the pre-existing manual revenue-recording table, left untouched) —
     * this table is the unified record for every money movement Finance
     * needs to show: gateway-processed charges, manual entries, renewals,
     * upgrades, and refunds, across both Subscriptions and Licenses.
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('payment_gateway_id')->nullable();
            $table->uuid('payable_id')->nullable();
            $table->string('payable_type')->nullable();
            $table->uuid('parent_transaction_id')->nullable();
            $table->index('parent_transaction_id');

            $table->string('type', 30);
            $table->string('reference_number')->unique();
            $table->string('invoice_number')->nullable()->unique();
            $table->string('external_transaction_id')->nullable();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('TZS');
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('fee_amount', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('refunded_amount', 12, 2)->default(0);

            $table->string('status', 20)->default('pending');
            $table->string('payment_method', 30)->nullable();
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->uuid('initiated_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('payment_gateway_id')->references('id')->on('payment_gateways')->nullOnDelete();
            $table->foreign('initiated_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('platform_users')->nullOnDelete();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['business_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('type');
        });

        // Self-referencing FK (refund -> original transaction) must be added
        // after the table exists; Postgres rejects it inline in CREATE TABLE.
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreign('parent_transaction_id')->references('id')->on('payment_transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
