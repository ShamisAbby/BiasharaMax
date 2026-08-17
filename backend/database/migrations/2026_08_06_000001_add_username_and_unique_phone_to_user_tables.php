<?php

use App\Domain\Authentication\Support\UserIdentityRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a `username` to both user tables and puts a unique index on the
 * phone number of each.
 *
 * Both columns are NULLABLE on purpose. These tables already hold rows,
 * and neither a username nor a phone number can be invented for an
 * existing account without making data up — so the database allows the
 * gap (MySQL permits any number of NULLs under a unique index) while the
 * admin forms require both going forward. Existing accounts keep working
 * and get their values filled in the next time someone edits them.
 *
 * `users.phone` already existed but was not unique; `platform_users` had
 * no phone column at all. After this migration both tables carry the same
 * shape: name, username, email, phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        // `users.phone` has been free-form and non-unique until now, so
        // it may well hold blanks or repeats. Blank strings would all
        // collide under a unique index (unlike NULL), so they are
        // normalised first; genuine duplicates can't be resolved without
        // choosing a winner, so the migration stops with a message
        // naming them rather than failing on an opaque SQL error.
        DB::table('users')->where('phone', '')->update(['phone' => null]);

        $duplicatePhones = DB::table('users')
            ->select('phone')
            ->whereNotNull('phone')
            ->groupBy('phone')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('phone');

        if ($duplicatePhones->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add a unique index to users.phone — these numbers are used by more than one account: '
                .$duplicatePhones->implode(', ')
                .'. Resolve the duplicates, then re-run this migration.',
            );
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', UserIdentityRules::USERNAME_MAX_LENGTH)->nullable()->unique()->after('name');
            $table->unique('phone');
        });

        Schema::table('platform_users', function (Blueprint $table): void {
            $table->string('username', UserIdentityRules::USERNAME_MAX_LENGTH)->nullable()->unique()->after('name');
            $table->string('phone', 32)->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['phone']);
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });

        Schema::table('platform_users', function (Blueprint $table): void {
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
