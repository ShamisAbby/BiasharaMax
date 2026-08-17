<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Business\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Business
 */
class BusinessSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'business_type' => $this->business_type,
            'status' => $this->status,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ] : null),
            'subscription' => $this->whenLoaded('subscription', fn () => $this->subscription ? [
                'id' => $this->subscription->id,
                'status' => $this->subscription->status,
                'subscription_plan_id' => $this->subscription->subscription_plan_id,
                'plan_name' => $this->subscription->plan?->name,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
