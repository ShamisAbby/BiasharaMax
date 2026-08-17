<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('sale_id');
            $table->uuid('customer_id')->nullable();

            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 30)->default('cash');
            $table->string('reference_number')->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->text('notes')->nullable();

            $table->uuid('received_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();

            $table->index(['business_id', 'paid_at']);
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
