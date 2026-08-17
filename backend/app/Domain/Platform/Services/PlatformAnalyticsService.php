<?php

namespace App\Domain\Platform\Services;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\Inventory\Models\Product;
use App\Domain\Shared\Support\DateFormatSql;
use App\Domain\Subscription\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

/**
 * Every figure here is read straight from BiasharaMax's own tables — there
 * is no Sales/Payments/Licensing module yet, so "Total Sales" and similar
 * unbuilt-module metrics from the original spec are deliberately omitted
 * rather than faked. See the SuperAdmin dashboard "Coming soon" nav items
 * for what's intentionally not here yet.
 */
class PlatformAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $totalBusinesses = Business::query()->count();
        $activeBusinesses = Business::query()->where('status', Business::STATUS_ACTIVE)->count();
        $trialBusinesses = Business::query()->where('status', Business::STATUS_TRIAL)->count();
        $inactiveBusinesses = Business::query()
            ->whereIn('status', [Business::STATUS_SUSPENDED, Business::STATUS_EXPIRED])
            ->count();

        $activeSubscriptions = Subscription::query()
            ->whereIn('status', [Subscription::STATUS_TRIALING, Subscription::STATUS_ACTIVE])
            ->count();
        $expiredSubscriptions = Subscription::query()
            ->whereIn('status', [Subscription::STATUS_EXPIRED, Subscription::STATUS_CANCELED])
            ->count();

        return [
            'total_businesses' => $totalBusinesses,
            'active_businesses' => $activeBusinesses,
            'inactive_businesses' => $inactiveBusinesses,
            'trial_accounts' => $trialBusinesses,
            'total_users' => User::query()->count(),
            'total_superadmins' => PlatformUser::query()->count(),
            'active_subscriptions' => $activeSubscriptions,
            'expired_subscriptions' => $expiredSubscriptions,
            'total_products' => Product::query()->count(),
            'system_health' => $this->systemHealth(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function systemHealth(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue_connection' => config('queue.default'),
        ];
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            Redis::connection()->ping();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Monthly business signups for the last 12 months.
     *
     * @return array<int, array{label: string, count: int}>
     */
    public function businessRegistrationTrend(): array
    {
        return $this->monthlyCount(Business::query());
    }

    /**
     * Monthly new subscriptions for the last 12 months — a proxy for
     * "Subscription Growth" since there's no separate growth ledger.
     *
     * @return array<int, array{label: string, count: int}>
     */
    public function subscriptionGrowth(): array
    {
        return $this->monthlyCount(Subscription::query());
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function monthlyCount($query): array
    {
        $since = Carbon::now()->startOfMonth()->subMonths(11);

        $rows = $query
            ->where('created_at', '>=', $since)
            ->selectRaw(DateFormatSql::monthly('created_at')." as month, count(*) as count")
            ->groupBy('month')
            ->pluck('count', 'month');

        $trend = [];

        for ($date = $since->copy(); $date->lte(Carbon::now()); $date->addMonth()) {
            $key = $date->format('Y-m');

            $trend[] = [
                'label' => $date->format('M Y'),
                'count' => (int) ($rows[$key] ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    public function topBusinessTypes(): array
    {
        return Business::query()
            ->selectRaw('business_type, count(*) as count')
            ->groupBy('business_type')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['label' => ucfirst($row->business_type), 'count' => (int) $row->count])
            ->all();
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    public function countryDistribution(): array
    {
        return Business::query()
            ->selectRaw('country, count(*) as count')
            ->groupBy('country')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['label' => $row->country, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    public function subscriptionStatusBreakdown(): array
    {
        return Subscription::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->get()
            ->map(fn ($row) => ['label' => ucfirst($row->status), 'count' => (int) $row->count])
            ->all();
    }

    /**
     * Real Horizon-backed queue snapshot rather than fabricated server
     * metrics — CPU/RAM/disk for the underlying host aren't something
     * this application can honestly report without a metrics agent.
     *
     * @return array<string, mixed>
     */
    public function queueSnapshot(): array
    {
        try {
            $pending = Queue::size();
        } catch (\Throwable) {
            $pending = null;
        }

        $failedJobs = \Illuminate\Support\Facades\Schema::hasTable('failed_jobs')
            ? DB::table('failed_jobs')->count()
            : 0;

        return [
            'pending_jobs' => $pending,
            'failed_jobs' => $failedJobs,
            'horizon_available' => class_exists(\Laravel\Horizon\Horizon::class),
        ];
    }
}
