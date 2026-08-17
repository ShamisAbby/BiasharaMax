<?php

namespace App\Domain\Platform\Filament\Widgets;

use App\Domain\Finance\Services\FinanceAnalyticsService;
use Filament\Widgets\ChartWidget;

/**
 * Mirrors the old dashboard's 12-month Revenue Trend line chart, backed
 * by the same FinanceAnalyticsService::monthlyTrend() call.
 */
class RevenueTrendChartWidget extends ChartWidget
{
    protected ?string $heading = 'Revenue Trend';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 2;

    protected string $color = 'success';

    protected function getData(): array
    {
        $trend = app(FinanceAnalyticsService::class)->monthlyTrend();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => array_column($trend, 'amount'),
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
