<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Part of the Postgres -> MySQL engine cutover (docs/ADR/0001-consolidation.md
 * Decisions table). Postgres's default collation is case-sensitive; MySQL's
 * default (`utf8mb4_unicode_ci`, per config/database.php) is
 * case-*insensitive*. Left alone, this silently changes uniqueness
 * semantics on identifier-like columns after the engine switch: `"ABC-1"`
 * and `"abc-1"` are distinct values today (Postgres) but would collide as
 * duplicates under a MySQL unique index using the default collation.
 *
 * Rather than accept that silent behavior change (or worse, have it
 * surface as a mysterious duplicate-key error the first time someone
 * creates a case-variant SKU), explicitly pin these specific
 * identifier/reference columns to a binary (case-sensitive) collation on
 * MySQL only — preserving exact parity with today's Postgres behavior.
 * Postgres needs no change here; its default is already case-sensitive.
 *
 * Deliberately NOT applied to `email` columns (users/platform_users/
 * businesses) — case-insensitive email uniqueness is standard practice and
 * arguably the more correct behavior (nobody wants `Name@Example.com` and
 * `name@example.com` to be treated as different accounts), so MySQL's
 * default here is a welcome behavior change, not a regression, and is left
 * as-is intentionally.
 *
 * No `doctrine/dbal` dependency (not installed in this project) — raw SQL
 * MODIFY COLUMN instead of Blueprint::change(), which needs it.
 */
return new class extends Migration
{
    /**
     * MySQL's `ALTER TABLE ... MODIFY` does not preserve attributes that
     * aren't restated in the statement — omitting `not null` here would
     * silently drop the NOT NULL constraint these columns already have
     * (confirmed against the original create-table migrations: `sku`,
     * `serial_number`, `reference_number` are all `$table->string(...)`
     * with no `->nullable()`; `barcode` is the only one of these six that
     * actually is nullable). Every definition below must restate every
     * attribute the column already has, not just the collation being added.
     *
     * @var array<int, array{table: string, column: string, definition: string}>
     */
    private array $columns = [
        ['table' => 'products', 'column' => 'sku', 'definition' => 'varchar(255) not null'],
        ['table' => 'products', 'column' => 'barcode', 'definition' => 'varchar(255) null'],
        ['table' => 'product_variants', 'column' => 'sku', 'definition' => 'varchar(255) not null'],
        ['table' => 'product_variants', 'column' => 'barcode', 'definition' => 'varchar(255) null'],
        ['table' => 'product_serials', 'column' => 'serial_number', 'definition' => 'varchar(255) not null'],
        ['table' => 'payment_transactions', 'column' => 'reference_number', 'definition' => 'varchar(255) not null'],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->columns as $col) {
            DB::statement("alter table {$col['table']} modify {$col['column']} {$col['definition']} collate utf8mb4_bin");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach ($this->columns as $col) {
            DB::statement("alter table {$col['table']} modify {$col['column']} {$col['definition']} collate utf8mb4_unicode_ci");
        }
    }
};
