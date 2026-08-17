<?php

namespace App\Domain\Authentication\Models;

use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Business;
use App\Domain\RBAC\Models\Role;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\HasUserstamps;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasUserstamps, HasUuids, Notifiable, SoftDeletes;

    public const STATUS_INVITED = 'invited';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'business_id',
        'role_id',
        'branch_id',
        'invited_by',
        'name',
        'username',
        'email',
        'avatar',
        'phone',
        'password',
        'status',
    ];

    /**
     * Per-instance authorization cache — see resolvePermissions().
     * Declared properties, so Eloquent never treats them as attributes.
     *
     * @var list<string>
     */
    private array $grantedPermissionSlugs = [];

    private bool $permissionsResolved = false;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['avatar_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(
            fn () => $this->avatar ? Storage::url($this->avatar) : null
        );
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @deprecated Superseded by roles(). The `role_id` column still
     * exists and is still populated for rollback safety, but nothing
     * reads it for authorization any more — see the 2026_08_06 pivot
     * migration. Kept only so existing callers don't break while they
     * are migrated.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /**
     * Keeps the pivot in step with any write to the legacy `role_id`
     * column, so the many callers that still assign a role the old way
     * (seeders, tests, older controllers) keep granting the permissions
     * they always did now that authorization reads only the pivot.
     * Without this an employee assigned a role the old way would end up
     * with no permissions at all.
     *
     * Only fires when the column actually changed, so it can't clobber a
     * multi-role assignment made through the pivot directly.
     */
    protected static function booted(): void
    {
        static::saved(function (self $user): void {
            // `wasChanged()` is only meaningful for an update, so a fresh
            // insert has to be handled explicitly.
            if (! $user->wasRecentlyCreated && ! $user->wasChanged('role_id')) {
                return;
            }

            if ($user->wasRecentlyCreated && $user->role_id === null) {
                return;
            }

            $user->roles()->sync(array_filter([$user->role_id]));
            $user->forgetPermissionCache();
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isOwnerOf(Business $business): bool
    {
        return $business->owner_id === $this->getKey();
    }

    /**
     * Permissions are the UNION of every assigned role: an employee who
     * is both Cashier and Inventory Officer gets both sets, and the most
     * permissive role wins. There is no per-user deny.
     *
     * Unlike the platform side, a tenant user with no roles has NO
     * permissions rather than unrestricted access — that matches the
     * previous behaviour here, where a null `role_id` denied everything.
     */
    public function hasPermission(string $permissionSlug): bool
    {
        $this->resolvePermissions();

        return in_array($permissionSlug, $this->grantedPermissionSlugs, true);
    }

    /**
     * Memoised for the same reason as the platform equivalent: this is
     * called repeatedly while rendering a single page, and the separate
     * `resolved` flag keeps a legitimately empty permission set from
     * being re-queried on every check.
     */
    private function resolvePermissions(): void
    {
        if ($this->permissionsResolved) {
            return;
        }

        $this->grantedPermissionSlugs = $this->roles()
            ->with('permissions:id,slug')
            ->get()
            ->flatMap(fn (Role $role): array => $role->permissions->pluck('slug')->all())
            ->unique()
            ->values()
            ->all();

        $this->permissionsResolved = true;
    }

    /**
     * Drops the memoised permission set, so a role change takes effect
     * within the same request that made it.
     */
    public function forgetPermissionCache(): void
    {
        $this->permissionsResolved = false;
        $this->grantedPermissionSlugs = [];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
