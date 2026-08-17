<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\CampaignRecipient;
use App\Domain\CRM\Models\MarketingCampaign;
use App\Domain\CRM\Notifications\MarketingCampaignNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;

class MarketingCampaignService
{
    public function __construct(
        private readonly CampaignAudienceService $audienceService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MarketingCampaign
    {
        $audienceCount = $this->audienceService->count($data['business_id'], $data['segment_filters'] ?? []);

        return MarketingCampaign::create([
            ...$data,
            'audience_count' => $audienceCount,
        ]);
    }

    /**
     * Builds the recipient list from the campaign's real audience filters,
     * then sends one real email per recipient via Laravel Mail (the only
     * genuinely configured outbound channel — no SMS/WhatsApp gateway
     * exists in this codebase).
     */
    public function send(MarketingCampaign $campaign): void
    {
        $customers = $this->audienceService->query($campaign->business_id, $campaign->segment_filters ?? [])->get();

        DB::transaction(function () use ($campaign, $customers) {
            $campaign->update(['status' => MarketingCampaign::STATUS_SENDING, 'audience_count' => $customers->count()]);

            foreach ($customers as $customer) {
                CampaignRecipient::create([
                    'marketing_campaign_id' => $campaign->id,
                    'customer_id' => $customer->id,
                    'email' => $customer->email,
                ]);
            }
        });

        $sent = 0;
        $failed = 0;

        foreach ($campaign->recipients()->where('status', CampaignRecipient::STATUS_PENDING)->get() as $recipient) {
            try {
                Notification::route('mail', $recipient->email)
                    ->notify(new MarketingCampaignNotification($campaign->subject, $campaign->body));

                $recipient->update(['status' => CampaignRecipient::STATUS_SENT, 'sent_at' => Carbon::now()]);
                $sent++;
            } catch (\Throwable $e) {
                $recipient->update(['status' => CampaignRecipient::STATUS_FAILED, 'error_message' => $e->getMessage()]);
                $failed++;
            }
        }

        $campaign->update([
            'status' => MarketingCampaign::STATUS_SENT,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'sent_at' => Carbon::now(),
        ]);
    }
}
