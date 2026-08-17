<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only narrative timeline for a single transaction (e.g.
     * "retried by Super Admin X", "webhook received: succeeded"). The
     * generic `audit_logs` table already diffs field changes via the
     * Auditable trait; this table captures the human-readable story the
     * Payment Timeline UI needs instead of re-deriving it from diffs.
     */
    public function up(): void
    {
        Schema::create('payment_transaction_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('payment_transaction_id');
            $table->string('event', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('message')->nullable();
            $table->string('actor_type', 20)->nullable();
            $table->uuid('actor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('payment_transaction_id')->references('id')->on('payment_transactions')->cascadeOnDelete();
            $table->index(['payment_transaction_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transaction_logs');
    }
};
