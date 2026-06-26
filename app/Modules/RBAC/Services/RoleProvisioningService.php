<?php

namespace App\Modules\RBAC\Services;

use App\Modules\Business\Models\Business;
use App\Modules\RBAC\Models\Permission;
use App\Modules\RBAC\Models\Role;
use Illuminate\Support\Collection;

/**
 * Creates the default set of system roles for a newly registered business.
 * Owners may later customize permissions per role; the slugs in
 * Role::OWNER etc. remain stable so the platform can always identify the
 * role that should never lose administrative access.
 */
class RoleProvisioningService
{
    /**
     * Role slug => permission slugs granted by default.
     *
     * @var array<string, array<int, string>>
     */
    private const DEFAULT_GRANTS = [
        Role::OWNER => ['*'],
        Role::MANAGER => [
            'dashboard.view',
            'business.view',
            'employees.view',
            'employees.create',
            'employees.update',
            'roles.view',
            'subscription.view',
        ],
        Role::CASHIER => [
            'dashboard.view',
        ],
        Role::INVENTORY_OFFICER => [
            'dashboard.view',
        ],
        Role::ACCOUNTANT => [
            'dashboard.view',
            'subscription.view',
        ],
    ];

    private const ROLE_NAMES = [
        Role::OWNER => 'Business Owner',
        Role::MANAGER => 'Manager',
        Role::CASHIER => 'Cashier',
        Role::INVENTORY_OFFICER => 'Inventory Officer',
        Role::ACCOUNTANT => 'Accountant',
    ];

    /**
     * @return Collection<int, Role> the created roles, keyed numerically with the Owner role first.
     */
    public function provisionDefaultRoles(Business $business): Collection
    {
        $allPermissionIds = Permission::query()->pluck('id', 'slug');

        return collect(self::DEFAULT_GRANTS)->map(function (array $grants, string $slug) use ($business, $allPermissionIds) {
            $role = Role::query()->create([
                'business_id' => $business->getKey(),
                'name' => self::ROLE_NAMES[$slug],
                'slug' => $slug,
                'is_system' => true,
            ]);

            $permissionIds = $grants === ['*']
                ? $allPermissionIds->values()
                : $allPermissionIds->only($grants)->values();

            $role->permissions()->sync($permissionIds);

            return $role;
        })->values();
    }

    public function ownerRoleFor(Business $business): Role
    {
        return Role::query()
            ->where('business_id', $business->getKey())
            ->where('slug', Role::OWNER)
            ->firstOrFail();
    }
}
