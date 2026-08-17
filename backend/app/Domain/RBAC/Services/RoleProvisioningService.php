<?php

namespace App\Domain\RBAC\Services;

use App\Domain\Business\Models\Business;
use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\Role;
use Illuminate\Support\Collection;

/**
 * Creates the default set of system roles for a newly registered business.
 * Owners may later customize permissions per role; the slugs in
 * Role::OWNER etc. remain stable so the platform can always identify the
 * role that should never lose administrative access.
 */
class RoleProvisioningService
{
    /** Sentinel meaning "every tenant-scope .view permission" — resolved dynamically, not hardcoded. */
    private const READ_ONLY_SENTINEL = '*.view';

    /**
     * Role slug => permission slugs granted by default. The '*' sentinel
     * means "every tenant-scope permission" (never platform-scope —
     * a tenant Business Owner must never be granted SuperAdmin-side
     * capabilities).
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
            'branches.view',
            'warehouses.view',
            'inventory.view',
            'products.view',
            'products.create',
            'products.update',
            'categories.view',
            'brands.view',
            'units.view',
            'tags.view',
            'collections.view',
            'attributes.view',
            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'stock_adjustments.view',
            'stock_adjustments.create',
            'stock_adjustments.complete',
            'stock_transfers.view',
            'stock_transfers.create',
            'stock_transfers.dispatch',
            'stock_transfers.receive',
            'inventory_counts.view',
            'inventory_counts.create',
            'inventory_counts.complete',
            'pos.view',
            'sales.view',
            'sales.create',
            'sales.void',
            'customers.view',
            'customers.create',
            'customers.update',
            'accounting.view',
            'accounting.expenses.manage',
            'accounting.expenses.approve',
            'accounting.income.manage',
            'crm.view',
            'crm.manage',
            'purchase_orders.view',
            'purchase_orders.create',
            'purchase_orders.approve',
            'goods_received.view',
            'goods_received.create',
            'sales_returns.view',
            'sales_returns.create',
            'sales_returns.approve',
            'website.view',
            'website.manage',
        ],
        Role::CASHIER => [
            'dashboard.view',
            'products.view',
            'categories.view',
            'brands.view',
            'pos.view',
            'sales.create',
            'customers.view',
            'customers.create',
            'sales_returns.create',
        ],
        Role::INVENTORY_OFFICER => [
            'dashboard.view',
            'branches.view',
            'warehouses.view',
            'inventory.view',
            'products.view',
            'products.create',
            'products.update',
            'products.import',
            'products.export',
            'categories.view',
            'categories.create',
            'categories.update',
            'brands.view',
            'brands.create',
            'brands.update',
            'units.view',
            'units.create',
            'units.update',
            'tags.view',
            'tags.create',
            'tags.update',
            'collections.view',
            'collections.create',
            'collections.update',
            'attributes.view',
            'attributes.create',
            'attributes.update',
            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'stock_adjustments.view',
            'stock_adjustments.create',
            'stock_adjustments.complete',
            'stock_transfers.view',
            'stock_transfers.create',
            'stock_transfers.dispatch',
            'stock_transfers.receive',
            'inventory_counts.view',
            'inventory_counts.create',
            'inventory_counts.complete',
            'purchase_orders.view',
            'goods_received.view',
            'goods_received.create',
        ],
        Role::ACCOUNTANT => [
            'dashboard.view',
            'subscription.view',
            'inventory.view',
            'products.view',
            'suppliers.view',
            'sales.view',
            'customers.view',
            'accounting.view',
            'accounting.expenses.manage',
            'accounting.expenses.approve',
            'accounting.income.manage',
            'purchase_orders.view',
            'goods_received.view',
            'sales_returns.view',
        ],
        Role::BRANCH_MANAGER => [
            'dashboard.view',
            'business.view',
            'employees.view',
            'branches.view',
            'warehouses.view',
            'inventory.view',
            'products.view',
            'products.update',
            'categories.view',
            'brands.view',
            'suppliers.view',
            'stock_adjustments.view',
            'stock_adjustments.create',
            'stock_transfers.view',
            'stock_transfers.create',
            'stock_transfers.receive',
            'inventory_counts.view',
            'inventory_counts.create',
            'pos.view',
            'sales.view',
            'sales.create',
            'customers.view',
            'customers.create',
            'accounting.view',
            'accounting.expenses.manage',
            'crm.view',
            'crm.manage',
            'purchase_orders.view',
            'purchase_orders.create',
            'goods_received.view',
            'goods_received.create',
            'sales_returns.view',
            'sales_returns.create',
            'sales_returns.approve',
            'website.view',
            'website.manage',
        ],
        Role::PURCHASING_OFFICER => [
            'dashboard.view',
            'branches.view',
            'warehouses.view',
            'products.view',
            'suppliers.view',
            'suppliers.create',
            'suppliers.update',
            'stock_transfers.view',
            'stock_transfers.create',
            'purchase_orders.view',
            'purchase_orders.create',
            'goods_received.view',
            'goods_received.create',
        ],
        Role::SALES_OFFICER => [
            'dashboard.view',
            'products.view',
            'categories.view',
            'customers.view',
            'customers.create',
            'customers.update',
            'sales.view',
            'sales.create',
            'pos.view',
            'crm.view',
            'crm.manage',
            'sales_returns.view',
            'sales_returns.create',
        ],
        Role::CUSTOMER_SUPPORT => [
            'dashboard.view',
            'customers.view',
            'crm.view',
            'crm.manage',
            'sales_returns.view',
            'sales_returns.create',
        ],
        Role::EMPLOYEE => [
            'dashboard.view',
        ],
        // Resolved dynamically below — every tenant-scope `.view` permission.
        Role::READ_ONLY => [self::READ_ONLY_SENTINEL],
    ];

    private const ROLE_NAMES = [
        Role::OWNER => 'Business Owner',
        Role::MANAGER => 'Manager',
        Role::CASHIER => 'Cashier',
        Role::INVENTORY_OFFICER => 'Inventory Officer',
        Role::ACCOUNTANT => 'Accountant',
        Role::BRANCH_MANAGER => 'Branch Manager',
        Role::PURCHASING_OFFICER => 'Purchasing Officer',
        Role::SALES_OFFICER => 'Sales Officer',
        Role::CUSTOMER_SUPPORT => 'Customer Support',
        Role::EMPLOYEE => 'Employee',
        Role::READ_ONLY => 'Read Only User',
    ];

    /**
     * The tenant role catalogue as slug => [name, grants], exposed so
     * RoleTemplateSeeder can publish a matching starter template for
     * each without restating the permission lists here.
     *
     * @return array<string, array{name: string, permissions: list<string>}>
     */
    public static function catalogue(): array
    {
        return collect(self::DEFAULT_GRANTS)
            ->map(fn (array $grants, string $slug): array => [
                'name' => self::ROLE_NAMES[$slug],
                'permissions' => $grants,
            ])
            ->all();
    }

    /**
     * @return Collection<int, Role> the created roles, keyed numerically with the Owner role first.
     */
    public function provisionDefaultRoles(Business $business): Collection
    {
        $tenantPermissions = Permission::query()->where('scope', Permission::SCOPE_TENANT)->get(['id', 'slug', 'action']);
        $tenantPermissionIdsBySlug = $tenantPermissions->pluck('id', 'slug');
        $tenantViewPermissionIds = $tenantPermissions->where('action', 'view')->pluck('id');

        return collect(self::DEFAULT_GRANTS)->map(function (array $grants, string $slug) use ($business, $tenantPermissionIdsBySlug, $tenantViewPermissionIds) {
            $role = Role::query()->create([
                'business_id' => $business->getKey(),
                'name' => self::ROLE_NAMES[$slug],
                'slug' => $slug,
                'is_system' => true,
            ]);

            $permissionIds = match (true) {
                $grants === ['*'] => $tenantPermissionIdsBySlug->values(),
                $grants === [self::READ_ONLY_SENTINEL] => $tenantViewPermissionIds->values(),
                default => $tenantPermissionIdsBySlug->only($grants)->values(),
            };

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
