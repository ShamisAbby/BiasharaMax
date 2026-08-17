<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Notifications\Models\NotificationChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationChannel
 */
class NotificationChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'channel' => $this->channel,
            'provider' => $this->provider,
            'is_enabled' => $this->is_enabled,
            'is_configured' => $this->isConfigured(),
            // What to do about it, when it is not — see the model.
            'configuration_hint' => $this->configurationHint(),
            'is_default' => $this->is_default,
            'credential_keys' => array_keys($this->credentials ?? []),
            'sender_id' => $this->sender_id,
            'webhook_url' => $this->webhook_url,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
        ];
    }
}
