<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Notifications\Models\NotificationTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationTemplate
 */
class NotificationTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'channel' => $this->channel,
            'category' => $this->category,
            'subject' => $this->subject,
            'body' => $this->body,
            'is_system' => $this->is_system,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
