<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only — extends the existing `audit_logs` table rather
     * than replacing it. `module`, `browser`, `operating_system` and
     * `device_type` are derived at write-time from data already
     * captured (the calling module, the existing `user_agent` string).
     * `country` stays null unless a geo-IP provider is configured later
     * — never a fabricated location. `risk_level` defaults to `normal`
     * and is set by a rule-based heuristic in a later phase.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('module', 60)->nullable()->after('business_id');
            $table->string('browser', 60)->nullable()->after('user_agent');
            $table->string('operating_system', 60)->nullable()->after('browser');
            $table->string('device_type', 20)->nullable()->after('operating_system');
            $table->string('country', 2)->nullable()->after('device_type');
            $table->string('risk_level', 20)->default('normal')->after('country');

            $table->index('module');
            $table->index('risk_level');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['module', 'browser', 'operating_system', 'device_type', 'country', 'risk_level']);
        });
    }
};
