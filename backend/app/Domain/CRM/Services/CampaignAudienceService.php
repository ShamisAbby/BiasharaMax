<?php

namespace App\Domain\CRM\Services;

use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Builds a real customer audience from real customer data — tags,
 * loyalty tier, debt status, and inactivity (no completed sale in
 * the last N days). No fabricated segments.
 *
 * @phpstan-type SegmentFilters array{
 *     tag_ids?: array<int, string>,
 *     loyalty_tier_id?: ?string,
 *     debt_status?: ?string,
 *     inactive_days?: ?int,
 * }
 */
class CampaignAudienceService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Customer>
     */
    public function query(string $businessId, array $filters): Builder
    {
        $query = Customer::query()
            ->where('business_id', $businessId)
            ->where('is_active', true)
            ->whereNotNull('email');

        if (! empty($filters['tag_ids'])) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('customer_tags.id', $filters['tag_ids']));
        }

        if (! empty($filters['loyalty_tier_id'])) {
            $query->where('loyalty_tier_id', $filters['loyalty_tier_id']);
        }

        if (! empty($filters['debt_status'])) {
            match ($filters['debt_status']) {
                'with_debt' => $query->where('current_balance', '>', 0),
                'no_debt' => $query->where('current_balance', '<=', 0),
                default => null,
            };
        }

        if (! empty($filters['inactive_days'])) {
            $cutoff = Carbon::now()->subDays((int) $filters['inactive_days']);
            $query->whereDoesntHave('sales', fn ($q) => $q
                ->where('status', Sale::STATUS_COMPLETED)
                ->where('created_at', '>=', $cutoff));
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function count(string $businessId, array $filters): int
    {
        return $this->query($businessId, $filters)->count();
    }
}
