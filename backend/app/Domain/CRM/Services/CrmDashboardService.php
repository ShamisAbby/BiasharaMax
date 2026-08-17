<?php

namespace App\Domain\CRM\Services;

use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Domain\Shared\Support\DateFormatSql;
use Illuminate\Support\Carbon;

class CrmDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(string $businessId): array
    {
        $monthStart = Carbon::now()->startOfMonth();

        return [
            'total_customers' => Customer::query()->where('business_id', $businessId)->count(),
            'active_customers' => Customer::query()->where('business_id', $businessId)->where('is_active', true)->count(),
            'new_customers_this_month' => Customer::query()->where('business_id', $businessId)->where('created_at', '>=', $monthStart)->count(),
            'vip_customers' => Customer::query()->where('business_id', $businessId)->whereHas('group', fn ($query) => $query->where('is_vip', true))->count(),
            'outstanding_debts' => (float) Customer::query()->where('business_id', $businessId)->sum('current_balance'),
            'total_loyalty_points' => (int) Customer::query()->where('business_id', $businessId)->sum('loyalty_points'),
        ];
    }

    /**
     * @return array<int, array{customer_id: string, name: string, lifetime_value: float}>
     */
    public function topCustomers(string $businessId, int $limit = 5): array
    {
        return Sale::query()
            ->where('business_id', $businessId)
            ->where('status', Sale::STATUS_COMPLETED)
            ->whereNotNull('customer_id')
            ->selectRaw('customer_id, sum(total_amount) as lifetime_value')
            ->groupBy('customer_id')
            ->orderByDesc('lifetime_value')
            ->limit($limit)
            ->with('customer:id,name')
            ->get()
            ->filter(fn ($row) => $row->customer !== null)
            ->map(fn ($row) => [
                'customer_id' => $row->customer_id,
                'name' => $row->customer->name,
                'lifetime_value' => (float) $row->lifetime_value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    public function newCustomersTrend(string $businessId): array
    {
        $since = Carbon::now()->subDays(13)->startOfDay();

        $countsByDay = Customer::query()
            ->where('business_id', $businessId)
            ->where('created_at', '>=', $since)
            ->selectRaw(DateFormatSql::daily('created_at')." as day, count(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        $trend = [];

        for ($date = $since->copy(); $date->lte(Carbon::now()); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $trend[] = [
                'label' => $date->format('M j'),
                'count' => (int) ($countsByDay[$key] ?? 0),
            ];
        }

        return $trend;
    }
}
