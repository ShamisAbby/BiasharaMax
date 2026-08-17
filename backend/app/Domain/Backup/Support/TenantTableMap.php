<?php

namespace App\Domain\Backup\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Decides which tables belong in a tenant's own backup.
 *
 * A vendor must never receive, or be able to write, a full database dump —
 * it would contain every other business on the platform, and importing raw
 * SQL would let any vendor rewrite anyone's data. So a tenant backup is
 * defined here as a deliberate, reviewable set of tables rather than
 * "everything", and the import side refuses any table not on this list.
 *
 * Membership is discovered from the schema (`business_id` column) rather
 * than hand-listed, so a new module's tables are included automatically
 * instead of being silently missing from every backup until someone
 * notices. What is hand-listed is the EXCLUSIONS — those need a human
 * decision and are the security boundary.
 */
final class TenantTableMap
{
    /**
     * Tables a tenant may not export or import, even though they carry a
     * `business_id`. Each one is here because restoring it from an
     * uploaded file would change something the business is not entitled to
     * change about itself.
     *
     * @var array<string, string> table => why
     */
    public const EXCLUDED = [
        // Billing and entitlement. A restored subscription row would hand
        // the business back a plan it no longer pays for, and a restored
        // transaction would corrupt revenue reporting.
        'subscriptions' => 'Billing state is owned by the platform, not the tenant.',
        'subscription_transactions' => 'Payment history must not be rewritable by the payer.',
        'payment_transactions' => 'Payment history must not be rewritable by the payer.',
        'licenses' => 'Licensing is issued by the platform.',
        'business_module' => 'Module entitlements follow the subscription plan.',

        // Authentication and authorization. Restoring these from a file
        // would let someone change who can sign in and what they may do,
        // outside of the screens that audit those changes.
        'users' => 'Accounts and password hashes are not business records.',
        'roles' => 'Restoring roles would change permissions via a file upload.',

        // Tamper-evidence. A backup that can erase the record of what was
        // done to it is not an audit log.
        'audit_logs' => 'An audit trail that can be restored over is worthless.',
        'impersonation_logs' => 'Platform oversight record, not tenant data.',

        // Cross-party records — the other side of these conversations is
        // the platform, which never agreed to a rollback.
        'support_tickets' => 'Shared with platform support; not solely tenant data.',
        'webhooks' => 'Outbound endpoints and secrets; restoring could redirect data.',
        'product_enquiries' => 'Submitted by members of the public, not by the business.',
    ];

    /**
     * Tables that hold tenant data but have no `business_id` of their own —
     * order lines, pivots and the like, which belong to a business only
     * through their parent row.
     *
     * Without these a backup would contain sales with no line items and
     * products with no images: technically restorable, practically useless.
     *
     * @var array<string, array{parent: string, foreign_key: string}>
     */
    public const CHILD_TABLES = [
        'sale_items' => ['parent' => 'sales', 'foreign_key' => 'sale_id'],
        'sale_return_items' => ['parent' => 'sale_returns', 'foreign_key' => 'sale_return_id'],
        'purchase_order_items' => ['parent' => 'purchase_orders', 'foreign_key' => 'purchase_order_id'],
        'goods_received_items' => ['parent' => 'goods_received_notes', 'foreign_key' => 'goods_received_note_id'],
        'inventory_count_items' => ['parent' => 'inventory_counts', 'foreign_key' => 'inventory_count_id'],
        'stock_adjustment_items' => ['parent' => 'stock_adjustments', 'foreign_key' => 'stock_adjustment_id'],
        'stock_transfer_items' => ['parent' => 'stock_transfers', 'foreign_key' => 'stock_transfer_id'],
        'budget_lines' => ['parent' => 'budgets', 'foreign_key' => 'budget_id'],
        'payslip_deductions' => ['parent' => 'payslips', 'foreign_key' => 'payslip_id'],
        'salary_allowances' => ['parent' => 'employee_profiles', 'foreign_key' => 'employee_profile_id'],
        'attribute_values' => ['parent' => 'attributes', 'foreign_key' => 'attribute_id'],
        'product_attribute_values' => ['parent' => 'products', 'foreign_key' => 'product_id'],
        'product_collection' => ['parent' => 'products', 'foreign_key' => 'product_id'],
        'product_tag' => ['parent' => 'products', 'foreign_key' => 'product_id'],
        'product_supplier' => ['parent' => 'products', 'foreign_key' => 'product_id'],
        'customer_customer_tag' => ['parent' => 'customers', 'foreign_key' => 'customer_id'],
        'customer_feedback_replies' => ['parent' => 'customer_feedback', 'foreign_key' => 'customer_feedback_id'],
        'campaign_recipients' => ['parent' => 'marketing_campaigns', 'foreign_key' => 'marketing_campaign_id'],
        'article_article_tag' => ['parent' => 'articles', 'foreign_key' => 'article_id'],
        'business_website_pages' => ['parent' => 'business_websites', 'foreign_key' => 'business_website_id'],
    ];

