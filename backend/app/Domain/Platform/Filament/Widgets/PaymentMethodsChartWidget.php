<?php

namespace App\Domain\Platform\Filament\Widgets;

use App\Domain\Finance\Services\FinanceAnalyticsService;
use Filament\Widgets\Widget;

/**
 * Mirrors the old dashboard's Payment Methods breakdown, backed by
 * FinanceAnalyticsService::topPaymentMethods(5). Rendered as a simple
 * proportional bar list rather than a Chart.js doughnut — this panel
 * doesn't yet have a verified way to load Chart.js for a hand-rolled
 * canvas outside Filament's own ChartWidget wiring, so a safe, always-
 * correct list (matching Subscription Status's style) was chosen over
 * an unverified custom chart. Shows the old UI's empty-state copy when
 * there's no data yet.
 */
class PaymentMethodsChartWidget extends Widget
{
    protected string $view = 'filament.platform.widgets.payment-methods';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    protected function getViewData(): array
    {
        $methods = app(FinanceAnalyticsService::class)->topPaymentMethods(5);
        $max = collect($methods)->max('total') ?: 1;

        return [
            'methods' => collect($methods)->map(fn (array $row) => [
                'label' => str($row['payment_method'])->headline()->toString(),
                'total' => $row['total'],
                'percent' => round(($row['total'] / $max) * 100),
            ])->all(),
        ];
    }
}
