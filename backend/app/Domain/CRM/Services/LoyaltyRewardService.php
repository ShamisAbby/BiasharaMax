<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Exceptions\LoyaltyPointsException;
use App\Domain\CRM\Exceptions\LoyaltyRewardException;
use App\Domain\CRM\Models\LoyaltyReward;
use App\Domain\CRM\Models\LoyaltyRewardRedemption;
use App\Domain\Sales\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoyaltyRewardService
{
    public function __construct(
        private readonly CustomerLoyaltyService $loyaltyService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): LoyaltyReward
    {
        $data['slug'] = $this->uniqueSlug($data['business_id'], $data['name']);

        return LoyaltyReward::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(LoyaltyReward $reward, array $data): LoyaltyReward
    {
        if (isset($data['name']) && $data['name'] !== $reward->name) {
            $data['slug'] = $this->uniqueSlug($reward->business_id, $data['name'], $reward->id);
        }

        $reward->update($data);

        return $reward->refresh();
    }

    public function delete(LoyaltyReward $reward): void
    {
        $reward->delete();
    }

    /**
     * @throws LoyaltyPointsException|LoyaltyRewardException
     */
    public function redeem(Customer $customer, LoyaltyReward $reward, ?string $createdBy): LoyaltyRewardRedemption
    {
        if (! $reward->is_active) {
            throw LoyaltyRewardException::inactive($reward->name);
        }

        if (! $reward->isInStock()) {
            throw LoyaltyRewardException::outOfStock($reward->name);
        }

        return DB::transaction(function () use ($customer, $reward, $createdBy) {
            $this->loyaltyService->redeem($customer, $reward->points_cost, "Redeemed: {$reward->name}", $createdBy);

            if ($reward->stock_quantity !== null) {
                $reward->decrement('stock_quantity');
            }

            return LoyaltyRewardRedemption::create([
                'business_id' => $customer->business_id,
                'customer_id' => $customer->id,
                'loyalty_reward_id' => $reward->id,
                'points_spent' => $reward->points_cost,
                'redeemed_at' => now(),
                'created_by' => $createdBy,
            ]);
        });
    }

    public function fulfil(LoyaltyRewardRedemption $redemption): LoyaltyRewardRedemption
    {
        $redemption->update([
            'status' => LoyaltyRewardRedemption::STATUS_FULFILLED,
            'fulfilled_at' => now(),
        ]);

        return $redemption->refresh();
    }

    private function uniqueSlug(string $businessId, string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (
            LoyaltyReward::query()
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
}
