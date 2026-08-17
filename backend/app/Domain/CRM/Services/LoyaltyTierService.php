<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\LoyaltyTier;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleReturn;
use Illuminate\Support\Str;

/**
 * Membership tiers are computed entirely from real lifetime net spend
 * (completed sales minus approved refunds) — there's no manual override
 * and no fabricated "loyalty score". A customer always sits in the
 * highest tier their real spend qualifies for; this runs automatically
 * after every completed sale and approved return (see the Sales event
 * listeners), so upgrades and downgrades both happen without staff
 * intervention.
 */
class LoyaltyTierService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): LoyaltyTier
    {
        $data['slug'] = $this->uniqueSlug($data['business_id'], $data['name']);

        // minimum_spend_minor is derived automatically from minimum_spend
        // by LoyaltyTier's SyncsMoneyMinorColumns trait — no need to set
        // it here, including for callers (like this store request) that
        // only know about the legacy decimal field.
        return LoyaltyTier::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LoyaltyTier $tier, array $data): LoyaltyTier
    {
        if (isset($data['name']) && $data['name'] !== $tier->name) {
            $data['slug'] = $this->uniqueSlug($tier->business_id, $data['name'], $tier->id);
        }

        $tier->update($data);

        return $tier->refresh();
    }

    public function delete(LoyaltyTier $tier): void
    {
        $tier->delete();
    }

    private function uniqueSlug(string $businessId, string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (
            LoyaltyTier::query()
                ->where('business_id', $businessId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->withTrashed()
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    public function recalculateTier(Customer $customer): void
    {
        // Both sides now compare in integer minor units. Sale/SaleReturn
        // were cut over to Money/minor units in the Sales context (ADR
        // 0002, context 5 of 6) — total_amount_minor/refund_amount_minor
        // are reliably populated via SyncsMoneyMinorColumns regardless of
        // whether a row was written by SaleService/SaleReturnService or
        // any other path, so this no longer needs to bridge through the
        // legacy decimal columns.
        $spendMinor = $this->lifetimeSpendMinor($customer);

        $tier = LoyaltyTier::query()
            ->where('business_id', $customer->business_id)
            ->where('minimum_spend_minor', '<=', $spendMinor)
            ->orderByDesc('minimum_spend_minor')
            ->first();

        $tierId = $tier?->id;

        if ($customer->loyalty_tier_id !== $tierId) {
            $customer->update(['loyalty_tier_id' => $tierId]);
        }
    }

    private function lifetimeSpendMinor(Customer $customer): int
    {
        $salesMinor = (int) Sale::query()
            ->where('customer_id', $customer->id)
            ->where('status', Sale::STATUS_COMPLETED)
            ->sum('total_amount_minor');

        $refundsMinor = (int) SaleReturn::query()
            ->where('customer_id', $customer->id)
            ->where('status', SaleReturn::STATUS_APPROVED)
            ->sum('refund_amount_minor');

        return $salesMinor - $refundsMinor;
    }
}
