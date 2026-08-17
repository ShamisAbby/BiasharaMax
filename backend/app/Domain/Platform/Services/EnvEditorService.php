<?php

namespace App\Domain\Platform\Services;

class EnvEditorService
{
    private string $path;

    /** Keys never allowed to be overwritten through the UI */
    private const PROTECTED = ['APP_KEY'];

    public function __construct()
    {
        $this->path = base_path('.env');
    }

    /**
     * Read the current values for a named group from the .env file.
     *
     * @return array<string, string>
     */
    public function getGroup(string $group): array
    {
        $prefix = $this->prefix($group);
        $all    = $this->readAll();
        $result = [];

        foreach ($all as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $shortKey = strtolower(substr($key, strlen($prefix)));
                $result[$shortKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Write an array of env key-value pairs back to the .env file.
     *
     * Keys must be in UPPER_CASE (APP_NAME, MAIL_HOST, etc.).
     * Protected keys are silently skipped.
     *
     * @param array<string, string> $values
     */
    public function set(array $values): void
    {
        $content = file_get_contents($this->path);

        foreach ($values as $envKey => $value) {
            $envKey = strtoupper($envKey);

            if (in_array($envKey, self::PROTECTED, true)) {
                continue;
            }

            $line = $envKey . '=' . $this->quoteValue((string) $value);

            if (preg_match('/^' . preg_quote($envKey, '/') . '\s*=/m', $content)) {
                $content = preg_replace(
                    '/^' . preg_quote($envKey, '/') . '\s*=.*/m',
                    $line,
                    $content,
                );
            } else {
                $content .= "\n" . $line;
            }
        }

        file_put_contents($this->path, $content);

        // Make the values visible to the current request via putenv()
        foreach ($values as $envKey => $value) {
            $envKey = strtoupper($envKey);
            if (! in_array($envKey, self::PROTECTED, true)) {
                putenv("$envKey=$value");
                $_ENV[$envKey]    = $value;
                $_SERVER[$envKey] = $value;
            }
        }
    }

    /**
     * Parse every non-comment, non-blank KEY=VALUE line from the .env file.
     *
     * @return array<string, string>
     */
    private function readAll(): array
    {
        $lines  = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $result = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }

            [$rawKey, $rawValue] = explode('=', $line, 2);
            $key   = trim($rawKey);
            $value = trim($rawValue);

            // Strip surrounding single or double quotes
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Return the ENV_VAR prefix string for a logical group name.
     */
    private function prefix(string $group): string
    {
        return match ($group) {
            'app'      => 'APP_',
            'mail'     => 'MAIL_',
            'database' => 'DB_',
            default    => throw new \InvalidArgumentException("Unknown env group: $group"),
        };
    }

    /**
     * Quote a value for safe storage in a .env file.
     *
     * Booleans and plain numbers are left unquoted. Everything else gets
     * double-quoted so spaces, @-signs, and # characters are preserved.
     */
    private function quoteValue(string $value): string
    {
        if (in_array($value, ['true', 'false', 'null', ''], true)) {
            return $value;
        }

        if (is_numeric($value)) {
            return $value;
        }

        return '"' . str_replace('"', '\\"', $value) . '"';
    }
}
