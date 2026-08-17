<?php

namespace App\Domain\Authentication\Models;

use App\Domain\Platform\Support\AdminSurface;
use App\Domain\RBAC\Models\PlatformRole;
use Database\Factories\PlatformUserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * Platform-level Super Admin account. Fully separate from tenant `users`
 * so platform staff can manage every business without ever being subject
 * to tenant isolation scopes.
 */
class PlatformUser extends Authenticatable implements FilamentUser, HasAvatar, MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    public const STATUS_INVITED = 'invited';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'avatar',
        'password',
        'status',
        'platform_role_id',
        'preferred_admin_surface',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Per-instance authorization cache — see resolvePlatformPermissions().
     * Declared properties, so Eloquent never treats them as attributes.
     *
     * @var list<string>
     */
    private array $grantedPlatformPermissionSlugs = [];

    private bool $platformPermissionsResolved = false;

    private bool $isUnrestrictedPlatformUser = false;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function newFactory(): PlatformUserFactory
    {
        return PlatformUserFactory::new();
    }

    /**
     * @deprecated Superseded by platformRoles(). The `platform_role_id`
     * column still exists and is still populated for rollback safety,
     * but nothing reads it for authorization any more — see the
     * 2026_08_06 pivot migration. Kept only so existing callers don't
     * break while they are migrated.
     */
    public function platformRole(): BelongsTo
    {
        return $this->belongsTo(PlatformRole::class);
    }

    public function platformRoles(): BelongsToMany
    {
        return $this->belongsToMany(PlatformRole::class, 'platform_role_platform_user')
            ->withTimestamps();
    }

    /**
     * Keeps the pivot in step with any write to the legacy
     * `platform_role_id` column.
     *
     * Plenty of code still assigns a role the old way — the Inertia
     * staff screens, seeders, tests. Now that authorization reads only
     * the pivot, such a write would otherwise leave the account with NO
     * pivot rows, and because an account with no roles is treated as
     * unrestricted, assigning a narrow role the old way would hand out
     * FULL access. Bridging it here means every existing caller keeps
     * working and fails closed instead.
     *
     * Only fires when the column actually changed, so it can't clobber a
     * multi-role assignment made through the pivot directly.
     */
    protected static function booted(): void
    {
        static::saved(function (self $platformUser): void {
            // `wasChanged()` is only meaningful for an update, so a fresh
            // insert has to be handled explicitly or a newly created
            // account would skip the sync entirely.
            if (! $platformUser->wasRecentlyCreated && ! $platformUser->wasChanged('platform_role_id')) {
                return;
            }

            if ($platformUser->wasRecentlyCreated && $platformUser->platform_role_id === null) {
                return;
            }

            $platformUser->platformRoles()->sync(array_filter([$platformUser->platform_role_id]));
            $platformUser->forgetPlatformPermissionCache();
        });
    }

    /**
     * Permissions are the UNION of every assigned role: holding both
     * Support Agent and Finance Manager grants both sets, and the most
     * permissive role wins. There is no per-user deny.
     *
     * A user with NO roles at all is treated as unrestricted. That is
     * pre-existing behaviour, carried over deliberately so the original
     * seeded Super Admin (which predates roles entirely) doesn't lock
     * itself out — but it does mean a newly invited admin left without a
     * role has full access until one is assigned. Worth tightening once
     * every account is guaranteed to have a role.
     */
    /**
     * Which admin surface this administrator lands on.
     *
     * Always answers with a valid surface: the column is nullable, and a
     * value written before a surface was renamed or retired would
     * otherwise leave this account redirecting somewhere that no longer
     * exists.
     */
    public function preferredAdminSurface(): string
    {
        return AdminSurface::normalise($this->preferred_admin_surface);
    }

    public function hasPlatformPermission(string $permissionSlug): bool
    {
        $this->resolvePlatformPermissions();

        return $this->isUnrestrictedPlatformUser
            || in_array($permissionSlug, $this->grantedPlatformPermissionSlugs, true);
    }

    /**
     * Loads every granted slug once per instance. Authorization is
     * checked many times per request — once per navigation item, table
     * action and page guard — so without memoising, rendering the
     * sidebar alone would run dozens of identical queries.
     *
     * The separate `resolved` flag matters: a user whose roles grant
     * nothing produces a legitimately empty list, which must not be
     * mistaken for "not loaded yet" and re-queried on every check.
     */
    private function resolvePlatformPermissions(): void
    {
        if ($this->platformPermissionsResolved) {
            return;
        }

        $roles = $this->platformRoles()->with('permissions:id,slug')->get();

        $this->isUnrestrictedPlatformUser = $roles->isEmpty();
        $this->grantedPlatformPermissionSlugs = $roles
            ->flatMap(fn (PlatformRole $role): array => $role->permissions->pluck('slug')->all())
            ->unique()
            ->values()
            ->all();

        $this->platformPermissionsResolved = true;
    }

    /**
     * Drops the memoised permission set, so a role change takes effect
     * within the same request that made it.
     */
    public function forgetPlatformPermissionCache(): void
    {
        $this->platformPermissionsResolved = false;
        $this->grantedPlatformPermissionSlugs = [];
        $this->isUnrestrictedPlatformUser = false;
    }

    /**
     * Filament requires this in production (APP_ENV !== 'local') to gate
     * panel access at all — without it, every authenticated user of any
     * guard would be allowed in. The `platform` guard's user provider
     * (config/auth.php) only ever resolves PlatformUser instances, so any
     * authenticated platform user is allowed into the one panel we have;
     * per-resource/action authorization is handled separately via
     * hasPlatformPermission(), matching the same permission slugs the
     * existing Inertia routes already gate on with the
     * `platform.permission:` middleware.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Same `avatar` column/disk the existing Inertia flow's
     * PlatformProfileController::uploadAvatar() writes to (public disk,
     * `avatars/` directory) — this just makes Filament's topbar/account
     * widget render it instead of falling back to initials.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }
}
