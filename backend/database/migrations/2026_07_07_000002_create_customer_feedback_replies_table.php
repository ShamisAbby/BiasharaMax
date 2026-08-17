<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only reply thread, mirroring support_ticket_messages.
     */
    public function up(): void
    {
        Schema::create('customer_feedback_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_feedback_id');
            $table->uuid('author_id');
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('customer_feedback_id')->references('id')->on('customer_feedback')->cascadeOnDelete();
            $table->foreign('author_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['customer_feedback_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_feedback_replies');
    }
};
