<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('integration_id');
            $table->string('direction', 10);
            $table->string('event_type', 40);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('is_successful')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('integration_id')->references('id')->on('integrations')->cascadeOnDelete();
            $table->index(['integration_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
    }
};
