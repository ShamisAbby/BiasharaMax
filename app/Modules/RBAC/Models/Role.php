<?php

namespace App\Modules\RBAC\Models;

use App\Modules\Authentication\Models\User;
use App\Modules\Shared\Concerns\Auditable;
use App\Modules\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    /** System role slugs seeded automatically for every new business. */
    public const OWNER = 'business-owner';

    public const MANAGER = 'manager';

    public const CASHIER = 'cashier';

    public const INVENTORY_OFFICER = 'inventory-officer';

    public const ACCOUNTANT = 'accountant';

    protected $fillable = [
        'business_id',
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
        return $this->belongsToMany(Permission::class, 'permission_role');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
