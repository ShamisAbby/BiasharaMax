<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('webhook_id');
            $table->string('event');
            $table->json('payload');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->boolean('is_successful')->default(false);
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('webhook_id')->references('id')->on('webhooks')->cascadeOnDelete();
            $table->index(['webhook_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
