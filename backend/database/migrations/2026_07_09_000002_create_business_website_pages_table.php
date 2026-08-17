<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_website_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_website_id');

            $table->string('type', 30);
            $table->string('title');
            $table->string('slug');
            $table->json('content')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('business_website_id')->references('id')->on('business_websites')->cascadeOnDelete();
            $table->unique(['business_website_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_website_pages');
    }
};
