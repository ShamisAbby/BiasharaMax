<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only request/response log per gateway — webhook deliveries,
     * outbound charge/refund/verify calls, and health checks. Lets a
     * SuperAdmin debug "why did this webhook fail" without guessing.
     */
    public function up(): void
    {
        Schema::create('payment_gateway_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_gateway_id');
            $table->uuid('payment_transaction_id')->nullable();
            $table->string('direction', 10);
            $table->string('event_type', 30);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('is_successful')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('payment_gateway_id')->references('id')->on('payment_gateways')->cascadeOnDelete();
            $table->foreign('payment_transaction_id')->references('id')->on('payment_transactions')->nullOnDelete();
            $table->index(['payment_gateway_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_logs');
    }
};
