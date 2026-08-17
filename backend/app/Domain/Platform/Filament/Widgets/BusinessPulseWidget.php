<?php

namespace App\Domain\Platform\Filament\Widgets;

use App\Domain\Platform\Services\PlatformPulseService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Mirrors the old dashboard's "Business Pulse" card — same 8 figures
 * from PlatformPulseService::businessPulse() (health score, revenue
 * delta vs last month, 7-day new business/subscription counts,
 * at-risk/inactive counts, security score, system health label).
 * AI recommendations from the same payload are rendered separately by
 * AiRecommendationsWidget rather than crammed into this stats grid.
 */
class BusinessPulseWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getHeading(): ?string
    {
        return 'Business Pulse';
    }

    protected function getStats(): array
    {
        $pulse = app(PlatformPulseService::class)->businessPulse();

        $revenueChange = $pulse['revenue_change_percent'];

        return [
            Stat::make('Overall platform health', number_format($pulse['platform_health_score'], 1).'%')
                ->description($this->healthLabel($pulse['platform_health_score']))
                ->icon(Heroicon::ShieldCheck)
                ->color($this->scoreColor($pulse['platform_health_score'])),
            Stat::make('Revenue vs last month', $revenueChange === null ? '—' : ($revenueChange >= 0 ? '+' : '').number_format($revenueChange, 1).'%')
                ->icon($revenueChange !== null && $revenueChange < 0 ? Heroicon::ArrowTrendingDown : Heroicon::ArrowTrendingUp)
                ->color($revenueChange === null ? 'gray' : ($revenueChange >= 0 ? 'success' : 'danger')),
            Stat::make('New businesses (7d)', $pulse['new_businesses_7d'])
                ->icon(Heroicon::BuildingOffice2),
            Stat::make('New subscriptions (7d)', $pulse['new_subscriptions_7d'])
                ->icon(Heroicon::CreditCard),
            Stat::make('Businesses at risk', $pulse['businesses_at_risk'])
                ->icon(Heroicon::ExclamationTriangle)
                ->color($pulse['businesses_at_risk'] > 0 ? 'danger' : 'success'),
            Stat::make('Inactive businesses', $pulse['inactive_businesses'])
                ->icon(Heroicon::PauseCircle)
                ->color('gray'),
            Stat::make('Security score', number_format($pulse['security_score'], 1).'%')
                ->icon(Heroicon::LockClosed)
                ->color($this->scoreColor($pulse['security_score'])),
            Stat::make('System health', $pulse['system_health_label'])
                ->icon(Heroicon::Signal)
                ->color(match ($pulse['system_health_label']) {
                    'Excellent', 'Good' => 'success',
                    'Needs Attention' => 'warning',
                    default => 'danger',
                }),
        ];
    }

    protected function getColumns(): int
    {
        return 4;
    }

    private function scoreColor(?float $score): string
    {
        return match (true) {
            $score === null => 'gray',
            $score >= 90 => 'success',
            $score >= 70 => 'success',
            $score >= 50 => 'warning',
            default => 'danger',
        };
    }

    private function healthLabel(?float $score): string
    {
        return match (true) {
            $score === null => 'Unknown',
            $score >= 90 => 'Excellent',
            $score >= 70 => 'Good',
            $score >= 50 => 'Needs Attention',
            default => 'Critical',
        };
    }
}
