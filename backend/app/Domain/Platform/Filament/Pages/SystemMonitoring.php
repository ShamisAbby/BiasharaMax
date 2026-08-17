<?php

namespace App\Domain\Platform\Filament\Pages;

use App\Domain\Monitoring\Models\BackupRecord;
use App\Domain\Monitoring\Models\SystemHealthSnapshot;
use App\Domain\Monitoring\Services\SystemMetricsService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Custom page, not a Resource — mirrors
 * App\Domain\Platform\Http\Controllers\MonitoringController exactly.
 *
 * Every number comes from SystemMetricsService rather than being
 * recomputed here, so this panel and the old /admin screen can never
 * disagree about whether the platform is healthy. That service is the
 * one place that knows how CPU, memory and disk are actually read on
 * this host.
 *
 * Live metrics are resolved per request in a property accessor rather
 * than in mount(). `currentSnapshot()` shells out and touches Redis, so
 * caching it into a public Livewire property would both serialise the
 * whole payload into every subsequent request's component state and
 * freeze the reading at first paint — a monitoring page that stops
 * updating is worse than one that costs a query.
 */
class SystemMonitoring extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Security';

    protected static ?string $navigationLabel = 'System Monitoring';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'System Monitoring';

    protected string $view = 'filament.platform.pages.system-monitoring';

    /**
     * Mirrors the `platform.permission:monitoring.view` middleware the
     * Inertia route carries. Filament hides a page from navigation *and*
     * refuses direct URL access from this one method, so the two surfaces
     * gate on exactly the same permission string.
     */
    public static function canAccess(): bool
    {
        return Auth::guard('platform')->user()?->hasPlatformPermission('monitoring.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLive(): array
    {
        return app(SystemMetricsService::class)->currentSnapshot();
    }

    /**
     * The 24-hour trend, thinned for display.
     *
     * Snapshots are recorded every 5 minutes, so a full day is ~288 rows
     * — more points than the width of the chart has pixels. Taking every
     * fourth gives roughly hourly resolution, which is what the shape of
     * a day actually reads at.
     *
     * @return Collection<int, SystemHealthSnapshot>
     */
    public function getTrend(): Collection
    {
        return SystemHealthSnapshot::query()
            ->where('recorded_at', '>=', now()->subHours(24))
            ->orderBy('recorded_at')
            ->get(['cpu_usage', 'memory_usage', 'disk_usage', 'health_score', 'recorded_at'])
            ->filter(fn ($snapshot, int $index): bool => $index % 4 === 0)
            ->values();
    }

    /**
     * @return Collection<int, BackupRecord>
     */
    public function getBackups(): Collection
    {
        return BackupRecord::query()->latest('started_at')->limit(20)->get();
    }

    /**
     * Colour band for a percentage where *high is bad* — CPU, memory,
     * disk. Deliberately not shared with the health score below, which
     * runs the other way.
     */
    public function usageTone(float|int|null $value): string
    {
        return match (true) {
            $value === null => 'gray',
            $value >= 90 => 'red',
            $value >= 75 => 'amber',
            default => 'emerald',
        };
    }

    /**
     * Colour band for the health score, where *high is good*.
     *
     * Kept separate from usageTone() on purpose: collapsing the two into
     * one helper with an `$invert` flag is how a 96% memory reading ends
     * up painted green.
     */
    public function healthTone(float|int|null $value): string
    {
        return match (true) {
            $value === null => 'gray',
            $value >= 80 => 'emerald',
            $value >= 50 => 'amber',
            default => 'red',
        };
    }
}
