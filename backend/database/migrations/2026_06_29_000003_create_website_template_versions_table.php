<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only snapshot history for rollback/version-compare — each
     * publish action writes one row capturing the full template + pages
     * state at that point.
     */
    public function up(): void
    {
        Schema::create('website_template_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('website_template_id');
            $table->string('version', 20);
            $table->json('snapshot');
            $table->uuid('published_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('website_template_id')->references('id')->on('website_templates')->cascadeOnDelete();
            $table->foreign('published_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index(['website_template_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_template_versions');
    }
};
