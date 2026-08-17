<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Notifications\Models\NotificationCampaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationCampaign
 */
class NotificationCampaignResource extends JsonResource
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
            'subject' => $this->subject,
            'body' => $this->body,
            'audience_type' => $this->audience_type,
            'audience_filter' => $this->audience_filter,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'sent_at' => $this->sent_at,
            'total_recipients' => $this->total_recipients,
            'sent_count' => $this->sent_count,
            'failed_count' => $this->failed_count,
            'deliveries' => $this->whenLoaded('deliveries', fn () => $this->deliveries->map(fn ($d) => [
                'id' => $d->id,
                'notifiable_type' => class_basename($d->notifiable_type),
                'recipient' => $d->recipient,
                'status' => $d->status,
                'error_message' => $d->error_message,
                'sent_at' => $d->sent_at,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
