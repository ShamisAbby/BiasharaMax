<?php

namespace Database\Seeders;

use App\Modules\RBAC\Models\Permission;
use App\Modules\RBAC\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * The permissions available so far. Each module added in a future
     * sprint will register its own permissions via its own seeder.
     *
     * @var array<int, array{module: string, slug: string, name: string}>
     */
    private const PERMISSIONS = [
        ['module' => 'dashboard', 'slug' => 'dashboard.view', 'name' => 'View Dashboard'],
        ['module' => 'business', 'slug' => 'business.view', 'name' => 'View Business Profile'],
        ['module' => 'business', 'slug' => 'business.update', 'name' => 'Update Business Settings'],
        ['module' => 'employees', 'slug' => 'employees.view', 'name' => 'View Employees'],
        ['module' => 'employees', 'slug' => 'employees.create', 'name' => 'Invite Employees'],
        ['module' => 'employees', 'slug' => 'employees.update', 'name' => 'Update Employees'],
        ['module' => 'employees', 'slug' => 'employees.delete', 'name' => 'Remove Employees'],
        ['module' => 'roles', 'slug' => 'roles.view', 'name' => 'View Roles & Permissions'],
        ['module' => 'roles', 'slug' => 'roles.create', 'name' => 'Create Roles'],
        ['module' => 'roles', 'slug' => 'roles.update', 'name' => 'Update Roles'],
        ['module' => 'roles', 'slug' => 'roles.delete', 'name' => 'Delete Roles'],
        ['module' => 'subscription', 'slug' => 'subscription.view', 'name' => 'View Subscription'],
        ['module' => 'subscription', 'slug' => 'subscription.manage', 'name' => 'Manage Subscription & Billing'],
        ['module' => 'branches', 'slug' => 'branches.view', 'name' => 'View Branches'],
        ['module' => 'branches', 'slug' => 'branches.create', 'name' => 'Create Branches'],
        ['module' => 'branches', 'slug' => 'branches.update', 'name' => 'Update Branches'],
        ['module' => 'branches', 'slug' => 'branches.delete', 'name' => 'Delete Branches'],
        ['module' => 'warehouses', 'slug' => 'warehouses.view', 'name' => 'View Warehouses'],
        ['module' => 'warehouses', 'slug' => 'warehouses.create', 'name' => 'Create Warehouses'],
        ['module' => 'warehouses', 'slug' => 'warehouses.update', 'name' => 'Update Warehouses'],
        ['module' => 'warehouses', 'slug' => 'warehouses.delete', 'name' => 'Delete Warehouses'],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                ['module' => $permission['module'], 'name' => $permission['name']],
            );
        }

        $this->syncOwnerRolesWithAllPermissions();
    }

    /**
     * The Business Owner role is defined as "full access" by definition, not
     * as a per-business customization. Whenever a new permission is added by
     * a later sprint, every existing business's Owner role must receive it
     * automatically so an owner never loses access to a capability their
     * business already pays for. Other system roles (Manager, Cashier, ...)
     * are intentionally left untouched here, since their permissions may
     * have already been deliberately customized by the business owner.
     */
    private function syncOwnerRolesWithAllPermissions(): void
    {
        $allPermissionIds = Permission::query()->pluck('id');

        Role::query()
            ->where('slug', Role::OWNER)
            ->where('is_system', true)
            ->each(fn (Role $role) => $role->permissions()->sync($allPermissionIds));
    }
}
