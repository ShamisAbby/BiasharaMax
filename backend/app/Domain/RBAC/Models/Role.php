<?php

namespace App\Domain\RBAC\Models;

use App\Domain\Authentication\Models\User;
use App\Domain\Shared\Concerns\Auditable;
use App\Domain\Shared\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use Auditable, BelongsToTenant, HasUuids;

    /** System role slugs seeded automatically for every new business. */
    public const OWNER = 'business-owner';

    public const MANAGER = 'manager';

    public const CASHIER = 'cashier';

    public const INVENTORY_OFFICER = 'inventory-officer';

    public const ACCOUNTANT = 'accountant';

    public const BRANCH_MANAGER = 'branch-manager';

    public const PURCHASING_OFFICER = 'purchasing-officer';

    public const SALES_OFFICER = 'sales-officer';

    public const CUSTOMER_SUPPORT = 'customer-support';

    public const EMPLOYEE = 'employee';

    public const READ_ONLY = 'read-only-user';

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

    /**
     * Read through the pivot, not the legacy `role_id` column — see the
     * note on PlatformRole::platformUsers().
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user')->withTimestamps();
    }
}
