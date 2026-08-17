<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores both rule-based statistical insights (always available,
     * computed from real platform data — revenue trend extrapolation,
     * churn-risk heuristics) and optional LLM-generated narrative
     * summaries (only when an `integrations` row with category=ai is
     * configured). `generated_by` distinguishes the two so the UI never
     * implies a number came from an AI model when it didn't.
     */
    public function up(): void
    {
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 40);
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('data')->nullable();
            $table->string('generated_by', 20)->default('rule_based');
            $table->uuid('integration_id')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('integration_id')->references('id')->on('integrations')->nullOnDelete();
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
    }
};
