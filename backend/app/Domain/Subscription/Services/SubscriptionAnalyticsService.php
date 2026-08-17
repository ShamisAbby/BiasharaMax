<?php

namespace App\Domain\Subscription\Services;

use App\Domain\Shared\Support\DateFormatSql;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionTransaction;
use Illuminate\Support\Carbon;

/**
 * Real numbers only: revenue here is the sum of manually-recorded
 * SubscriptionTransaction rows (what a SuperAdmin confirmed was actually
 * paid), not a projection from active-subscription prices.
 */
class SubscriptionAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        return [
            'revenue' => $this->revenueSummary(),
            'trial' => $this->trialSummary(),
            'subscribers' => $this->subscriberCounts(),
            'expiring_soon' => $this->expiringSubscriptions(),
            'monthly_revenue' => $this->monthlyRevenueTrend(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function revenueSummary(): array
    {
        $paid = SubscriptionTransaction::query()->where('status', SubscriptionTransaction::STATUS_PAID);

        return [
            'total' => (float) $paid->sum('amount'),
            'this_month' => (float) (clone $paid)->whereBetween('paid_at', [
                Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth(),
            ])->sum('amount'),
            'this_year' => (float) (clone $paid)->whereBetween('paid_at', [
                Carbon::now()->startOfYear(), Carbon::now()->endOfYear(),
            ])->sum('amount'),
        ];
    }

    /**
     * @return array<int, array{label: string, amount: float}>
     */
    public function monthlyRevenueTrend(): array
    {
        $since = Carbon::now()->startOfMonth()->subMonths(11);

        $rows = SubscriptionTransaction::query()
            ->where('status', SubscriptionTransaction::STATUS_PAID)
            ->where('paid_at', '>=', $since)
            ->selectRaw(DateFormatSql::monthly('paid_at')." as month, sum(amount) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        $trend = [];

        for ($date = $since->copy(); $date->lte(Carbon::now()); $date->addMonth()) {
            $key = $date->format('Y-m');

            $trend[] = [
                'label' => $date->format('M Y'),
                'amount' => (float) ($rows[$key] ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * @return array<string, int>
     */
    public function trialSummary(): array
    {
        return [
            'active' => Subscription::query()->where('status', Subscription::STATUS_TRIALING)->count(),
            'ending_in_3_days' => Subscription::query()
                ->where('status', Subscription::STATUS_TRIALING)
                ->whereBetween('trial_ends_at', [Carbon::now(), Carbon::now()->addDays(3)])
                ->count(),
            'ending_in_7_days' => Subscription::query()
                ->where('status', Subscription::STATUS_TRIALING)
                ->whereBetween('trial_ends_at', [Carbon::now(), Carbon::now()->addDays(7)])
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function subscriberCounts(): array
    {
        return Subscription::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();
    }

    /**
     * Active subscriptions whose paid period ends within 14 days —
     * the renewal-reminder worklist for a SuperAdmin.
     *
     * @return \Illuminate\Support\Collection<int, Subscription>
     */
    public function expiringSubscriptions()
    {
        return Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereBetween('current_period_end', [Carbon::now(), Carbon::now()->addDays(14)])
            ->with('business', 'plan')
            ->orderBy('current_period_end')
            ->limit(20)
            ->get();
    }
}
