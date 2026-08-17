<?php

namespace Database\Seeders;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\RBAC\Models\PlatformRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the first SuperAdmin account. Run manually
 * (`php artisan db:seed --class=PlatformUserSeeder`) — SuperAdmin is a
 * root-level account with no public registration, so it is never part
 * of the default seeding pipeline that runs in tests or CI.
 */
class PlatformUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SUPERADMIN_EMAIL', 'superadmin@biasharamax.test');
        $password = env('SUPERADMIN_PASSWORD');

        if (! $password) {
            $this->command?->error('Set SUPERADMIN_PASSWORD in your environment before running this seeder.');

            return;
        }

        if (PlatformUser::query()->where('email', $email)->exists()) {
            $this->command?->info("Platform user {$email} already exists — skipping.");

            return;
        }

        $superAdminRole = PlatformRole::query()->where('slug', PlatformRole::SUPER_ADMIN)->first();

        $platformUser = PlatformUser::query()->create([
            'name' => env('SUPERADMIN_NAME', 'Super Admin'),
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'active',
            'email_verified_at' => now(),
            'platform_role_id' => $superAdminRole?->id,
        ]);

        // Authorization reads the pivot, not the legacy column — without
        // this the account would have no roles, which the permission
        // check treats as unrestricted. It would still work, but by
        // accident rather than by having the Super Admin role.
        if ($superAdminRole !== null) {
            $platformUser->platformRoles()->sync([$superAdminRole->id]);
        }

        $this->command?->info("Platform SuperAdmin created: {$email}");
    }
}
