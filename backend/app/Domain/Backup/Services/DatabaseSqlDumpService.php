<?php

namespace App\Domain\Backup\Services;

use App\Domain\Backup\Support\SqlValue;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * A whole-database `.sql` dump, written in PHP.
 *
 * Deliberately not `mysqldump`. The existing zip backups shell out to it,
 * and on this project that is exactly what fails first: XAMPP and Homebrew
 * put the client binaries somewhere the web process's PATH doesn't reach,
 * so `backup:run` returns a non-zero exit code and the backup silently
 * lands in the history as "failed". A dump that only works when a binary
 * happens to be on PATH is a backup you find out you don't have on the day
 * you need it.
 *
 * The trade-off is honest: this is slower than mysqldump and MySQL-family
 * only. It is meant to sit alongside the zip backups (which also cover
 * uploaded files), not replace them.
 */
class DatabaseSqlDumpService
{
    /**
     * Tables whose contents are runtime scaffolding, not data.
     *
     * Restoring these does active harm: `sessions` would sign the wrong
     * people in, `jobs` would re-run work that already happened, and
     * `cache` would resurrect stale values that outlive the restore.
     */
    private const EPHEMERAL = [
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
    ];

    private const CHUNK = 500;

    public const FORMAT_HEADER = '-- BiasharaMax Database Backup';

    /**
     * @return Generator<int, string>
     */
    public function stream(): Generator
    {
        $this->assertSupported();

        $tables = $this->tables();

        yield implode("\n", [
            self::FORMAT_HEADER,
            '-- generated_at: '.now()->toIso8601String(),
            '-- database: '.DB::connection()->getDatabaseName(),
            '-- tables: '.count($tables),
            '--',
            '-- Full schema and data. Restoring this replaces every business',
            '-- on the platform. Runtime tables (sessions, jobs, cache) are',
            '-- excluded on purpose.',
            '',
            'SET FOREIGN_KEY_CHECKS=0;',
            '',
            '',
        ]);

        foreach ($tables as $table) {
            yield from $this->dumpTable($table);
        }

        yield "SET FOREIGN_KEY_CHECKS=1;\n\n-- End of backup.\n";
    }

    public function filename(): string
    {
        return sprintf('biasharamax-database-%s.sql', now()->format('Y-m-d-His'));
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        // Scoped to this connection's database — see TenantTableMap for
        // the full story. Without the schema argument MySQL lists tables
        // from every database the user can see, so a dev and a testing
        // database on one server produce each table name twice and every
        // row gets dumped twice.
        return collect(Schema::getTableListing(
            schema: DB::connection()->getDatabaseName(),
            schemaQualified: false,
        ))
            ->unique()
            ->reject(fn (string $table): bool => in_array($table, self::EPHEMERAL, true))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return Generator<int, string>
     */
    private function dumpTable(string $table): Generator
    {
        yield sprintf("--\n-- Table: %s\n--\n\n", $table);
        yield sprintf("DROP TABLE IF EXISTS `%s`;\n", $table);

        // Taken from the engine rather than reconstructed from the schema
        // builder: only SHOW CREATE TABLE reproduces indexes, defaults,
        // generated columns and collation exactly as they are.
        $create = DB::selectOne("SHOW CREATE TABLE `{$table}`");
        $sql = (array) $create;
        $definition = $sql['Create Table'] ?? $sql['Create View'] ?? null;

        if ($definition === null) {
            throw new RuntimeException("Could not read the definition of `{$table}`.");
        }

        yield $definition.";\n\n";

        $total = DB::table($table)->count();

        if ($total === 0) {
            yield "\n";

            return;
        }

        $columns = Schema::getColumnListing($table);
        $orderBy = in_array('id', $columns, true) ? ['id'] : $columns;
        $offset = 0;

        while (true) {
            $query = DB::table($table);

            foreach ($orderBy as $column) {
                $query->orderBy($column);
            }

            $rows = $query->offset($offset)->limit(self::CHUNK)->get();

            if ($rows->isEmpty()) {
                break;
            }

            $buffer = '';

            foreach ($rows as $row) {
                $values = array_map(SqlValue::quote(...), array_values((array) $row));

                $buffer .= sprintf(
                    "INSERT INTO `%s` (%s) VALUES (%s);\n",
                    $table,
                    implode(', ', array_map(fn (string $c): string => '`'.$c.'`', array_keys((array) $row))),
                    implode(', ', $values),
                );
            }

            yield $buffer;

            $offset += self::CHUNK;
        }

        yield "\n";
    }

    private function assertSupported(): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException(
                "Plain .sql export currently supports MySQL and MariaDB only (this connection is {$driver})."
            );
        }
    }
}