    /**
     * Memoised per connection.
     *
     * Resolving the list means a `Schema::hasColumn()` round trip for every
     * table in the database — around 170 queries. `isAllowed()` is called
     * once per row during an import, so without this a 50,000-row restore
     * would issue millions of schema queries and never finish. The schema
     * cannot change during a request, so caching it costs nothing.
     *
     * @var array<string, list<string>>
     */
    private static array $directCache = [];

    /**
     * @var array<string, array<string, array{parent: string, foreign_key: string}>>
     */
    private static array $childCache = [];

    /**
     * @var array<string, array<string, true>>
     */
    private static array $lookupCache = [];

    /**
     * Clears the memoised lists.
     *
     * Needed by tests, which migrate between cases: a list resolved against
     * one schema must not leak into the next.
     */
    public static function flush(): void
    {
        self::$directCache = [];
        self::$childCache = [];
        self::$lookupCache = [];
    }

    /**
     * Tables owned directly by a business, in a stable order.
     *
     * @return list<string>
     */
    public static function directTables(): array
    {
        $connection = DB::connection()->getName().':'.DB::connection()->getDatabaseName();

        if (isset(self::$directCache[$connection])) {
            return self::$directCache[$connection];
        }

        // Scoped to THIS connection's database, explicitly.
        //
        // `Schema::getTableListing()` with no arguments lists tables from
        // every schema the MySQL user can see except the system ones — so
        // on a machine with both `biasharamax` and `biasharaos_testing` (or
        // any dev and prod database on one server, which is the normal
        // XAMPP setup) it returned `accounts` twice, once per schema.
        //
        // Stripping the schema prefix then collapsed them into a duplicate
        // entry, and the exporter dumped every row twice — which the
        // importer faithfully tried to insert twice, hitting a duplicate
        // primary key. Passing the database name is what makes the listing
        // mean what the rest of this class assumes it means.
        $tables = collect(Schema::getTableListing(
            schema: DB::connection()->getDatabaseName(),
            schemaQualified: false,
        ))
            ->unique()
            ->filter(fn (string $table): bool => ! array_key_exists($table, self::EXCLUDED))
            ->filter(fn (string $table): bool => Schema::hasColumn($table, 'business_id'))
            ->sort()
            ->values();

        return self::$directCache[$connection] = $tables->all();
    }

    /**
     * Child tables whose parent is actually part of the backup. Filtered
     * against `directTables()` so excluding a parent silently excludes its
     * children too — otherwise excluding `roles` would still let
     * `permission_role` through.
     *
     * @return array<string, array{parent: string, foreign_key: string}>
     */
    public static function childTables(): array
    {
        $connection = DB::connection()->getName().':'.DB::connection()->getDatabaseName();

        if (isset(self::$childCache[$connection])) {
            return self::$childCache[$connection];
        }

        $direct = self::directTables();

        return self::$childCache[$connection] = collect(self::CHILD_TABLES)
            ->filter(fn (array $meta, string $table): bool => in_array($meta['parent'], $direct, true)
                && Schema::hasTable($table))
            ->all();
    }

    /**
     * Every table a tenant backup may contain — the only names the importer
     * will accept.
     *
     * @return list<string>
     */
    public static function allTables(): array
    {
        return array_values(array_merge(self::directTables(), array_keys(self::childTables())));
    }

    /**
     * Called once per row during an import, so it looks the table up in a
     * hash rather than scanning the list.
     */
    public static function isAllowed(string $table): bool
    {
        $connection = DB::connection()->getName().':'.DB::connection()->getDatabaseName();

        // A class property rather than a `static` local, so `flush()` can
        // actually clear it — a stale lookup surviving a schema change is
        // the one way this could start accepting a table it shouldn't.
        self::$lookupCache[$connection] ??= array_fill_keys(self::allTables(), true);

        return isset(self::$lookupCache[$connection][$table]);
    }
}
