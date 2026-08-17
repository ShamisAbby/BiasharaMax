<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_ticket_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('support_ticket_id');
            $table->uuid('support_ticket_message_id')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('support_ticket_id')->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->foreign('support_ticket_message_id')->references('id')->on('support_ticket_messages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_attachments');
    }
};
