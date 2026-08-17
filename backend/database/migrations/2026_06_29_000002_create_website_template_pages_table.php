<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_template_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('website_template_id');
            $table->string('type', 30);
            $table->string('title');
            $table->string('slug');
            $table->json('content')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('website_template_id')->references('id')->on('website_templates')->cascadeOnDelete();
            $table->unique(['website_template_id', 'slug']);
            $table->index(['website_template_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_template_pages');
    }
};
