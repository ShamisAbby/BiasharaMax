<?php

namespace App\Domain\Platform\Filament\Widgets;

use App\Domain\Platform\Services\PlatformAnalyticsService;
use Filament\Widgets\ChartWidget;

/**
 * Mirrors the old dashboard's Subscription Growth bar chart, backed by
 * PlatformAnalyticsService::subscriptionGrowth() — a proxy metric (new
 * subscriptions per month) since there's no separate growth ledger, same
 * caveat as the original service's docblock.
 */
class SubscriptionGrowthChartWidget extends ChartWidget
{
    protected ?string $heading = 'Subscription Growth';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $growth = app(PlatformAnalyticsService::class)->subscriptionGrowth();

        return [
            'datasets' => [
                [
                    'label' => 'New subscriptions',
                    'data' => array_column($growth, 'count'),
                ],
            ],
            'labels' => array_column($growth, 'label'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
