<?php

namespace App\Domain\ModuleManagement\Support;

/**
 * The ten sections of the vendor dashboard, as the Super Admin sees them.
 *
 * These slugs are the contract between three places that must agree or the
 * feature silently half-works: the module registry rows, the route groups
 * that enforce access, and the sidebar that hides what you can't reach. A
 * typo in any one of them produces a section that is hidden but reachable,
 * or blocked but still advertised — so they are named here once.
 */
final class DashboardModule
{
    public const BUSINESS = 'business';

    public const INVENTORY = 'inventory';

    public const PURCHASING = 'purchasing';

    public const SALES = 'sales';

    public const FINANCE = 'finance';

    public const CRM = 'crm';

    public const WEBSITE = 'website';

    public const EMPLOYEES = 'employees';

    public const REPORTS = 'reports';

    public const SETTINGS = 'settings';

    /**
     * Slug => catalogue metadata, used to seed the registry.
     *
     * `sort_order` matches the sidebar so the Super Admin's list reads in
     * the same order as the thing they are switching off.
     *
     * @return array<string, array{name: string, description: string, icon: string, category: string, sort_order: int}>
     */
    public static function catalogue(): array
    {
        return [
            self::BUSINESS => [
                'name' => 'Business',
                'description' => 'Business profile, branches and warehouses.',
                'icon' => 'building-office-2',
                'category' => 'core',
                'sort_order' => 10,
            ],
            self::INVENTORY => [
                'name' => 'Inventory',
                'description' => 'Products, categories, stock adjustments, transfers and counts.',
                'icon' => 'cube',
                'category' => 'operations',
                'sort_order' => 20,
            ],
            self::PURCHASING => [
                'name' => 'Purchasing',
                'description' => 'Suppliers, purchase orders and goods received.',
                'icon' => 'truck',
                'category' => 'operations',
                'sort_order' => 30,
            ],
            self::SALES => [
                'name' => 'Sales',
                'description' => 'Point of sale, orders and returns.',
                'icon' => 'shopping-cart',
                'category' => 'operations',
                'sort_order' => 40,
            ],
            self::FINANCE => [
                'name' => 'Finance',
                'description' => 'Accounting, journal, chart of accounts, banking and tax.',
                'icon' => 'calculator',
                'category' => 'finance',
                'sort_order' => 50,
            ],
            self::CRM => [
                'name' => 'Customers & CRM',
                'description' => 'Customers, groups, loyalty, campaigns and feedback.',
                'icon' => 'user-group',
                'category' => 'growth',
                'sort_order' => 60,
            ],
            self::WEBSITE => [
                'name' => 'Website & Online Store',
                'description' => 'Public website, storefront, blog and enquiries.',
                'icon' => 'globe-alt',
                'category' => 'growth',
                'sort_order' => 70,
            ],
            self::EMPLOYEES => [
                'name' => 'Employees',
                'description' => 'Staff, attendance, leave and payroll.',
                'icon' => 'users',
                'category' => 'people',
                'sort_order' => 80,
            ],
            self::REPORTS => [
                'name' => 'Reports',
                'description' => 'The cross-module report centre.',
                'icon' => 'chart-bar',
                'category' => 'insight',
                'sort_order' => 90,
            ],
            self::SETTINGS => [
                'name' => 'Settings',
                'description' => 'Roles, permissions, subscription and backups.',
                'icon' => 'cog-6-tooth',
                'category' => 'core',
                'sort_order' => 100,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::catalogue());
    }
}
