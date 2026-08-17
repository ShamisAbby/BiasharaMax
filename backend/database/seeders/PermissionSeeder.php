<?php

namespace Database\Seeders;

use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Tenant-scope permissions for modules that exist and are real today.
     *
     * @var array<int, array{module: string, slug: string, name: string}>
     */
    private const TENANT_PERMISSIONS = [
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

        // Business-scope backup. Distinct from the platform's
        // `backups.manage`: a vendor exports and restores only their own
        // records, never the database.
        ['module' => 'backups', 'slug' => 'backups.view', 'name' => 'View Business Backups'],
        ['module' => 'backups', 'slug' => 'backups.create', 'name' => 'Export Business Backup'],
        ['module' => 'backups', 'slug' => 'backups.restore', 'name' => 'Restore Business From Backup'],

        ['module' => 'inventory', 'slug' => 'inventory.view', 'name' => 'View Inventory Dashboard & Reports'],

        ['module' => 'products', 'slug' => 'products.view', 'name' => 'View Products'],
        ['module' => 'products', 'slug' => 'products.create', 'name' => 'Create Products'],
        ['module' => 'products', 'slug' => 'products.update', 'name' => 'Update Products'],
        ['module' => 'products', 'slug' => 'products.delete', 'name' => 'Delete Products'],
        ['module' => 'products', 'slug' => 'products.import', 'name' => 'Bulk Import Products'],
        ['module' => 'products', 'slug' => 'products.export', 'name' => 'Bulk Export Products'],

        ['module' => 'categories', 'slug' => 'categories.view', 'name' => 'View Categories'],
        ['module' => 'categories', 'slug' => 'categories.create', 'name' => 'Create Categories'],
        ['module' => 'categories', 'slug' => 'categories.update', 'name' => 'Update Categories'],
        ['module' => 'categories', 'slug' => 'categories.delete', 'name' => 'Delete Categories'],

        ['module' => 'brands', 'slug' => 'brands.view', 'name' => 'View Brands'],
        ['module' => 'brands', 'slug' => 'brands.create', 'name' => 'Create Brands'],
        ['module' => 'brands', 'slug' => 'brands.update', 'name' => 'Update Brands'],
        ['module' => 'brands', 'slug' => 'brands.delete', 'name' => 'Delete Brands'],

        ['module' => 'units', 'slug' => 'units.view', 'name' => 'View Units'],
        ['module' => 'units', 'slug' => 'units.create', 'name' => 'Create Units'],
        ['module' => 'units', 'slug' => 'units.update', 'name' => 'Update Units'],
        ['module' => 'units', 'slug' => 'units.delete', 'name' => 'Delete Units'],

        ['module' => 'tags', 'slug' => 'tags.view', 'name' => 'View Tags'],
        ['module' => 'tags', 'slug' => 'tags.create', 'name' => 'Create Tags'],
        ['module' => 'tags', 'slug' => 'tags.update', 'name' => 'Update Tags'],
        ['module' => 'tags', 'slug' => 'tags.delete', 'name' => 'Delete Tags'],

        ['module' => 'collections', 'slug' => 'collections.view', 'name' => 'View Collections'],
        ['module' => 'collections', 'slug' => 'collections.create', 'name' => 'Create Collections'],
        ['module' => 'collections', 'slug' => 'collections.update', 'name' => 'Update Collections'],
        ['module' => 'collections', 'slug' => 'collections.delete', 'name' => 'Delete Collections'],

        ['module' => 'attributes', 'slug' => 'attributes.view', 'name' => 'View Attributes'],
        ['module' => 'attributes', 'slug' => 'attributes.create', 'name' => 'Create Attributes'],
        ['module' => 'attributes', 'slug' => 'attributes.update', 'name' => 'Update Attributes'],
        ['module' => 'attributes', 'slug' => 'attributes.delete', 'name' => 'Delete Attributes'],

        ['module' => 'suppliers', 'slug' => 'suppliers.view', 'name' => 'View Suppliers'],
        ['module' => 'suppliers', 'slug' => 'suppliers.create', 'name' => 'Create Suppliers'],
        ['module' => 'suppliers', 'slug' => 'suppliers.update', 'name' => 'Update Suppliers'],
        ['module' => 'suppliers', 'slug' => 'suppliers.delete', 'name' => 'Delete Suppliers'],

        ['module' => 'stock_adjustments', 'slug' => 'stock_adjustments.view', 'name' => 'View Stock Adjustments'],
        ['module' => 'stock_adjustments', 'slug' => 'stock_adjustments.create', 'name' => 'Create Stock Adjustments'],
        ['module' => 'stock_adjustments', 'slug' => 'stock_adjustments.complete', 'name' => 'Complete Stock Adjustments'],
        ['module' => 'stock_adjustments', 'slug' => 'stock_adjustments.delete', 'name' => 'Delete Stock Adjustments'],

        ['module' => 'stock_transfers', 'slug' => 'stock_transfers.view', 'name' => 'View Stock Transfers'],
        ['module' => 'stock_transfers', 'slug' => 'stock_transfers.create', 'name' => 'Create Stock Transfers'],
        ['module' => 'stock_transfers', 'slug' => 'stock_transfers.dispatch', 'name' => 'Dispatch Stock Transfers'],
        ['module' => 'stock_transfers', 'slug' => 'stock_transfers.receive', 'name' => 'Receive Stock Transfers'],
        ['module' => 'stock_transfers', 'slug' => 'stock_transfers.cancel', 'name' => 'Cancel Stock Transfers'],

        ['module' => 'inventory_counts', 'slug' => 'inventory_counts.view', 'name' => 'View Inventory Counts'],
        ['module' => 'inventory_counts', 'slug' => 'inventory_counts.create', 'name' => 'Start Inventory Counts'],
        ['module' => 'inventory_counts', 'slug' => 'inventory_counts.complete', 'name' => 'Complete Inventory Counts'],

        // Sales & POS module (Sprint 4) — pos.view is deliberately separate
        // from sales.view: a cashier can be granted POS terminal access and
        // sales.create without seeing the full back-office sales ledger.
        ['module' => 'sales', 'slug' => 'sales.view', 'name' => 'View Sales Orders'],
        ['module' => 'sales', 'slug' => 'sales.create', 'name' => 'Create Sales & Process POS Checkouts'],
        ['module' => 'sales', 'slug' => 'sales.void', 'name' => 'Void Sales'],
        ['module' => 'pos', 'slug' => 'pos.view', 'name' => 'Access POS Terminal'],

        // Sales Returns — sales_returns.create covers requesting a return;
        // approve is separate so a cashier can log a return request
        // without being able to approve their own refund.
        ['module' => 'sales_returns', 'slug' => 'sales_returns.view', 'name' => 'View Sales Returns'],
        ['module' => 'sales_returns', 'slug' => 'sales_returns.create', 'name' => 'Request Sales Returns'],
        ['module' => 'sales_returns', 'slug' => 'sales_returns.approve', 'name' => 'Approve or Reject Sales Returns'],

        ['module' => 'customers', 'slug' => 'customers.view', 'name' => 'View Customers & Debt Balances'],
        ['module' => 'customers', 'slug' => 'customers.create', 'name' => 'Create Customers'],
        ['module' => 'customers', 'slug' => 'customers.update', 'name' => 'Update Customers'],

        // Accounting module (Expenses + Income + P&L) — accounting.view gates
        // the dashboard and P&L report; the manage/approve verbs are split so
        // a bookkeeper can log expenses without being able to approve their own.
        ['module' => 'accounting', 'slug' => 'accounting.view', 'name' => 'View Accounting Dashboard & Reports'],
        ['module' => 'accounting', 'slug' => 'accounting.expenses.manage', 'name' => 'Create & Update Expenses'],
        ['module' => 'accounting', 'slug' => 'accounting.expenses.approve', 'name' => 'Approve or Reject Expenses'],
        ['module' => 'accounting', 'slug' => 'accounting.income.manage', 'name' => 'Create & Update Income'],

        // Finance module (General Ledger: Chart of Accounts, Journal Entries,
        // GL reports) — distinct from accounting.* above, which gates the
        // existing bookkeeping (Expenses/Income) tools. finance.journal.manage
        // (create/edit/void drafts) is split from finance.journal.post (commit
        // a draft to the ledger) so a bookkeeper can prepare entries without
        // being able to post them unreviewed.
        ['module' => 'finance', 'slug' => 'finance.view', 'name' => 'View Finance Dashboard, Ledger & Reports'],
        ['module' => 'finance', 'slug' => 'finance.journal.manage', 'name' => 'Create, Update & Void Draft Journal Entries'],
        ['module' => 'finance', 'slug' => 'finance.journal.post', 'name' => 'Post & Reverse Journal Entries'],
        ['module' => 'finance', 'slug' => 'finance.chart-of-accounts.manage', 'name' => 'Manage Chart of Accounts'],
        ['module' => 'finance', 'slug' => 'finance.supplier-payments.manage', 'name' => 'Record Supplier Payments'],

        // CRM module (Customer 360: Groups/Tags/Notes/Loyalty) — crm.view
        // gates the CRM dashboard and a customer's CRM profile; crm.manage
        // covers notes, tags, group assignment and loyalty point
        // adjustments, kept separate from customers.update (Sales module)
        // so a Customer Support role can manage CRM data without the
        // broader sales-side customer edit permission.
        ['module' => 'crm', 'slug' => 'crm.view', 'name' => 'View CRM Dashboard & Customer Profiles'],
        ['module' => 'crm', 'slug' => 'crm.manage', 'name' => 'Manage Customer Notes, Tags, Groups & Loyalty Points'],

        // Website module — the business's own editable site (seeded from
        // the WebsiteTemplate assigned to its BusinessType). .view covers
        // the dashboard and page content; .manage covers editing pages,
        // SEO fields and publish/unpublish.
        ['module' => 'website', 'slug' => 'website.view', 'name' => 'View Website Dashboard & Pages'],
        ['module' => 'website', 'slug' => 'website.manage', 'name' => 'Edit Website Pages & Publish/Unpublish'],

        // Purchasing module (Purchase Orders + Goods Received) —
        // purchase_orders.create covers create/edit/duplicate/cancel;
        // approve is separate so a Purchasing Officer can build a PO
        // without being able to approve their own spend.
        ['module' => 'purchase_orders', 'slug' => 'purchase_orders.view', 'name' => 'View Purchase Orders & Purchasing Dashboard'],
        ['module' => 'purchase_orders', 'slug' => 'purchase_orders.create', 'name' => 'Create, Edit & Cancel Purchase Orders'],
        ['module' => 'purchase_orders', 'slug' => 'purchase_orders.approve', 'name' => 'Approve or Reject Purchase Orders'],
        ['module' => 'goods_received', 'slug' => 'goods_received.view', 'name' => 'View Goods Received Notes'],
        ['module' => 'goods_received', 'slug' => 'goods_received.create', 'name' => 'Record Goods Received'],

        // Finance Phase 2 — Cash & Bank Management
        ['module' => 'finance', 'slug' => 'finance.bank-accounts.manage', 'name' => 'Manage Bank Accounts & Transfers'],
        ['module' => 'finance', 'slug' => 'finance.bank-accounts.reconcile', 'name' => 'Reconcile Bank Accounts'],

        // Finance Phase 3 — Financial Periods
        ['module' => 'finance', 'slug' => 'finance.periods.manage', 'name' => 'Manage Accounting Periods'],
        ['module' => 'finance', 'slug' => 'finance.periods.close', 'name' => 'Lock & Close Accounting Periods'],

        // Finance Phase 4 — Budgets
        ['module' => 'finance', 'slug' => 'finance.budgets.manage', 'name' => 'Create & Edit Budgets'],
        ['module' => 'finance', 'slug' => 'finance.budgets.approve', 'name' => 'Approve & Activate Budgets'],

        // Finance Phase 5 — Tax Management
        ['module' => 'finance', 'slug' => 'finance.tax.manage', 'name' => 'Configure Business Tax Settings'],
        ['module' => 'finance', 'slug' => 'finance.tax.returns.view', 'name' => 'View Tax Returns & Summaries'],

        // Finance Phase 6 — Fixed Assets
        ['module' => 'finance', 'slug' => 'finance.assets.manage', 'name' => 'Manage Fixed Assets'],
        ['module' => 'finance', 'slug' => 'finance.assets.depreciate', 'name' => 'Post Depreciation Entries'],

        // Payroll Phase 7
        ['module' => 'payroll', 'slug' => 'payroll.view', 'name' => 'View Payroll Dashboard & Reports'],
        ['module' => 'payroll', 'slug' => 'payroll.manage', 'name' => 'Manage Employees & Payroll Periods'],
        ['module' => 'payroll', 'slug' => 'payroll.approve', 'name' => 'Approve Payroll Periods'],
        ['module' => 'payroll', 'slug' => 'payroll.process', 'name' => 'Process & Pay Payroll'],

        // HRM — Attendance
        ['module' => 'attendance', 'slug' => 'attendance.view', 'name' => 'View Attendance Records'],
        ['module' => 'attendance', 'slug' => 'attendance.manage', 'name' => 'Manage Attendance (Clock In/Out, Manual Records)'],
        ['module' => 'attendance', 'slug' => 'attendance.approve', 'name' => 'Approve Attendance Corrections'],

        // HRM — Leave
        ['module' => 'leave', 'slug' => 'leave.view', 'name' => 'View All Leave Requests'],
        ['module' => 'leave', 'slug' => 'leave.manage', 'name' => 'Manage Leave Types & Policies'],
        ['module' => 'leave', 'slug' => 'leave.approve', 'name' => 'Approve or Reject Leave Requests'],
    ];

    /**
     * Tenant-scope placeholders for modules in the platform's long-term
     * roadmap that aren't built yet (Sales, POS, CRM, ...). A single
     * `.view` slug each — enough to exist in a Permission Matrix UI
     * without pretending there's real enforcement behind it. Wiring
     * real create/update/delete verbs is each module's own job once it
     * actually ships.
     *
     * @var array<int, array{module: string, slug: string, name: string}>
     */
    private const TENANT_PLACEHOLDER_PERMISSIONS = [
        ['module' => 'reports', 'slug' => 'reports.view', 'name' => 'View Reports'],
        ['module' => 'notifications', 'slug' => 'notifications.view', 'name' => 'View Notification Center'],
        ['module' => 'system', 'slug' => 'system.view', 'name' => 'View System Status'],
        ['module' => 'api', 'slug' => 'api.view', 'name' => 'View API Access'],
        ['module' => 'ai', 'slug' => 'ai.view', 'name' => 'View AI Assistant'],
    ];

    /**
     * Platform-scope permissions — gate the SuperAdmin dashboard's own
     * features. Only Roles & Permissions, Business Types and Modules
     * are actually enforced (via the `platform.permission` middleware);
     * the rest exist as real catalog rows for the Permission Matrix but
     * aren't yet wired onto routes, matching those areas' current
     * "any platform user" access model.
     *
     * @var array<int, array{module: string, slug: string, name: string}>
     */
    private const PLATFORM_PERMISSIONS = [
        ['module' => 'platform_roles', 'slug' => 'platform_roles.view', 'name' => 'View Platform Roles'],
        ['module' => 'platform_roles', 'slug' => 'platform_roles.create', 'name' => 'Create Platform Roles'],
        ['module' => 'platform_roles', 'slug' => 'platform_roles.update', 'name' => 'Update Platform Roles'],
        ['module' => 'platform_roles', 'slug' => 'platform_roles.delete', 'name' => 'Delete Platform Roles'],
        ['module' => 'platform_roles', 'slug' => 'platform_roles.manage', 'name' => 'Manage Platform Roles & Permissions'],

        ['module' => 'role_templates', 'slug' => 'role_templates.view', 'name' => 'View Role Templates'],
        ['module' => 'role_templates', 'slug' => 'role_templates.manage', 'name' => 'Manage Role Templates'],

        ['module' => 'business_types', 'slug' => 'business_types.view', 'name' => 'View Business Types'],
        ['module' => 'business_types', 'slug' => 'business_types.create', 'name' => 'Create Business Types'],
        ['module' => 'business_types', 'slug' => 'business_types.update', 'name' => 'Update Business Types'],
        ['module' => 'business_types', 'slug' => 'business_types.delete', 'name' => 'Delete Business Types'],
        ['module' => 'business_types', 'slug' => 'business_types.archive', 'name' => 'Archive Business Types'],
        ['module' => 'business_types', 'slug' => 'business_types.manage', 'name' => 'Manage Business Types'],

        ['module' => 'modules', 'slug' => 'modules.view', 'name' => 'View Modules'],
        ['module' => 'modules', 'slug' => 'modules.create', 'name' => 'Create Modules'],
        ['module' => 'modules', 'slug' => 'modules.update', 'name' => 'Update Modules'],
        ['module' => 'modules', 'slug' => 'modules.delete', 'name' => 'Delete Modules'],
        ['module' => 'modules', 'slug' => 'modules.manage', 'name' => 'Manage Modules'],

        ['module' => 'businesses', 'slug' => 'businesses.manage', 'name' => 'Manage Businesses'],
        ['module' => 'platform_users', 'slug' => 'platform_users.manage', 'name' => 'Manage Platform Users'],
        ['module' => 'subscriptions', 'slug' => 'subscriptions.manage', 'name' => 'Manage Subscriptions'],
        ['module' => 'licenses', 'slug' => 'licenses.manage', 'name' => 'Manage Licenses'],
        ['module' => 'audit_logs', 'slug' => 'audit_logs.view', 'name' => 'View Audit Logs'],

        ['module' => 'payments', 'slug' => 'payments.view', 'name' => 'View Payments'],
        ['module' => 'payments', 'slug' => 'payments.manage', 'name' => 'Manage Payments'],
        ['module' => 'payments', 'slug' => 'payments.refund', 'name' => 'Refund Payments'],

        ['module' => 'payment_gateways', 'slug' => 'payment_gateways.view', 'name' => 'View Payment Gateways'],
        ['module' => 'payment_gateways', 'slug' => 'payment_gateways.manage', 'name' => 'Manage Payment Gateways'],

        ['module' => 'finance_reports', 'slug' => 'finance_reports.view', 'name' => 'View Finance Reports'],
        ['module' => 'finance_reports', 'slug' => 'finance_reports.export', 'name' => 'Export Finance Reports'],

        ['module' => 'website_templates', 'slug' => 'website_templates.view', 'name' => 'View Website Templates'],
        ['module' => 'website_templates', 'slug' => 'website_templates.manage', 'name' => 'Manage Website Templates'],

        ['module' => 'platform_notifications', 'slug' => 'platform_notifications.view', 'name' => 'View Notification Center'],
        ['module' => 'platform_notifications', 'slug' => 'platform_notifications.manage', 'name' => 'Manage Notification Channels & Templates'],
        ['module' => 'platform_notifications', 'slug' => 'platform_notifications.send', 'name' => 'Send Notification Campaigns'],

        ['module' => 'support', 'slug' => 'support.view', 'name' => 'View Support Center'],
        ['module' => 'support', 'slug' => 'support.manage', 'name' => 'Manage Support Tickets & Knowledge Base'],

        ['module' => 'audit_logs', 'slug' => 'audit_logs.export', 'name' => 'Export Audit Logs'],

        ['module' => 'security', 'slug' => 'security.view', 'name' => 'View Security Center'],
        ['module' => 'security', 'slug' => 'security.manage', 'name' => 'Manage Security Settings (block IPs, unlock accounts)'],

        ['module' => 'monitoring', 'slug' => 'monitoring.view', 'name' => 'View System Monitoring'],

        ['module' => 'developer', 'slug' => 'developer.view', 'name' => 'View Developer Center'],
        ['module' => 'developer', 'slug' => 'developer.manage', 'name' => 'Manage Webhooks & Developer Tools'],

        ['module' => 'platform_settings', 'slug' => 'platform_settings.view', 'name' => 'View Platform Settings'],
        ['module' => 'platform_settings', 'slug' => 'platform_settings.manage', 'name' => 'Manage Platform Settings'],

        ['module' => 'backups', 'slug' => 'backups.manage', 'name' => 'Manage Backups & Restore'],

        ['module' => 'ai_insights', 'slug' => 'ai_insights.view', 'name' => 'View AI Insights'],

        ['module' => 'integrations', 'slug' => 'integrations.view', 'name' => 'View Integrations'],
        ['module' => 'integrations', 'slug' => 'integrations.manage', 'name' => 'Manage Integrations'],
    ];

    public function run(): void
    {
        foreach (self::TENANT_PERMISSIONS as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                ['module' => $permission['module'], 'name' => $permission['name'], 'scope' => Permission::SCOPE_TENANT],
            );
        }

        foreach (self::TENANT_PLACEHOLDER_PERMISSIONS as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                ['module' => $permission['module'], 'name' => $permission['name'], 'scope' => Permission::SCOPE_TENANT],
            );
        }

        foreach (self::PLATFORM_PERMISSIONS as $permission) {
            Permission::query()->updateOrCreate(
                ['slug' => $permission['slug']],
                ['module' => $permission['module'], 'name' => $permission['name'], 'scope' => Permission::SCOPE_PLATFORM],
            );
        }

        $this->syncOwnerRolesWithAllPermissions();
    }

    /**
     * The Business Owner role is defined as "full access" by definition, not
     * as a per-business customization. Whenever a new tenant permission is
     * added by a later sprint, every existing business's Owner role must
     * receive it automatically so an owner never loses access to a
     * capability their business already pays for. Platform-scope
     * permissions are deliberately excluded — a tenant Business Owner
     * should never be granted SuperAdmin-side capabilities. Other system
     * roles (Manager, Cashier, ...) are intentionally left untouched here,
     * since their permissions may have already been deliberately
     * customized by the business owner.
     */
    private function syncOwnerRolesWithAllPermissions(): void
    {
        $tenantPermissionIds = Permission::query()->where('scope', Permission::SCOPE_TENANT)->pluck('id');

        Role::query()
            ->where('slug', Role::OWNER)
            ->where('is_system', true)
            ->each(fn (Role $role) => $role->permissions()->sync($tenantPermissionIds));
    }
}
