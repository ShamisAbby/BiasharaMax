<?php

namespace App\Domain\Backup\Services;

use App\Domain\Backup\Support\SqlValue;
use App\Domain\Backup\Support\TenantTableMap;
use App\Domain\Business\Models\Business;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Restores a business from a `.sql` file produced by
 * TenantSqlExportService.
 *
 * The single most important thing about this class is what it does NOT do:
 * it never executes the uploaded file. Running an uploaded `.sql` would
 * hand any vendor arbitrary SQL against the shared database — they could
 * grant themselves a subscription plan, read another tenant's customers,
 * or make themselves a platform admin. Instead the file is *parsed*, every
 * table name is checked against the allow-list, `business_id` is rewritten
 * to the importing business, and the rows go in through the query builder
 * as parameterised inserts.
 *
 * That means a malicious file is not dangerous, only rejected: the worst a
 * crafted backup can do is write rows into the uploader's own business,
 * which they can already do through the UI.
 */
class TenantSqlImportService
{
    /**
     * Rows are inserted in batches rather than one statement at a time.
     * Small enough to stay well inside MySQL's placeholder limit on wide
     * tables (products has ~40 columns), large enough that restoring tens
     * of thousands of stock movements doesn't take minutes.
     */
    private const INSERT_BATCH = 200;

    /**
     * Reads the file without writing anything.
     *
     * Deliberately a separate step. A restore replaces live business data,
     * and the difference between "this backup has 4,000 sales" and "this
     * backup has 3 sales" is the difference between restoring and
     * destroying — the owner needs to see that before deciding.
     *
     * @return array{business_id: ?string, business_name: ?string, generated_at: ?string, belongs_to: ?string, rows: array<string, int>, total: int, skipped: array<string, int>}
     */
    public function preview(string $path): array
    {
        $meta = $this->readHeader($path);
        $rows = [];
        $skipped = [];

        foreach ($this->statements($path) as [$table, $columns, $values]) {
            if (TenantTableMap::isAllowed($table)) {
                $rows[$table] = ($rows[$table] ?? 0) + 1;
            } else {
                // Counted and reported rather than silently dropped: if a
                // file contains tables we refuse, the owner should be told
                // that part of it will not be restored.
                $skipped[$table] = ($skipped[$table] ?? 0) + 1;
            }
        }

        ksort($rows);
        ksort($skipped);

        return [
            'business_id' => $meta['business_id'] ?? null,
            'business_name' => $meta['business_name'] ?? null,
            'generated_at' => $meta['generated_at'] ?? null,
            // Surfaced so the screen can say so before the owner types a
            // confirmation, rather than failing after they have.
            'belongs_to' => $meta['business_id'] ?? null,
            'rows' => $rows,
            'total' => array_sum($rows),
            'skipped' => $skipped,
        ];
    }

    /**
     * Replaces the business's records with the contents of the file.
     *
     * @return array{restored: array<string, int>, deleted: int, skipped: array<string, int>}
     */
    public function restore(Business $business, string $path): array
    {
        $meta = $this->readHeader($path);

        // A backup may only be restored into the business it came from.
        //
        // This started as a test that expected cross-business restores to
        // work, and the test was wrong. Primary keys are preserved so that
        // foreign keys inside the backup stay valid, which means restoring
        // business A's file into business B tries to insert rows whose ids
        // already exist — A's rows are still there. Remapping every key and
        // every reference to it is a different feature (clone a business),
        // not a restore.
        //
        // It is also the safer rule: a backup someone was sent by a former
        // employee or a competitor cannot be loaded in as "my data".
        $source = $meta['business_id'] ?? null;

        if ($source !== null && $source !== $business->getKey()) {
            throw new RuntimeException(
                'This backup belongs to a different business ('
                .($meta['business_name'] ?? $source)
                .') and cannot be restored here.'
            );
        }

        $restored = [];
        $skipped = [];
        $deleted = 0;

        DB::transaction(function () use ($business, $path, &$restored, &$skipped, &$deleted): void {
            // Foreign keys are switched off for the duration because the
            // data being restored is internally consistent but arrives in
            // whatever order the file lists it — a sale_item before its
            // sale, say. Scoped to this connection and this transaction,
            // and re-enabled in the finally below even if a row fails.
            $this->withoutForeignKeyChecks(function () use ($business, $path, &$restored, &$skipped, &$deleted): void {
                $deleted = $this->purge($business);

                $buffer = [];

                foreach ($this->statements($path) as [$table, $columns, $values]) {
                    if (! TenantTableMap::isAllowed($table)) {
                        $skipped[$table] = ($skipped[$table] ?? 0) + 1;

                        continue;
                    }

                    $row = $this->buildRow($business, $table, $columns, $values);

                    $buffer[$table][] = $row;
                    $restored[$table] = ($restored[$table] ?? 0) + 1;

                    if (count($buffer[$table]) >= self::INSERT_BATCH) {
                        DB::table($table)->insert($buffer[$table]);
                        $buffer[$table] = [];
                    }
                }

                foreach ($buffer as $table => $rows) {
                    if ($rows !== []) {
                        DB::table($table)->insert($rows);
                    }
                }
            });
        });

        ksort($restored);
        ksort($skipped);

        return ['restored' => $restored, 'deleted' => $deleted, 'skipped' => $skipped];
    }

