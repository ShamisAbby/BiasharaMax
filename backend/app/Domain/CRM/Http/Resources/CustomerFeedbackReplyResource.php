<?php

namespace App\Domain\CRM\Http\Resources;

use App\Domain\CRM\Models\CustomerFeedbackReply;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerFeedbackReply
 */
class CustomerFeedbackReplyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->whenLoaded('author', fn () => $this->author ? [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ] : null),
            'created_at' => $this->created_at,
        ];
    }
}
