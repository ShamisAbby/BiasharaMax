<?php

namespace App\Domain\Platform\Filament\Widgets;

use App\Domain\Platform\Services\PlatformAnalyticsService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Mirrors the old dashboard's "Queue & Background Jobs" card, backed by
 * PlatformAnalyticsService::queueSnapshot() — real Horizon-backed queue
 * counts, not fabricated server metrics.
 */
class QueueSnapshotWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 13;

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return 'Queue & Background Jobs';
    }

    protected function getStats(): array
    {
        $queue = app(PlatformAnalyticsService::class)->queueSnapshot();

        return [
            Stat::make('Pending jobs', $queue['pending_jobs'] ?? '—')
                ->icon(Heroicon::Clock),
            Stat::make('Failed jobs', $queue['failed_jobs'])
                ->icon(Heroicon::ExclamationTriangle)
                ->color($queue['failed_jobs'] > 0 ? 'danger' : 'success'),
            Stat::make('Horizon', $queue['horizon_available'] ? 'Available' : 'Not installed')
                ->icon(Heroicon::Bolt)
                ->color($queue['horizon_available'] ? 'success' : 'gray'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}
