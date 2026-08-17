<?php

namespace App\Domain\Backup\Services;

use App\Domain\Backup\Support\SqlValue;
use App\Domain\Backup\Support\TenantTableMap;
use App\Domain\Business\Models\Business;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Writes one business's records as a plain `.sql` file.
 *
 * Not a database dump. `mysqldump` would hand a vendor every other
 * business on the platform, so this walks the tenant table map and emits
 * only rows belonging to the business asking for them.
 *
 * The output is streamed and chunked rather than assembled in memory: a
 * busy shop's `sales` and `stock_movements` tables are the largest thing
 * in the system, and a backup that dies with a memory error on the
 * businesses that most need one would be worse than having no feature.
 */
class TenantSqlExportService
{
    /**
     * The format marker at the top of every file. The importer refuses
     * anything without it — not as security (the allow-list does that) but
     * so someone who uploads a `mysqldump` of their own server gets a clear
     * "this isn't a BiasharaMax backup" instead of a confusing partial
     * restore.
     */
    public const FORMAT_HEADER = '-- BiasharaMax Tenant Backup';

    public const FORMAT_VERSION = 1;

    private const CHUNK = 500;

    /**
     * Yields the file a piece at a time, ready for a streamed response.
     *
     * @return Generator<int, string>
     */
    public function stream(Business $business): Generator
    {
        yield $this->header($business);

        foreach (TenantTableMap::directTables() as $table) {
            yield from $this->dumpTable(
                $table,
                DB::table($table)->where('business_id', $business->getKey()),
            );
        }

        foreach (TenantTableMap::childTables() as $table => $meta) {
            // Child rows carry no business_id, so they are selected through
            // the parent that does. Same subquery the delete side uses, so
            // export and restore always cover exactly the same rows.
            yield from $this->dumpTable(
                $table,
                DB::table($table)->whereIn(
                    $meta['foreign_key'],
                    DB::table($meta['parent'])->select('id')->where('business_id', $business->getKey()),
                ),
            );
        }

        yield "\n-- End of backup.\n";
    }

    public function filename(Business $business): string
    {
        $slug = $business->slug ?: 'business';

        return sprintf('biasharamax-%s-%s.sql', $slug, now()->format('Y-m-d-His'));
    }

    private function header(Business $business): string
    {
        $tables = TenantTableMap::allTables();

        return implode("\n", [
            self::FORMAT_HEADER,
            '-- format_version: '.self::FORMAT_VERSION,
            '-- business_id: '.$business->getKey(),
            '-- business_name: '.str_replace(["\n", "\r"], ' ', (string) $business->name),
            '-- generated_at: '.now()->toIso8601String(),
            '-- tables: '.count($tables),
            '--',
            '-- This file contains only this business\'s records. It is not a',
            '-- database dump and cannot be used to restore the platform.',
            '-- Accounts, roles, billing and audit history are deliberately',
            '-- excluded — see the Backup & Restore screen for the full list.',
            '',
            '',
        ]);
    }

    /**
     * @return Generator<int, string>
     */
    private function dumpTable(string $table, \Illuminate\Database\Query\Builder $query): Generator
    {
        $total = (clone $query)->count();

        yield sprintf("-- table: %s (%d rows)\n", $table, $total);

        if ($total === 0) {
            yield "\n";

            return;
        }

        // Pivot tables (product_tag, customer_customer_tag, …) have no `id`,
        // so ordering falls back to every column. Some deterministic order
        // is required either way: paging an unordered result over a table
        // taking writes can return one row twice and miss another.
        $orderBy = Schema::hasColumn($table, 'id')
            ? ['id']
            : Schema::getColumnListing($table);

        $offset = 0;

        while (true) {
            $page = clone $query;

            foreach ($orderBy as $column) {
                $page->orderBy($column);
            }

            $rows = $page->offset($offset)->limit(self::CHUNK)->get();

            if ($rows->isEmpty()) {
                break;
            }

            $buffer = '';

            foreach ($rows as $row) {
                $columns = array_keys((array) $row);
                $values = array_map(SqlValue::quote(...), array_values((array) $row));

                $buffer .= sprintf(
                    "INSERT INTO `%s` (%s) VALUES (%s);\n",
                    $table,
                    implode(', ', array_map(fn (string $c): string => '`'.$c.'`', $columns)),
                    implode(', ', $values),
                );
            }

            yield $buffer;

            $offset += self::CHUNK;
        }

        yield "\n";
    }
}
