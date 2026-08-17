<?php

namespace App\Domain\RBAC\Models;

use App\Domain\Authentication\Models\PlatformUser;
use Database\Factories\PlatformRoleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Platform-level RBAC. Before this, every PlatformUser had identical,
 * unrestricted access — this is the first time that's been
 * differentiated.
 */
class PlatformRole extends Model
{
    use HasFactory, HasUuids;

    /** Seeded by PlatformRoleProvisioningService; granted every permission. */
    public const SUPER_ADMIN = 'super-admin';

    /** Seeded by PlatformRoleProvisioningService; a narrower operational role. */
    public const PLATFORM_ADMIN = 'platform-admin';

    protected $fillable = [
        'name',
        'slug',
        'is_system',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'platform_permission_role');
    }

    /**
     * Users holding this role, read through the pivot rather than the
     * legacy `platform_role_id` column — that column is no longer the
     * source of truth, so a HasMany on it would report a role as unused
     * while people still hold it, and the "in use" delete guard would
     * let it be deleted out from under them.
     */
    public function platformUsers(): BelongsToMany
    {
        return $this->belongsToMany(PlatformUser::class, 'platform_role_platform_user')
            ->withTimestamps();
    }

    protected static function newFactory(): PlatformRoleFactory
    {
        return PlatformRoleFactory::new();
    }
}
