<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves both user tables from one role per account to many.
 *
 * The pivot becomes the single source of truth: every existing
 * `users.role_id` / `platform_users.platform_role_id` value is copied
 * into it here, and from this point the permission checks read only the
 * pivot. The original columns are deliberately LEFT IN PLACE and left
 * populated — dropping them in the same change that starts reading the
 * new tables would leave no way back if the backfill turned out to be
 * wrong. They are no longer read by anything and can be dropped in a
 * later, separate migration once this has run in production.
 *
 * A composite primary key on each pivot makes a duplicate assignment
 * impossible at the database level, so `syncWithoutDetaching` and the
 * like can't quietly create a second row for the same pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_role_platform_user', function (Blueprint $table): void {
            $table->uuid('platform_user_id');
            $table->uuid('platform_role_id');
            $table->timestamps();

            $table->primary(['platform_user_id', 'platform_role_id']);
            $table->foreign('platform_user_id')->references('id')->on('platform_users')->cascadeOnDelete();
            $table->foreign('platform_role_id')->references('id')->on('platform_roles')->cascadeOnDelete();
            $table->index('platform_role_id');
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->timestamps();

            $table->primary(['user_id', 'role_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->index('role_id');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('platform_role_platform_user');
    }

    /**
     * Copies the existing single-role assignments across. Chunked rather
     * than a single INSERT…SELECT so the timestamps are set the same way
     * Eloquent would, and so this behaves identically on every driver.
     *
     * Soft-deleted users are included on purpose: restoring one later
     * should restore the role it had, not silently come back with none.
     */
    private function backfill(): void
    {
        $now = now();

        DB::table('platform_users')
            ->whereNotNull('platform_role_id')
            ->select('id', 'platform_role_id')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($now): void {
                DB::table('platform_role_platform_user')->insertOrIgnore(
                    collect($rows)->map(fn ($row): array => [
                        'platform_user_id' => $row->id,
                        'platform_role_id' => $row->platform_role_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });

        DB::table('users')
            ->whereNotNull('role_id')
            ->select('id', 'role_id')
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($now): void {
                DB::table('role_user')->insertOrIgnore(
                    collect($rows)->map(fn ($row): array => [
                        'user_id' => $row->id,
                        'role_id' => $row->role_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all(),
                );
            });
    }
};
