<?php

namespace App\Domain\Platform\Filament\Widgets;

use App\Domain\Platform\Services\PlatformAnalyticsService;
use Filament\Widgets\ChartWidget;

/**
 * Mirrors the old dashboard's 12-month Business Registration Trend line
 * chart, backed by PlatformAnalyticsService::businessRegistrationTrend().
 */
class BusinessRegistrationTrendChartWidget extends ChartWidget
{
    protected ?string $heading = 'Business Registration Trend';

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 2;

    protected function getData(): array
    {
        $trend = app(PlatformAnalyticsService::class)->businessRegistrationTrend();

        return [
            'datasets' => [
                [
                    'label' => 'New businesses',
                    'data' => array_column($trend, 'count'),
                    'fill' => true,
                ],
            ],
            'labels' => array_column($trend, 'label'),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
