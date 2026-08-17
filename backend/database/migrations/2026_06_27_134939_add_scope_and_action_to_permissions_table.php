<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `scope` distinguishes tenant permissions (existing rows — products,
     * branches, etc.) from new platform-level permissions (Roles &
     * Permissions, Modules, Business Types management).
     *
     * `action` is a generated column — always derived from the existing
     * `{module}.{action}` slug format, so a Permission Matrix UI can group
     * by module × action without parsing slugs anywhere. Generated (not
     * backfilled) deliberately: PermissionSeeder doesn't need to change to
     * populate it, and it can never drift out of sync with slug. Every
     * existing slug is exactly one dot (verified against PermissionSeeder),
     * which is why Postgres's split_part(slug, '.', 2) (2nd of exactly two
     * dot-separated parts) and MySQL's substring_index(slug, '.', -1)
     * (everything after the last dot) are equivalent here — they'd diverge
     * for a slug with more than one dot, which doesn't occur in this data.
     */
    public function up(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('scope', 20)->default('tenant')->after('module');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("alter table permissions add column action varchar(30) generated always as (substring_index(slug, '.', -1)) stored");
        } else {
            DB::statement("alter table permissions add column action varchar(30) generated always as (split_part(slug, '.', 2)) stored");
        }

        Schema::table('permissions', function (Blueprint $table) {
            $table->index('scope');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['scope', 'action']);
        });
    }
};
