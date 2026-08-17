<?php

namespace App\Domain\Platform\Filament\Widgets;

use App\Domain\Platform\Services\PlatformAnalyticsService;
use App\Domain\Platform\Services\PlatformPulseService;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Matches the old Inertia dashboard's header exactly: time-of-day
 * greeting with the signed-in SuperAdmin's first name, date + platform
 * version line, and 3 status badges (overall system health label,
 * database connectivity, Redis connectivity) — sourced from the same
 * PlatformAnalyticsService::overview()['system_health'] and
 * PlatformPulseService::businessPulse()['system_health_label'] the
 * Inertia page uses, so the badges can't drift out of sync between the
 * two admin surfaces.
 */
class DashboardHeaderWidget extends Widget
{
    protected string $view = 'filament.platform.widgets.dashboard-header';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $overview = app(PlatformAnalyticsService::class)->overview();
        $pulse = app(PlatformPulseService::class)->businessPulse();
        $user = Auth::guard('platform')->user();
        $firstName = $user ? str($user->name)->before(' ')->toString() : 'there';

        return [
            'greeting' => $this->greeting(),
            'firstName' => $firstName,
            'today' => Carbon::now(),
            'platformVersion' => config('app.version', '1.0.0'),
            'healthLabel' => $pulse['system_health_label'],
            'healthColor' => match ($pulse['system_health_label']) {
                'Excellent', 'Good' => 'success',
                'Needs Attention' => 'warning',
                default => 'danger',
            },
            'databaseOnline' => $overview['system_health']['database'],
            'redisOnline' => $overview['system_health']['redis'],
        ];
    }

    private function greeting(): string
    {
        $hour = (int) Carbon::now()->format('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 18 => 'Good afternoon',
            default => 'Good evening',
        };
    }
}
