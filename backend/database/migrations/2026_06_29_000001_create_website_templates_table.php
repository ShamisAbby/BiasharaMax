<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catalog of website templates a business can be assigned. Per-page
     * content (homepage, about, gallery, ...) lives in
     * `website_template_pages` as flexible builder blocks rather than one
     * boolean/text column per page type here — the page list itself
     * varies per template and shouldn't be hardcoded into the schema.
     */
    public function up(): void
    {
        Schema::create('website_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->uuid('business_type_id')->nullable();
            $table->text('description')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('preview_url')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('version', 20)->default('1.0.0');
            $table->boolean('is_default')->default(false);
            $table->json('theme_colors')->nullable();
            $table->json('typography')->nullable();
            $table->text('custom_css')->nullable();
            $table->json('header_config')->nullable();
            $table->json('footer_config')->nullable();
            $table->json('navigation_config')->nullable();
            $table->json('seo_settings')->nullable();
            $table->json('social_media')->nullable();
            $table->string('whatsapp_number', 32)->nullable();
            $table->text('google_maps_embed')->nullable();
            $table->text('analytics_code')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_type_id')->references('id')->on('business_types')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('platform_users')->nullOnDelete();
            $table->index(['status', 'business_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_templates');
    }
};
