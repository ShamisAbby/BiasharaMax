<?php

namespace App\Domain\Platform\Services;

use Illuminate\Support\Facades\Cache;

/**
 * The platform health badge shown in both admin surfaces' top bars.
 *
 * Extracted because the two disagreed, and not subtly: the Filament
 * panel computed a real status from PlatformAnalyticsService and
 * PlatformPulseService, while the Inertia admin rendered a hardcoded
 * green dot reading "Operational" — literal text, derived from nothing.
 * An operator could watch Redis fall over on /platform and see
 * "Operational" on /admin at the same moment.
 *
 * That is the failure mode worth naming. A status indicator that cannot
 * report a problem is worse than no indicator, because it actively
 * reassures. Anyone glancing at the top bar to answer "is the platform
 * up" was being told yes unconditionally.
 *
 * One implementation, two callers: PlatformPanelProvider's render hook
 * and HandleInertiaRequests' shared props. The cache key is shared too,
 * so both surfaces read the same value within the same 60-second window
 * — without that they could still disagree for a minute after a change,
 * which is exactly long enough to be confusing while debugging.
 *
 * @see \App\Providers\Filament\PlatformPanelProvider
 * @see \App\Http\Middleware\HandleInertiaRequests
 */
class PlatformStatusBadgeService
{
    private const CACHE_KEY = 'platform.topbar.health';

    /**
     * Short by design. This is a liveness indicator; a stale "everything
     * is fine" is the thing it exists to prevent. Sixty seconds keeps it
     * off the hot path for a topbar that renders on every request while
     * still surfacing an outage promptly.
     */
    private const CACHE_TTL = 60;

    public function __construct(
        private readonly PlatformAnalyticsService $analytics,
        private readonly PlatformPulseService $pulse,
    ) {}

    /**
     * @return array{
     *     color: string,
     *     label: string,
     *     title: string,
     *     database: bool,
     *     redis: bool,
     *     redisInUse: bool,
     *     healthLabel: string
     * }
     */
    public function current(): array
    {
        $health = $this->health();

        $color = $this->color($health);

        return [
            'color' => $color,
            'label' => match ($color) {
                'success' => 'Operational',
                'warning' => 'Degraded',
                default => 'Down',
            },
            // "not in use" rather than "online" where Redis isn't
            // configured. Reporting a service as online when it isn't
            // even installed is the sort of reassurance this class was
            // extracted to stop.
            'title' => "{$health['label']} · DB "
                .($health['database'] ? 'online' : 'offline')
                .' · Redis '.match (true) {
                    ! $health['redis_in_use'] => 'not in use',
                    $health['redis'] => 'online',
                    default => 'offline',
                },
            'database' => $health['database'],
            'redis' => $health['redis'],
            'redisInUse' => $health['redis_in_use'],
            'healthLabel' => $health['label'],
        ];
    }

    /**
     * Clears the shared cache so both surfaces pick up a change at once.
     * Useful after a deliberate restart rather than waiting out the TTL.
     */
    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{database: bool, redis: bool, redis_in_use: bool, label: string}
     */
    private function health(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $overview = $this->analytics->overview();
            $pulse = $this->pulse->businessPulse();

            return [
                'database' => (bool) $overview['system_health']['database'],
                'redis' => (bool) $overview['system_health']['redis'],
                'redis_in_use' => (bool) ($overview['system_health']['redis_in_use'] ?? true),
                'label' => $pulse['system_health_label'],
            ];
        });
    }

    /**
     * The whole decision, as a pure function of its three inputs.
     *
     * Public and static so it can be tested across every combination
     * without a database or a Redis to point at. That matters here: the
     * interesting cases are the unhealthy ones, and a test that reads
     * live infrastructure can only assert whatever the machine happens
     * to be doing — which is normally "fine", i.e. the case that needs
     * the least checking.
     *
     * Note there are two independent routes to `danger`, and conflating
     * them is a real mistake to avoid: infrastructure being down, and a
     * critical health score while everything is technically reachable.
     * A platform at 96% memory with the database up is genuinely in
     * trouble, so "database and Redis are fine" does **not** imply the
     * badge is anything better than Down.
     */
    public static function colorFor(bool $database, bool $redis, string $healthLabel): string
    {
        return match (true) {
            // Infrastructure first: unreachable outranks any derived
            // score, because the score is computed from data we may not
            // have been able to read.
            ! $database, ! $redis => 'danger',
            in_array($healthLabel, ['Excellent', 'Good'], true) => 'success',
            $healthLabel === 'Needs Attention' => 'warning',
            default => 'danger',
        };
    }

    /**
     * @param  array{database: bool, redis: bool, redis_in_use: bool, label: string}  $health
     */
    private function color(array $health): string
    {
        return self::colorFor($health['database'], $health['redis'], $health['label']);
    }
}
