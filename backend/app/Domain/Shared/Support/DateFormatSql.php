<?php

namespace App\Domain\Shared\Support;

use Illuminate\Support\Facades\DB;

/**
 * Driver-portable date-bucketing SQL fragments. Several report/dashboard
 * services group rows by day or month using a raw SQL date-format
 * expression (selectRaw/groupByRaw/orderByRaw all need the *same* fragment,
 * so this is worth centralizing rather than repeating the driver check at
 * every call site) — Postgres's `to_char(column, 'YYYY-MM-DD')` has no MySQL
 * equivalent function, so this branches to MySQL's `DATE_FORMAT(column,
 * '%Y-%m-%d')` instead, mirroring the split_part -> substring_index branch
 * already done for the `permissions.action` generated column
 * (2026_06_27_134939_add_scope_and_action_to_permissions_table.php).
 *
 * Deliberately returns a raw SQL string (not a closure/enum) since every
 * call site plugs it straight into selectRaw()/groupByRaw()/orderByRaw().
 */
class DateFormatSql
{
    /**
     * Buckets a timestamp column to a 'YYYY-MM-DD' string.
     */
    public static function daily(string $column): string
    {
        return self::isMysql()
            ? "DATE_FORMAT({$column}, '%Y-%m-%d')"
            : "to_char({$column}, 'YYYY-MM-DD')";
    }

    /**
     * Buckets a timestamp column to a 'YYYY-MM' string.
     */
    public static function monthly(string $column): string
    {
        return self::isMysql()
            ? "DATE_FORMAT({$column}, '%Y-%m')"
            : "to_char({$column}, 'YYYY-MM')";
    }

    private static function isMysql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }
}
