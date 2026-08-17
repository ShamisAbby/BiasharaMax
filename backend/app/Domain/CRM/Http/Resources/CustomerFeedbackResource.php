<?php

namespace App\Domain\CRM\Http\Resources;

use App\Domain\CRM\Models\CustomerFeedback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerFeedback
 */
class CustomerFeedbackResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'rating' => $this->rating,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'resolved_at' => $this->resolved_at,
            'created_at' => $this->created_at,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ] : null),
            'replies' => CustomerFeedbackReplyResource::collection($this->whenLoaded('replies')),
        ];
    }
}
