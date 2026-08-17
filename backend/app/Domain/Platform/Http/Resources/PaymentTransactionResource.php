<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Finance\Models\PaymentTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentTransaction
 */
class PaymentTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'reference_number' => $this->reference_number,
            'invoice_number' => $this->invoice_number,
            'external_transaction_id' => $this->external_transaction_id,
            'business' => $this->whenLoaded('business', fn () => [
                'id' => $this->business->id,
                'name' => $this->business->name,
                'owner_name' => $this->business->owner?->name,
                'owner_email' => $this->business->owner?->email,
            ]),
            'gateway' => $this->whenLoaded('gateway', fn () => $this->gateway ? [
                'id' => $this->gateway->id,
                'name' => $this->gateway->name,
            ] : null),
            'payable_type' => $this->payable_type ? class_basename($this->payable_type) : null,
            'payable_id' => $this->payable_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'fee_amount' => $this->fee_amount,
            'commission_amount' => $this->commission_amount,
            'refunded_amount' => $this->refunded_amount,
            'net_amount' => $this->netAmount(),
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'receipt_path' => $this->receipt_path,
            'notes' => $this->notes,
            'is_refundable' => $this->isRefundable(),
            'paid_at' => $this->paid_at,
            'failed_at' => $this->failed_at,
            'refunded_at' => $this->refunded_at,
            'timeline' => $this->whenLoaded('timeline', fn () => $this->timeline->map(fn ($entry) => [
                'id' => $entry->id,
                'event' => $entry->event,
                'from_status' => $entry->from_status,
                'to_status' => $entry->to_status,
                'message' => $entry->message,
                'created_at' => $entry->created_at,
            ])),
            'refunds' => $this->whenLoaded('refunds', fn () => PaymentTransactionResource::collection($this->refunds)),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
