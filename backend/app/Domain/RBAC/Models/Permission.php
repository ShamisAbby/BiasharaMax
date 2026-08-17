<?php

namespace App\Domain\RBAC\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasUuids;

    public const SCOPE_TENANT = 'tenant';

    public const SCOPE_PLATFORM = 'platform';

    /**
     * `action` is intentionally absent — it's a PostgreSQL generated
     * column (see migration 2026_06_27_134939) derived from `slug`, so
     * it's never mass-assigned.
     */
    protected $fillable = [
        'module',
        'scope',
        'name',
        'slug',
        'description',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role');
    }

    public function platformRoles(): BelongsToMany
    {
        return $this->belongsToMany(PlatformRole::class, 'platform_permission_role');
    }

    public function roleTemplates(): BelongsToMany
    {
        return $this->belongsToMany(RoleTemplate::class, 'permission_role_template');
    }
}
