<?php

namespace App\Domain\Platform\Filament\Widgets;

use App\Domain\Platform\Services\PlatformAnalyticsService;
use Filament\Widgets\Widget;

/**
 * Mirrors the old dashboard's Country Distribution breakdown, backed by
 * PlatformAnalyticsService::countryDistribution(). Rendered as a
 * proportional bar list (see PaymentMethodsChartWidget's docblock for
 * why, vs. a Chart.js doughnut).
 */
class CountryDistributionChartWidget extends Widget
{
    protected string $view = 'filament.platform.widgets.country-distribution';

    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 1;

    protected function getViewData(): array
    {
        $countries = app(PlatformAnalyticsService::class)->countryDistribution();
        $max = collect($countries)->max('count') ?: 1;

        return [
            'countries' => collect($countries)->map(fn (array $row) => [
                'label' => $row['label'],
                'count' => $row['count'],
                'percent' => round(($row['count'] / $max) * 100),
            ])->all(),
        ];
    }
}
