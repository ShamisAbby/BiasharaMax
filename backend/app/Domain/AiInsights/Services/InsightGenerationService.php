<?php

namespace App\Domain\AiInsights\Services;

use App\Domain\AiInsights\Models\AiInsight;
use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Subscription\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * Every number here comes from a real, explainable statistical method
 * (linear trend, recency scoring, group-by aggregation) over real
 * platform data — the same "rule-based, not a black box" philosophy as
 * Monitoring's health score. No machine-learning model is trained or
 * claimed. Narrative commentary on top of these numbers is optional and
 * handled separately by AiNarrativeService, only when a real AI
 * provider integration is configured.
 */
class InsightGenerationService
{
    /**
     * Linear trend extrapolation over the last 6 months of real revenue.
     *
     * @return array<string, mixed>
     */
    public function revenueForecast(): array
    {
        $months = $this->monthlyRevenueSeries(6);
        $values = array_values($months);
        $trend = $this->linearTrendNextValue($values);

        return [
            'history' => $months,
            'forecast_next_month' => max(0, round($trend, 2)),
            'method' => 'linear_trend_extrapolation',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function subscriptionForecast(): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();
            $months[$start->format('M Y')] = Subscription::query()->whereBetween('created_at', [$start, $end])->count();
        }

        $trend = $this->linearTrendNextValue(array_values($months));

        return [
            'history' => $months,
            'forecast_next_month' => max(0, round($trend)),
            'method' => 'linear_trend_extrapolation',
        ];
    }

    /**
     * Churn-risk score per active subscriber: real recency signals
     * (days since last login, days since last payment), weighted into
     * a 0-100 risk score — higher means more likely to churn.
     *
     * @return array<int, array{business_id: string, business_name: string, risk_score: int, reasons: array<int, string>}>
     */
    public function churnRisk(int $limit = 10): array
    {
        $businesses = Business::query()
            ->where('status', Business::STATUS_ACTIVE)
            ->with(['owner', 'subscription'])
            ->get();

        $scored = $businesses->map(function (Business $business) {
            $reasons = [];
            $score = 0;

            $lastLogin = $business->owner?->last_login_at;
            $daysSinceLogin = $lastLogin ? $lastLogin->diffInDays(now()) : 999;
            if ($daysSinceLogin > 30) {
                $score += 40;
                $reasons[] = "No login in {$daysSinceLogin} days";
            }

            $lastPayment = PaymentTransaction::query()
                ->where('business_id', $business->id)
                ->where('status', PaymentTransaction::STATUS_SUCCESSFUL)
                ->latest('paid_at')
                ->first();
            $daysSincePayment = $lastPayment?->paid_at ? $lastPayment->paid_at->diffInDays(now()) : 999;
            if ($daysSincePayment > 45) {
                $score += 40;
                $reasons[] = "No successful payment in {$daysSincePayment} days";
            }

            if ($business->subscription && $business->subscription->isInGracePeriod()) {
                $score += 20;
                $reasons[] = 'Subscription in grace period';
            }

            return [
                'business_id' => $business->id,
                'business_name' => $business->name,
                'risk_score' => min(100, $score),
                'reasons' => $reasons,
            ];
        })
            ->filter(fn ($row) => $row['risk_score'] > 0)
            ->sortByDesc('risk_score')
            ->take($limit)
            ->values();

        return $scored->all();
    }

    /**
     * @return array<int, array{business_id: string, business_name: string, health_score: int}>
     */
    public function businessHealthScores(int $limit = 20): array
    {
        $churnRisk = collect($this->churnRisk(1000))->keyBy('business_id');

        return Business::query()
            ->where('status', Business::STATUS_ACTIVE)
            ->limit($limit)
            ->get()
            ->map(fn (Business $business) => [
                'business_id' => $business->id,
                'business_name' => $business->name,
                'health_score' => 100 - ($churnRisk->get($business->id)['risk_score'] ?? 0),
            ])
            ->sortByDesc('health_score')
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{businesses: int, growth_percent: float}>
     */
    public function growthTrend(): array
    {
        $thisMonth = Business::query()->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count();
        $lastMonth = Business::query()->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])->count();

