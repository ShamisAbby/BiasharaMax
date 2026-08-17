<?php

namespace App\Domain\Backup\Services;

use Generator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Restores a whole-database `.sql` file uploaded by a platform admin.
 *
 * Unlike the tenant importer, this one really does execute the file — a
 * schema restore has to run DDL, and a platform admin already holds full
 * authority over this database. The safeguards here are therefore about
 * mistakes rather than privilege: read the file first (`inspect`) and
 * refuse anything that isn't recognisably one of our dumps.
 *
 * Be clear about what the transaction does and doesn't buy: MySQL commits
 * implicitly on DDL, so `DROP TABLE` and `CREATE TABLE` cannot be rolled
 * back. A restore that fails halfway leaves the database halfway. That is
 * inherent to restoring a schema on MySQL, not something this class can
 * paper over — which is why the UI insists on a typed confirmation and
 * says so in as many words.
 *
 * `DB::unprepared()` per statement rather than piping the file to the
 * `mysql` client, for the same reason the dump is written in PHP: the
 * client binary is frequently not on the web process's PATH, and a
 * restore path that depends on it fails exactly when it is needed.
 */
class DatabaseSqlRestoreService
{
    /**
     * Counts what a file will do without touching the database.
     *
     * @return array{statements: int, tables: list<string>, inserts: int, is_recognised: bool}
     */
    public function inspect(string $path): array
    {
        $tables = [];
        $statements = 0;
        $inserts = 0;

        foreach ($this->statements($path) as $statement) {
            $statements++;

            if (preg_match('/^INSERT INTO `([a-zA-Z0-9_]+)`/i', $statement, $match) === 1) {
                $inserts++;
                $tables[$match[1]] = true;
            } elseif (preg_match('/^CREATE TABLE `?([a-zA-Z0-9_]+)`?/i', $statement, $match) === 1) {
                $tables[$match[1]] = true;
            }
        }

        ksort($tables);

        return [
            'statements' => $statements,
            'tables' => array_keys($tables),
            'inserts' => $inserts,
            'is_recognised' => $this->isRecognised($path),
        ];
    }

    public function restore(string $path): int
    {
        if (! $this->isRecognised($path)) {
            throw new RuntimeException(
                'This file is not a BiasharaMax database backup. Export one from this screen to see the expected format.'
            );
        }

        $applied = 0;

        DB::transaction(function () use ($path, &$applied): void {
            // The dump sets this itself, but a file that fails partway
            // through would otherwise leave the session with constraints
            // off. Setting it here means the `finally` below always puts it
            // back regardless of how the restore ends.
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            try {
                foreach ($this->statements($path) as $statement) {
                    DB::unprepared($statement);
                    $applied++;
                }
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        });

        return $applied;
    }

    private function isRecognised(string $path): bool
    {
        $handle = $this->open($path);
        $firstLine = fgets($handle) ?: '';
        fclose($handle);

        return str_starts_with(trim($firstLine), DatabaseSqlDumpService::FORMAT_HEADER);
    }

    /**
     * Yields complete statements from the file.
     *
     * Statement boundaries are found by tracking string literals rather
     * than splitting on `;`, because a semicolon inside a product
     * description would otherwise cut a statement in half — which is the
     * kind of bug that only shows up on the one restore that matters.
     *
     * @return Generator<int, string>
     */
    private function statements(string $path): Generator
    {
        $handle = $this->open($path);
        $buffer = '';
        $inString = false;
        $quote = "'";

        try {
            while (($line = fgets($handle)) !== false) {
                if ($buffer === '' && str_starts_with(ltrim($line), '--')) {
                    continue;
                }

                $length = strlen($line);

                for ($i = 0; $i < $length; $i++) {
                    $char = $line[$i];

                    if ($inString) {
                        if ($char === '\\' && $i + 1 < $length) {
                            $buffer .= $char.$line[$i + 1];
                            $i++;

                            continue;
                        }

                        if ($char === $quote) {
                            $inString = false;
                        }

                        $buffer .= $char;

                        continue;
                    }

                    if ($char === "'" || $char === '"' || $char === '`') {
                        $inString = true;
                        $quote = $char;
                        $buffer .= $char;

                        continue;
                    }

                    if ($char === ';') {
                        $statement = trim($buffer);
                        $buffer = '';

                        if ($statement !== '') {
                            yield $statement;
                        }

                        continue;
                    }

                    $buffer .= $char;
                }
            }

            $statement = trim($buffer);

            if ($statement !== '') {
                yield $statement;
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
}