    /**
     * Removes the business's existing rows from every covered table.
     *
     * Children first, so a parent delete never strands a child row in a
     * table that outlives the transaction's disabled constraints.
     */
    private function purge(Business $business): int
    {
        $deleted = 0;

        foreach (TenantTableMap::childTables() as $table => $meta) {
            $deleted += DB::table($table)
                ->whereIn(
                    $meta['foreign_key'],
                    DB::table($meta['parent'])->select('id')->where('business_id', $business->getKey()),
                )
                ->delete();
        }

        foreach (TenantTableMap::directTables() as $table) {
            $deleted += DB::table($table)->where('business_id', $business->getKey())->delete();
        }

        return $deleted;
    }

    /**
     * Turns one parsed statement into a row that is safe to insert.
     *
     * @param  list<string>  $columns
     * @param  list<string|int|float|null>  $values
     * @return array<string, mixed>
     */
    private function buildRow(Business $business, string $table, array $columns, array $values): array
    {
        if (count($columns) !== count($values)) {
            throw new RuntimeException("Malformed row for `{$table}`: {$this->describeMismatch($columns, $values)}");
        }

        $row = array_combine($columns, $values);

        // Columns that no longer exist are dropped rather than fatal: a
        // backup taken before a migration removed a column is still a
        // perfectly good backup of everything else in it.
        $existing = Schema::getColumnListing($table);
        $row = array_intersect_key($row, array_flip($existing));

        // The one rewrite that matters. Whatever business the file claims
        // to be from, the rows land in the business doing the importing —
        // so a file from another tenant cannot be used to write into
        // theirs, and a file whose business_id was edited by hand is
        // simply ignored.
        if (in_array('business_id', $existing, true)) {
            $row['business_id'] = $business->getKey();
        }

        return $row;
    }

    private function describeMismatch(array $columns, array $values): string
    {
        return sprintf('%d columns but %d values', count($columns), count($values));
    }

    /**
     * @return array<string, string>
     */
    private function readHeader(string $path): array
    {
        $handle = $this->open($path);
        $meta = [];
        $seenMarker = false;

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");

            if (! str_starts_with($line, '--')) {
                break;
            }

            if (str_starts_with($line, TenantSqlExportService::FORMAT_HEADER)) {
                $seenMarker = true;

                continue;
            }

            if (preg_match('/^--\s*([a-z_]+):\s*(.*)$/', $line, $matches) === 1) {
                $meta[$matches[1]] = trim($matches[2]);
            }
        }

        fclose($handle);

        if (! $seenMarker) {
            throw new RuntimeException(
                'This file is not a BiasharaMax backup. Upload the .sql file you downloaded from this screen.'
            );
        }

        $version = (int) ($meta['format_version'] ?? 0);

        if ($version > TenantSqlExportService::FORMAT_VERSION) {
            throw new RuntimeException(
                "This backup was made by a newer version of BiasharaMax (format {$version}) and can't be restored here."
            );
        }

        return $meta;
    }

    /**
     * Walks the file one INSERT at a time.
     *
     * A generator over a file handle, not `file_get_contents` — a backup of
     * a busy business is tens of megabytes, and holding it plus the parsed
     * result in memory at once is how imports fail on shared hosting.
     *
     * @return Generator<int, array{0: string, 1: list<string>, 2: list<string|int|float|null>}>
     */
    private function statements(string $path): Generator
    {
        $handle = $this->open($path);

        try {
            while (($line = fgets($handle)) !== false) {
                $line = trim($line);

                if ($line === '' || str_starts_with($line, '--')) {
                    continue;
                }

                if (preg_match('/^INSERT INTO `([a-zA-Z0-9_]+)` \((.*?)\) VALUES \((.*)\);$/', $line, $matches) !== 1) {
                    // Anything that isn't one of our own INSERTs is ignored
                    // rather than executed. This is what makes an uploaded
                    // file harmless: DROP, UPDATE, GRANT and everything else
                    // simply doesn't match and never reaches the database.
                    continue;
                }

                $columns = array_map(
                    static fn (string $column): string => trim(trim($column), '`'),
                    explode(',', $matches[2]),
                );

                yield [$matches[1], $columns, SqlValue::parseRow($matches[3])];
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return resource
     */
    private function open(string $path)
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('The uploaded backup file could not be read.');
        }

        return $handle;
    }

    private function withoutForeignKeyChecks(callable $callback): void
    {
        $driver = DB::connection()->getDriverName();

        // Only MySQL/MariaDB need this, and only they understand the
        // statement — issuing it blindly would break SQLite test runs.
        $toggle = in_array($driver, ['mysql', 'mariadb'], true);

        if ($toggle) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            $callback();
        } finally {
            if ($toggle) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }
}
