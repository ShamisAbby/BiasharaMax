<?php

namespace App\Domain\CRM\Http\Resources;

use App\Domain\CRM\Models\MarketingCampaign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MarketingCampaign
 */
class MarketingCampaignResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'segment_filters' => $this->segment_filters,
            'audience_count' => $this->audience_count,
            'sent_count' => $this->sent_count,
            'failed_count' => $this->failed_count,
            'sent_at' => $this->sent_at,
            'created_at' => $this->created_at,
        ];
    }
}
