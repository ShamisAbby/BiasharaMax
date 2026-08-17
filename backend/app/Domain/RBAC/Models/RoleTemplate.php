<?php

namespace App\Domain\RBAC\Models;

use App\Domain\Authentication\Models\PlatformUser;
use Database\Factories\RoleTemplateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A reusable starter permission set a SuperAdmin can apply when creating
 * a role — distinct from RoleProvisioningService's auto-provisioned
 * default roles, which apply automatically at business registration.
 */
class RoleTemplate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'scope',
        'description',
        'is_system',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_role_template');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'created_by');
    }

    protected static function newFactory(): RoleTemplateFactory
    {
        return RoleTemplateFactory::new();
    }
}
