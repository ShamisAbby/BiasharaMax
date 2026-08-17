<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A business's own editable copy of its website — seeded from the
     * WebsiteTemplate assigned to its BusinessType, then owned and edited
     * independently so multiple businesses sharing a template never
     * clobber each other's content.
     */
    public function up(): void
    {
        Schema::create('business_websites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_id')->unique();
            $table->uuid('website_template_id')->nullable();

            $table->string('status', 20)->default('draft');
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('website_template_id')->references('id')->on('website_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_websites');
    }
};
