<?php

namespace App\Domain\Website\Http\Resources;

use App\Domain\Website\Models\ProductEnquiry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductEnquiry
 */
class ProductEnquiryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'message' => $this->message,
            'status' => $this->status,
            'reply' => $this->reply,
            'responded_at' => $this->responded_at,
            'created_at' => $this->created_at,
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
            ] : null),
        ];
    }
}
