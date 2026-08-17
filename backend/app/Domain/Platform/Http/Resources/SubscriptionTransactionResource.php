<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Subscription\Models\SubscriptionTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionTransaction
 */
class SubscriptionTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'billing_cycle' => $this->billing_cycle,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'business' => $this->whenLoaded('business', fn () => $this->business ? [
                'id' => $this->business->id,
                'name' => $this->business->name,
            ] : null),
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy?->name),
            'paid_at' => $this->paid_at,
        ];
    }
}
