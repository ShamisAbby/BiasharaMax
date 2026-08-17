<?php

namespace App\Domain\Backup\Support;

/**
 * Encodes and decodes the values inside a tenant backup's INSERT
 * statements.
 *
 * The pair has to be exactly symmetrical: whatever `quote()` writes,
 * `parseRow()` must read back as the identical PHP value, or a restore
 * silently corrupts data rather than failing loudly. That symmetry is the
 * whole reason this lives in one small class instead of being spread
 * across the exporter and importer.
 *
 * The output is deliberately valid MySQL so the file can be opened,
 * inspected, and — for a platform admin who wants to — fed to `mysql`
 * directly. The importer never does that: it parses these statements and
 * writes through the query builder, so an uploaded file can only ever
 * become parameterised INSERTs into tables on the allow-list.
 */
final class SqlValue
{
    /**
     * Escapes exactly the sequences `unescape()` reverses. Anything not
     * listed here passes through untouched, including UTF-8, which is why
     * this is byte-safe for normal text.
     */
    private const ESCAPES = [
        '\\' => '\\\\',
        "'" => "\\'",
        "\n" => '\\n',
        "\r" => '\\r',
        "\t" => '\\t',
        "\x00" => '\\0',
        "\x1a" => '\\Z',
    ];

    public static function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        // Numbers are emitted bare so the file reads like a real dump.
        // `is_numeric` is deliberately not used: it would treat the string
        // "0012" as a number and drop the leading zeros on the way back,
        // which matters for codes, phone numbers and SKUs.
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $string = (string) $value;

        // Binary that isn't valid UTF-8 can't survive a text file, so it
        // goes out as a hex literal — which MySQL understands natively and
        // `unescape()` never sees, because it isn't quoted.
        if (! mb_check_encoding($string, 'UTF-8')) {
            return '0x'.bin2hex($string);
        }

        return "'".strtr($string, self::ESCAPES)."'";
    }

    public static function unescape(string $value): string
    {
        return strtr($value, array_flip(self::ESCAPES));
    }

    /**
     * Splits the inside of a `VALUES (...)` clause into raw PHP values.
     *
     * Hand-written rather than a regex: values contain commas, quotes and
     * escaped quotes, and a regex that gets that right is both unreadable
     * and easy to fool. Walking the string one character at a time is the
     * version whose correctness can be checked by reading it.
     *
     * @return list<string|int|float|null>
     */
    public static function parseRow(string $tuple): array
    {
        $values = [];
        $buffer = '';
        $inString = false;
        $length = strlen($tuple);

        for ($i = 0; $i < $length; $i++) {
            $char = $tuple[$i];

            if ($inString) {
                if ($char === '\\' && $i + 1 < $length) {
                    // Keep the escape sequence intact; `unescape()` resolves
                    // it once the whole literal has been collected.
                    $buffer .= $char.$tuple[$i + 1];
                    $i++;

                    continue;
                }

                if ($char === "'") {
                    $inString = false;
                    $values[] = self::unescape($buffer);
                    $buffer = '';

                    continue;
                }

                $buffer .= $char;

                continue;
            }

            if ($char === "'") {
                $inString = true;
                $buffer = '';

                continue;
            }

            if ($char === ',') {
                $literal = trim($buffer);
                if ($literal !== '') {
                    $values[] = self::literal($literal);
                }
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        if ($inString) {
            throw new \RuntimeException('Unterminated string literal in backup file.');
        }

        $literal = trim($buffer);
        if ($literal !== '') {
            $values[] = self::literal($literal);
        }

        return $values;
    }

    /**
     * An unquoted token: NULL, a number, or a hex blob.
     */
    private static function literal(string $token): string|int|float|null
    {
        if (strcasecmp($token, 'NULL') === 0) {
            return null;
        }

        if (preg_match('/^0x([0-9a-fA-F]*)$/', $token, $matches) === 1) {
            return hex2bin($matches[1]) ?: '';
        }

        if (preg_match('/^-?\d+$/', $token) === 1) {
            return (int) $token;
        }

        if (is_numeric($token)) {
            return (float) $token;
        }

        throw new \RuntimeException("Unrecognised value in backup file: {$token}");
    }
}
