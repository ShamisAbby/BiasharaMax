<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\CustomerLoyaltyTransaction;
use App\Domain\CRM\Models\LoyaltyRewardRedemption;
use App\Domain\Sales\Models\Customer;
use Illuminate\Support\Carbon;

/**
 * Every figure here is computed live from real customers/loyalty
 * transaction/redemption rows.
 */
class LoyaltyDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(string $businessId): array
    {
        $monthStart = Carbon::now()->startOfMonth();

        $members = Customer::query()
            ->where('business_id', $businessId)
            ->where(fn ($query) => $query->where('loyalty_points', '>', 0)->orWhereNotNull('loyalty_tier_id'));

        return [
            'total_members' => (clone $members)->count(),
            'active_members' => Customer::query()->where('business_id', $businessId)->where('is_active', true)->where('loyalty_points', '>', 0)->count(),
            'vip_customers' => Customer::query()->where('business_id', $businessId)->whereHas('group', fn ($q) => $q->where('is_vip', true))->count(),
            'points_issued' => (int) CustomerLoyaltyTransaction::query()
                ->where('business_id', $businessId)->where('type', CustomerLoyaltyTransaction::TYPE_EARN)
                ->where('created_at', '>=', $monthStart)->sum('points'),
            'points_redeemed' => (int) abs(CustomerLoyaltyTransaction::query()
                ->where('business_id', $businessId)->where('type', CustomerLoyaltyTransaction::TYPE_REDEEM)
                ->where('created_at', '>=', $monthStart)->sum('points')),
            'reward_redemptions_count' => LoyaltyRewardRedemption::query()
                ->where('business_id', $businessId)->where('created_at', '>=', $monthStart)->count(),
            'points_outstanding' => (int) Customer::query()->where('business_id', $businessId)->sum('loyalty_points'),
        ];
    }

    /**
     * @return array<int, array{customer_id: string, name: string, loyalty_points: int, tier: string|null}>
     */
    public function topLoyalCustomers(string $businessId, int $limit = 5): array
    {
        return Customer::query()
            ->where('business_id', $businessId)
            ->where('loyalty_points', '>', 0)
            ->with('loyaltyTier:id,name')
            ->orderByDesc('loyalty_points')
            ->limit($limit)
            ->get()
            ->map(fn (Customer $customer) => [
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'loyalty_points' => $customer->loyalty_points,
                'tier' => $customer->loyaltyTier?->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{tier: string, customers_count: int}>
     */
    public function tierDistribution(string $businessId): array
    {
        return Customer::query()
            ->where('business_id', $businessId)
            ->whereNotNull('loyalty_tier_id')
            ->with('loyaltyTier:id,name')
            ->get()
            ->groupBy(fn (Customer $customer) => $customer->loyaltyTier?->name ?? 'Unknown')
            ->map(fn ($group, $tier) => ['tier' => $tier, 'customers_count' => $group->count()])
            ->values()
            ->all();
    }
}
