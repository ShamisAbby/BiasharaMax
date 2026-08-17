<?php

namespace App\Domain\Platform\Filament\Widgets;

use App\Domain\Platform\Filament\Resources\Businesses\BusinessResource;
use App\Domain\Platform\Services\PlatformAnalyticsService;
use Filament\Widgets\Widget;

/**
 * Mirrors the old dashboard's Top Business Types breakdown, backed by
 * PlatformAnalyticsService::topBusinessTypes(). Rendered as a
 * proportional bar list (see PaymentMethodsChartWidget's docblock for
 * why, vs. a Chart.js doughnut). The old UI's empty state has a "Create
 * Business" button — BusinessResource has no create page in this rebuild
 * (businesses are created via tenant signup, not an admin form), so this
 * links to the Businesses list instead of a nonexistent create route.
 */
class TopBusinessTypesChartWidget extends Widget
{
    protected string $view = 'filament.platform.widgets.top-business-types';

    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 1;

    protected function getViewData(): array
    {
        $types = app(PlatformAnalyticsService::class)->topBusinessTypes();
        $max = collect($types)->max('count') ?: 1;

        return [
            'types' => collect($types)->map(fn (array $row) => [
                'label' => $row['label'],
                'count' => $row['count'],
                'percent' => round(($row['count'] / $max) * 100),
            ])->all(),
            'businessesUrl' => BusinessResource::getUrl(),
        ];
    }
}
