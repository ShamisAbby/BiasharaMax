<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only: the legacy `business_type` string column (and its
     * 4 hardcoded call sites) stays exactly as-is for now. A later phase
     * backfills this FK from that string and migrates the call sites
     * over before the string column is ever deprecated.
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->uuid('business_type_id')->nullable()->after('business_type');
            $table->foreign('business_type_id')->references('id')->on('business_types')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign(['business_type_id']);
            $table->dropColumn('business_type_id');
        });
    }
};
