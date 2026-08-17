<?php

namespace App\Domain\Platform\Services;

use App\Domain\AiInsights\Models\AiInsight;
use App\Domain\AiInsights\Services\InsightGenerationService;
use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Finance\Services\FinanceAnalyticsService;
use App\Domain\Integrations\Models\Integration;
use App\Domain\Monitoring\Services\SystemMetricsService;
use App\Domain\Security\Services\SecurityScoreService;
use App\Domain\Shared\Models\AuditLog;
use App\Domain\Subscription\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * Powers the SuperAdmin Dashboard v2.0 widgets (KPI deltas/sparklines,
 * Business Pulse, Live Activity). Every figure is computed live from
 * BiasharaMax's own tables or the real system/security services built in
 * earlier sprints — nothing here is estimated or fabricated. Where a
 * metric genuinely isn't trackable yet (e.g. API request volume — no
 * metrics agent exists), it is honestly omitted rather than guessed.
 */
class PlatformPulseService
{
    public function __construct(
        private readonly SystemMetricsService $systemMetrics,
        private readonly SecurityScoreService $securityScore,
        private readonly FinanceAnalyticsService $financeAnalytics,
        private readonly InsightGenerationService $insights,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function kpis(): array
    {
        $businesses = Business::query();
        $totalBusinesses = (clone $businesses)->count();
        $activeBusinesses = (clone $businesses)->where('status', Business::STATUS_ACTIVE)->count();
        $inactiveBusinesses = (clone $businesses)->whereIn('status', [Business::STATUS_SUSPENDED, Business::STATUS_EXPIRED])->count();
        $trialBusinesses = (clone $businesses)->where('status', Business::STATUS_TRIAL)->count();

        $activeSubscriptions = Subscription::query()->whereIn('status', [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE])->count();

        $revenue = $this->financeAnalytics->revenueSummary();
        $mrrArr = $this->recurringRevenue();
        $system = $this->systemMetrics->currentSnapshot();
        $healthScores = collect($this->insights->businessHealthScores(500));

        return [
            'total_businesses' => $this->withTrend(fn ($from, $to) => Business::query()->whereBetween('created_at', [$from, $to])->count(), $totalBusinesses),
            'active_businesses' => $this->withTrend(fn ($from, $to) => Business::query()->where('status', Business::STATUS_ACTIVE)->whereBetween('updated_at', [$from, $to])->count(), $activeBusinesses),
            'inactive_businesses' => $this->withTrend(null, $inactiveBusinesses),
            'trial_businesses' => $this->withTrend(fn ($from, $to) => Business::query()->where('status', Business::STATUS_TRIAL)->whereBetween('created_at', [$from, $to])->count(), $trialBusinesses),
            'active_subscriptions' => $this->withTrend(fn ($from, $to) => Subscription::query()->whereBetween('created_at', [$from, $to])->count(), $activeSubscriptions),
            'monthly_revenue' => $this->withTrend(null, round($revenue['this_month'], 2)),
            'mrr' => $this->withTrend(null, $mrrArr['mrr']),
            'arr' => $this->withTrend(null, $mrrArr['arr']),
            'total_users' => $this->withTrend(fn ($from, $to) => User::query()->whereBetween('created_at', [$from, $to])->count(), User::query()->count()),
            'storage_usage' => $this->withTrend(null, $system['disk_usage']),
            'cpu_usage' => $this->withTrend(null, $system['cpu_usage']),
            'memory_usage' => $this->withTrend(null, $system['memory_usage']),
            'platform_health' => $this->withTrend(null, $system['health_score']),
            'ai_health_score' => $this->withTrend(null, $healthScores->isNotEmpty() ? round($healthScores->avg('health_score')) : null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function businessPulse(): array
    {
        $weekAgo = Carbon::now()->subDays(7);
        $system = $this->systemMetrics->currentSnapshot();
        $security = $this->securityScore->compute();
        $revenue = $this->financeAnalytics->revenueSummary();
        $lastMonthRevenue = $this->previousMonthRevenue();
        $churnRisk = $this->insights->churnRisk(50);

        return [
            'platform_health_score' => $system['health_score'],
            'revenue_change_percent' => $this->percentChange($lastMonthRevenue, $revenue['this_month']),
            'new_businesses_7d' => Business::query()->where('created_at', '>=', $weekAgo)->count(),
            'new_subscriptions_7d' => Subscription::query()->where('created_at', '>=', $weekAgo)->count(),
            'businesses_at_risk' => collect($churnRisk)->where('risk_score', '>=', 50)->count(),
            'inactive_businesses' => Business::query()->whereIn('status', [Business::STATUS_SUSPENDED, Business::STATUS_EXPIRED])->count(),
            'security_score' => $security['score'],
            'security_signals' => $security['signals'],
            'system_health_label' => $this->healthLabel($system['health_score']),
            'ai_recommendations' => AiInsight::query()->latest('created_at')->limit(5)->get(['id', 'title', 'summary', 'created_at']),
            'ai_configured' => Integration::query()->where('category', Integration::CATEGORY_AI)->where('is_enabled', true)->exists(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function liveActivity(int $limit = 12): array
    {
        $logs = AuditLog::query()
            ->with('business')
            ->latest('created_at')
            ->limit($limit)
            ->get();

        $platformUserIds = $logs->where('actor_type', 'platform_user')->pluck('actor_id')->filter()->unique();
        $userIds = $logs->where('actor_type', 'user')->pluck('actor_id')->filter()->unique();

        $platformUsers = PlatformUser::query()->whereIn('id', $platformUserIds)->pluck('name', 'id');
        $users = User::query()->whereIn('id', $userIds)->pluck('name', 'id');

        return $logs->map(function (AuditLog $log) use ($platformUsers, $users) {
            $actorName = match ($log->actor_type) {
                'platform_user' => $platformUsers->get($log->actor_id, 'A SuperAdmin'),
                'user' => $users->get($log->actor_id, 'A user'),
                default => 'System',
            };

            return [
                'id' => $log->id,
                'actor_name' => $actorName,
                'actor_type' => $log->actor_type,
                'module' => $log->module,
                'action' => $log->action,
                'auditable_type' => $log->auditable_type ? class_basename($log->auditable_type) : null,
                'business_name' => $log->business?->name,
                'created_at' => $log->created_at,
            ];
        })->all();
    }

    /**
     * @return array{mrr: float, arr: float}
     */
    private function recurringRevenue(): array
    {
        $mrr = Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->with('plan')
            ->get()
            ->sum(function (Subscription $subscription) {
                if (! $subscription->plan || ! $subscription->billing_cycle) {
                    return 0;
                }

                return match ($subscription->billing_cycle) {
                    'monthly' => (float) $subscription->plan->price_monthly,
                    'quarterly' => (float) $subscription->plan->price_quarterly / 3,
                    'yearly' => (float) $subscription->plan->price_yearly / 12,
                    default => 0,
                };
            });

        return [
            'mrr' => round($mrr, 2),
            'arr' => round($mrr * 12, 2),
        ];
    }

    private function previousMonthRevenue(): float
    {
        return (float) PaymentTransaction::query()
            ->where('status', PaymentTransaction::STATUS_SUCCESSFUL)
            ->whereBetween('paid_at', [
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
            ])
            ->sum('amount');
    }

    private function percentChange(float $previous, float $current): ?float
    {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function healthLabel(float $score): string
    {
        return match (true) {
            $score >= 90 => 'Excellent',
            $score >= 70 => 'Good',
            $score >= 50 => 'Needs Attention',
            default => 'Critical',
        };
    }

    /**
     * Builds a {value, trend[14 days], change_percent} envelope. When a
     * daily-counter closure is given, the trend is real per-day counts;
     * otherwise the metric is a live gauge (e.g. CPU%) with no historical
     * series available, so only the current value is returned.
     *
     * @param  (callable(Carbon, Carbon): int)|null  $dailyCounter
     */
    private function withTrend(?callable $dailyCounter, mixed $value): array
    {
        if ($dailyCounter === null) {
            return ['value' => $value, 'trend' => null, 'change_percent' => null];
        }

        $days = collect(range(13, 0))->map(function (int $offset) use ($dailyCounter) {
            $day = Carbon::now()->subDays($offset);

            return (int) $dailyCounter($day->copy()->startOfDay(), $day->copy()->endOfDay());
        });

        $today = $days->last();
        $yesterday = $days->slice(-2, 1)->first() ?? 0;
        $changePercent = $yesterday > 0
            ? round((($today - $yesterday) / $yesterday) * 100, 1)
            : ($today > 0 ? 100.0 : null);

        return [
            'value' => $value,
            'trend' => $days->values()->all(),
            'change_percent' => $changePercent,
            'today_change' => $today - $yesterday,
        ];
    }
}
