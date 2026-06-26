<?php

namespace Database\Seeders;

use App\Modules\RBAC\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * The permissions available in Sprint 1. Each module added in a future
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
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                ['module' => $permission['module'], 'name' => $permission['name']],
            );
        }
    }
}
