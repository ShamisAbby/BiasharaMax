<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id');
            $table->uuid('category_id')->nullable();
            $table->uuid('author_id')->nullable();

            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('featured_image_path')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('article_categories')->nullOnDelete();
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();

            $table->unique(['business_id', 'slug']);
            $table->index(['business_id', 'status', 'published_at']);
        });

        Schema::create('article_article_tag', function (Blueprint $table) {
            $table->uuid('article_id');
            $table->uuid('article_tag_id');

            $table->foreign('article_id')->references('id')->on('articles')->cascadeOnDelete();
            $table->foreign('article_tag_id')->references('id')->on('article_tags')->cascadeOnDelete();
            $table->primary(['article_id', 'article_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_article_tag');
        Schema::dropIfExists('articles');
    }
};
