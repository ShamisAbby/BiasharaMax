<?php

namespace App\Domain\CRM\Http\Resources;

use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerCrmProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'customer_type' => $this->customer_type,
            'current_balance' => $this->current_balance,
            'credit_limit' => $this->credit_limit,
            'loyalty_points' => $this->loyalty_points,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'group' => $this->whenLoaded('group', fn () => $this->group ? [
                'id' => $this->group->id,
                'name' => $this->group->name,
                'is_vip' => $this->group->is_vip,
            ] : null),
            'loyalty_tier' => $this->whenLoaded('loyaltyTier', fn () => $this->loyaltyTier ? [
                'id' => $this->loyaltyTier->id,
                'name' => $this->loyaltyTier->name,
            ] : null),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])),
            'lifetime_value' => (float) $this->sales()->where('status', Sale::STATUS_COMPLETED)->sum('total_amount')
                - (float) SaleReturn::query()->where('customer_id', $this->id)->where('status', SaleReturn::STATUS_APPROVED)->sum('refund_amount'),
            'sales_count' => $this->sales()->where('status', Sale::STATUS_COMPLETED)->count(),
        ];
    }
}
