<?php

namespace App\Domain\Developer\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Live introspection only — every method reads real framework state
 * (the actual route table, the actual migrations table, the actual
 * failed_jobs table). Nothing here is mocked or sample data.
 */
class DeveloperToolsService
{
    /**
     * @return array<int, array{method: string, uri: string, name: ?string, action: string, middleware: array<int, string>}>
     */
    public function routeList(): array
    {
        return collect(Route::getRoutes())
            ->map(fn ($route) => [
                'method' => implode('|', array_diff($route->methods(), ['HEAD'])),
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => is_string($route->getActionName()) ? $route->getActionName() : 'Closure',
                'middleware' => array_values($route->gatherMiddleware()),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{migration: string, batch: ?int, ran: bool}>
     */
    public function migrationStatus(): array
    {
        $ran = DB::table('migrations')->pluck('batch', 'migration');

        $files = collect(glob(database_path('migrations/*.php')))
            ->map(fn ($path) => pathinfo($path, PATHINFO_FILENAME));

        return $files->map(fn ($migration) => [
            'migration' => $migration,
            'batch' => $ran[$migration] ?? null,
            'ran' => $ran->has($migration),
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function queueStatus(): array
    {
        return [
            'failed_jobs' => DB::table('failed_jobs')->count(),
            'connection' => config('queue.default'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function systemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'timezone' => config('app.timezone'),
            'database_driver' => config('database.default'),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'session_driver' => config('session.driver'),
        ];
    }

    public function clearCache(): void
    {
        Cache::flush();
    }

    /**
     * Redacts anything that looks like a secret before display —
     * config values are real, but never shown raw if the key name
     * suggests a credential.
     *
     * @return array<string, mixed>
     */
    public function configSnapshot(string $key): mixed
    {
        $value = config($key);

        return $this->redact($key, $value);
    }

    private function redact(string $key, mixed $value): mixed
    {
        if (is_array($value)) {
            return collect($value)->mapWithKeys(fn ($v, $k) => [$k => $this->redact((string) $k, $v)])->all();
        }

        if (preg_match('/secret|password|key|token/i', $key) && is_string($value) && $value !== '') {
            return '••••••••';
        }

        return $value;
    }
}
