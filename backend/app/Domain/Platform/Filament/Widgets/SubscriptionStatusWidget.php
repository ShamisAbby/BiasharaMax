<?php

namespace App\Domain\Platform\Filament\Widgets;

use App\Domain\Platform\Services\PlatformAnalyticsService;
use Filament\Widgets\Widget;

/**
 * Mirrors the old dashboard's Subscription Status badge/count list,
 * backed by PlatformAnalyticsService::subscriptionStatusBreakdown().
 */
class SubscriptionStatusWidget extends Widget
{
    protected string $view = 'filament.platform.widgets.subscription-status';

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 1;

    protected function getViewData(): array
    {
        return [
            'statuses' => app(PlatformAnalyticsService::class)->subscriptionStatusBreakdown(),
        ];
    }
}
