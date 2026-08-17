<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only conversation thread. `is_internal_note` rows are
     * never shown to the business that opened the ticket.
     */
    public function up(): void
    {
        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('support_ticket_id');
            $table->string('author_type', 20);
            $table->uuid('author_id');
            $table->text('body');
            $table->boolean('is_internal_note')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('support_ticket_id')->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->index(['support_ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
    }
};
