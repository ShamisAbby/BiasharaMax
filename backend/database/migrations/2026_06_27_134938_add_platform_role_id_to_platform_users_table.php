<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirrors users.role_id exactly: single role per account, nullable,
     * nullOnDelete. Nullable so the existing seeded SuperAdmin account
     * keeps working unchanged until a Super Admin platform role is
     * assigned in Phase 2's seeder.
     */
    public function up(): void
    {
        Schema::table('platform_users', function (Blueprint $table) {
            $table->uuid('platform_role_id')->nullable()->after('status');
            $table->foreign('platform_role_id')->references('id')->on('platform_roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('platform_users', function (Blueprint $table) {
            $table->dropForeign(['platform_role_id']);
            $table->dropColumn('platform_role_id');
        });
    }
};
