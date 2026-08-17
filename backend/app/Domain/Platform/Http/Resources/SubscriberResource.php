<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Subscription\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'billing_cycle' => $this->billing_cycle,
            'trial_ends_at' => $this->trial_ends_at,
            'current_period_end' => $this->current_period_end,
            'grace_period_ends_at' => $this->grace_period_ends_at,
            'is_in_grace_period' => $this->isInGracePeriod(),
            'is_custom_pricing' => $this->custom_price !== null,
            'effective_price' => $this->effectivePrice(),
            'auto_renew' => $this->auto_renew,
            'business' => $this->whenLoaded('business', fn () => $this->business ? [
                'id' => $this->business->id,
                'name' => $this->business->name,
                'owner_name' => $this->business->owner?->name,
                'owner_email' => $this->business->owner?->email,
            ] : null),
            'plan' => $this->whenLoaded('plan', fn () => $this->plan ? [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
