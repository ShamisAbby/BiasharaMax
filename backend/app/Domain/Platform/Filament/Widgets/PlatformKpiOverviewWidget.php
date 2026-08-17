<?php

namespace App\Domain\Platform\Filament\Widgets;

use App\Domain\Platform\Services\PlatformPulseService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * Mirrors the old /admin dashboard's top KPI grid exactly — every value
 * comes straight from PlatformPulseService::kpis(), the same service the
 * Inertia dashboard uses, so numbers can never drift between the two
 * admin surfaces while they coexist. Built as a custom Widget (not
 * StatsOverviewWidget) to match the old UI's colored-icon-square card
 * style, which Filament's native Stat component doesn't render — see
 * public/css/filament/platform/dashboard.css for the `.bos-kpi-*` rules
 * this view uses. Metrics with a real 14-day daily series (business/
 * subscription/user counts) get a sparkline and a day-over-day change
 * badge; live gauges (CPU/memory/storage/health scores, revenue
 * figures) show the current value only, matching kpis()'s own
 * `trend: null` contract for those keys.
 */
class PlatformKpiOverviewWidget extends Widget
{
    protected string $view = 'filament.platform.widgets.kpi-overview';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $kpis = app(PlatformPulseService::class)->kpis();

        // Colors match the old /admin dashboard's KPI icons exactly,
        // per-metric (read directly from Pages/Platform/Dashboard.tsx —
        // each card there has its own distinct Tailwind `-600`/`-700`
        // color, not a single uniform brand color).
        return [
            'cards' => [
                $this->card('Total businesses', $kpis['total_businesses'], Heroicon::BuildingOffice2, 'indigo'),
                $this->card('Active businesses', $kpis['active_businesses'], Heroicon::CheckCircle, 'emerald'),
                $this->card('Inactive businesses', $kpis['inactive_businesses'], Heroicon::PauseCircle, 'gray'),
                $this->card('Trial businesses', $kpis['trial_businesses'], Heroicon::Clock, 'blue'),
                $this->card('Active subscriptions', $kpis['active_subscriptions'], Heroicon::CreditCard, 'cyan'),
                $this->card('Monthly revenue', $kpis['monthly_revenue'], Heroicon::Banknotes, 'amber', formatter: fn ($v) => number_format($v, 2)),
                $this->card('MRR', $kpis['mrr'], Heroicon::ChartBar, 'violet', formatter: fn ($v) => number_format($v, 2)),
                $this->card('ARR', $kpis['arr'], Heroicon::ChartBar, 'purple-dark', formatter: fn ($v) => number_format($v, 2)),
                $this->card('Total users', $kpis['total_users'], Heroicon::Users, 'purple'),
                $this->card('Storage usage', $kpis['storage_usage'], Heroicon::CircleStack, 'slate', formatter: fn ($v) => $this->percent($v)),
                $this->card('CPU usage', $kpis['cpu_usage'], Heroicon::ServerStack, 'orange', formatter: fn ($v) => $this->percent($v)),
                $this->card('Memory usage', $kpis['memory_usage'], Heroicon::ServerStack, 'red', formatter: fn ($v) => $this->percent($v)),
                $this->card('Platform health', $kpis['platform_health'], Heroicon::ShieldCheck, 'emerald-dark', formatter: fn ($v) => $this->percent($v)),
                $this->card('AI health score', $kpis['ai_health_score'], Heroicon::Sparkles, 'pink', formatter: fn ($v) => $this->percent($v)),
            ],
        ];
    }

    /**
     * @param  array{value: mixed, trend: ?array<int>, change_percent: ?float}  $envelope
     */
    private function card(string $label, array $envelope, Heroicon $icon, string $color, ?\Closure $formatter = null): array
    {
        $value = $envelope['value'];

        return [
            'label' => $label,
            'value' => $formatter ? $formatter($value) : (string) $value,
            'icon' => $icon,
            'color' => $color,
            'trend' => $envelope['trend'],
            'changePercent' => $envelope['change_percent'],
        ];
    }

    private function percent(?float $value): string
    {
        return $value === null ? '—' : number_format($value, 1).'%';
    }
}
