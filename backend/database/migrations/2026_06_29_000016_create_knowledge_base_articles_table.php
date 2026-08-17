<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `type` distinguishes FAQs / user guides / general articles without
     * needing separate tables for each — all three are "an article shown
     * in the knowledge base", just filtered differently in the UI.
     */
    public function up(): void
    {
        Schema::create('knowledge_base_articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('knowledge_base_category_id');
            $table->string('type', 20)->default('article');
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('view_count')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('knowledge_base_category_id')->references('id')->on('knowledge_base_categories')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index(['type', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_articles');
    }
};
