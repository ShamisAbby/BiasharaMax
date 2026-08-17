<?php

namespace App\Domain\Platform\Services;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Platform\Notifications\PlatformAdminInvitedNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Invites a new platform admin. The account is created immediately
 * (status: invited) with an unusable random password — we never email a
 * real password — and a signed, time-limited link is sent so the admin
 * can set their own password and activate the account.
 */
class PlatformAdminInvitationService
{
    /**
     * `username` and `phone` are optional here because the older Inertia
     * invite form (PlatformAdminInviteRequest) doesn't collect them — but
     * they must be carried through when present, or an invite created
     * from the Filament staff form would validate both fields and then
     * silently drop them, leaving the new admin to re-enter them later.
     *
     * @param  array{name: string, username?: ?string, email: string, phone?: ?string, platform_role_id?: ?string, platform_role_ids?: list<string>}  $data
     */
    public function invite(PlatformUser $invitedBy, array $data): PlatformUser
    {
        $roleIds = $this->roleIdsFrom($data);

        $platformUser = PlatformUser::query()->create([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            // Legacy single-role column, still written so the old
            // Inertia screens keep rendering a role name. Authorization
            // reads the pivot below, not this.
            'platform_role_id' => $roleIds[0] ?? null,
            'password' => Hash::make(Str::random(40)),
            'status' => PlatformUser::STATUS_INVITED,
        ]);

        // The pivot is what actually grants permissions, so it must be
        // written even when the caller only supplied the old single
        // `platform_role_id` — otherwise an invite from the Inertia form
        // would create an account with a role on paper and none in
        // effect, which (because no roles means unrestricted) would hand
        // it full access.
        $platformUser->platformRoles()->sync($roleIds);

        $platformUser->notify(new PlatformAdminInvitedNotification($invitedBy->name));

        return $platformUser;
    }

    /**
     * Accepts either shape: `platform_role_ids` (the multi-role form) or
     * a single `platform_role_id` (the older Inertia form), so both entry
     * points end up with the same pivot rows.
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function roleIdsFrom(array $data): array
    {
        $ids = $data['platform_role_ids'] ?? $data['platform_role_id'] ?? [];

        return array_values(array_filter(Arr::wrap($ids)));
    }

    public function activate(PlatformUser $platformUser, string $password): void
    {
        $platformUser->forceFill([
            'password' => Hash::make($password),
            'status' => PlatformUser::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ])->save();
    }
}
