<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only — the legacy `website_template` string column on
     * `business_types` stays untouched (same precedent as
     * `business_type_id` alongside `business_type` on `businesses`).
     * This is "Assign Template to Business Type" from the spec.
     */
    public function up(): void
    {
        Schema::table('business_types', function (Blueprint $table) {
            $table->uuid('website_template_id')->nullable()->after('website_template');
            $table->foreign('website_template_id')->references('id')->on('website_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('business_types', function (Blueprint $table) {
            $table->dropForeign(['website_template_id']);
            $table->dropColumn('website_template_id');
        });
    }
};
