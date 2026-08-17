<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of the two admin surfaces this administrator lands on.
 *
 * Nullable with no default on purpose. Null means "never chosen", which
 * is not the same as having chosen the Inertia admin — the distinction
 * matters the day one surface is retired, because it tells you who
 * actively preferred it versus who simply never touched the setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_users', function (Blueprint $table): void {
            $table->string('preferred_admin_surface', 20)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('platform_users', function (Blueprint $table): void {
            $table->dropColumn('preferred_admin_surface');
        });
    }
};