        $growth = $lastMonth > 0 ? (($thisMonth - $lastMonth) / $lastMonth) * 100 : ($thisMonth > 0 ? 100.0 : 0.0);

        return [
            'this_month' => ['businesses' => $thisMonth, 'growth_percent' => round($growth, 1)],
        ];
    }

    /**
     * @return array<int, array{business_id: string, name: string, transaction_count: int}>
     */
    public function mostActiveBusinesses(int $limit = 10): array
    {
        return PaymentTransaction::query()
            ->selectRaw('business_id, count(*) as transaction_count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('business_id')
            ->orderByDesc('transaction_count')
            ->limit($limit)
            ->with('business:id,name')
            ->get()
            ->map(fn ($row) => ['business_id' => $row->business_id, 'name' => $row->business?->name, 'transaction_count' => (int) $row->transaction_count])
            ->all();
    }

    /**
     * @return array<int, array{business_id: string, name: string, days_inactive: int}>
     */
    public function inactiveBusinesses(int $limit = 10): array
    {
        return Business::query()
            ->where('status', Business::STATUS_ACTIVE)
            ->with('owner')
            ->get()
            ->map(fn (Business $b) => [
                'business_id' => $b->id,
                'name' => $b->name,
                'days_inactive' => $b->owner?->last_login_at ? $b->owner->last_login_at->diffInDays(now()) : 999,
            ])
            ->sortByDesc('days_inactive')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{business_type: ?string, total: float}>
     */
    public function revenueByBusinessType(): array
    {
        return PaymentTransaction::query()
            ->join('businesses', 'businesses.id', '=', 'payment_transactions.business_id')
            ->leftJoin('business_types', 'business_types.id', '=', 'businesses.business_type_id')
            ->where('payment_transactions.status', PaymentTransaction::STATUS_SUCCESSFUL)
            ->selectRaw('business_types.name as business_type, sum(payment_transactions.amount) as total')
            ->groupBy('business_types.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['business_type' => $row->business_type ?? 'Unspecified', 'total' => (float) $row->total])
            ->all();
    }

    /**
     * @return array<int, array{country: ?string, total: float}>
     */
    public function revenueByCountry(): array
    {
        return PaymentTransaction::query()
            ->join('businesses', 'businesses.id', '=', 'payment_transactions.business_id')
            ->where('payment_transactions.status', PaymentTransaction::STATUS_SUCCESSFUL)
            ->selectRaw('businesses.country as country, sum(payment_transactions.amount) as total')
            ->groupBy('businesses.country')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['country' => $row->country ?? 'Unspecified', 'total' => (float) $row->total])
            ->all();
    }

    /**
     * @return array<string, float>
     */
    private function monthlyRevenueSeries(int $months): array
    {
        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $start = Carbon::now()->subMonths($i)->startOfMonth();
            $end = Carbon::now()->subMonths($i)->endOfMonth();

            $series[$start->format('M Y')] = (float) PaymentTransaction::query()
                ->where('status', PaymentTransaction::STATUS_SUCCESSFUL)
                ->whereBetween('paid_at', [$start, $end])
                ->sum('amount');
        }

        return $series;
    }

    /**
     * Simple least-squares linear regression, returns the predicted next
     * value in the series. With fewer than 2 data points, returns the
     * last known value (no trend to extrapolate).
     *
     * @param  array<int, float>  $values
     */
    private function linearTrendNextValue(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return $values[0] ?? 0.0;
        }

        $xSum = $ySum = $xySum = $xxSum = 0;
        foreach ($values as $x => $y) {
            $xSum += $x;
            $ySum += $y;
            $xySum += $x * $y;
            $xxSum += $x * $x;
        }

        $denominator = ($n * $xxSum) - ($xSum * $xSum);
        if ($denominator == 0) {
            return $values[$n - 1];
        }

        $slope = (($n * $xySum) - ($xSum * $ySum)) / $denominator;
        $intercept = ($ySum - ($slope * $xSum)) / $n;

        return $slope * $n + $intercept;
    }
}
