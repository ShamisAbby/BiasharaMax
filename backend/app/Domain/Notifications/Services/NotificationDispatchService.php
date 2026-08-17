<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\Notifications\Models\NotificationCampaign;
use App\Domain\Notifications\Models\NotificationChannel;
use App\Domain\Notifications\Models\NotificationDelivery;
use Illuminate\Support\Collection;

class NotificationDispatchService
{
    public function __construct(
        private readonly ChannelDriverResolver $resolver,
    ) {}

    /**
     * Resolves which tenant Users a campaign targets, based on its
     * audience type/filter. Always returns business owners — the people
     * who actually receive platform-originated notifications today.
     *
     * @return Collection<int, User>
     */
    public function resolveAudience(NotificationCampaign $campaign): Collection
    {
        $query = Business::query()->with('owner')->whereHas('owner');

        match ($campaign->audience_type) {
            NotificationCampaign::AUDIENCE_BUSINESS_TYPE => $query->where(
                'business_type_id',
                $campaign->audience_filter['business_type_id'] ?? null,
            ),
            NotificationCampaign::AUDIENCE_SUBSCRIPTION_PLAN => $query->whereHas(
                'subscription',
                fn ($q) => $q->where('subscription_plan_id', $campaign->audience_filter['subscription_plan_id'] ?? null),
            ),
            NotificationCampaign::AUDIENCE_SPECIFIC_BUSINESSES => $query->whereIn(
                'id',
                $campaign->audience_filter['business_ids'] ?? [],
            ),
            default => null,
        };

        return $query->get()->pluck('owner')->filter()->values();
    }

    /**
     * Why this campaign cannot be sent, or null if it can.
     *
     * Checked before anything is written. Sending into a missing channel
     * used to produce one failed delivery per recipient and a bare
     * "failed" badge, which tells an administrator that something broke
     * but not that the fix is a two-minute configuration change. On a
     * large audience it also wrote thousands of identical failure rows
     * for a condition knowable in one query.
     */
    public function blockedReason(NotificationCampaign $campaign): ?string
    {
        $channel = $this->enabledChannelFor($campaign->channel);

        if (! $channel) {
            return "No enabled {$campaign->channel} channel is configured. Add one under Channels and enable it, then send this campaign.";
        }

        if ($this->resolveAudience($campaign)->isEmpty()) {
            return 'This campaign has no recipients — no business matches its audience, or none has an owner.';
        }

        return null;
    }

    public function enabledChannelFor(string $channel): ?NotificationChannel
    {
        return NotificationChannel::query()
            ->where('channel', $channel)
            ->where('is_enabled', true)
            ->first();
    }

    public function sendCampaign(NotificationCampaign $campaign): NotificationCampaign
    {
        $recipients = $this->resolveAudience($campaign);
        $channel = $this->enabledChannelFor($campaign->channel);

        $campaign->update(['status' => NotificationCampaign::STATUS_SENDING, 'total_recipients' => $recipients->count()]);

        $sent = 0;
        $failed = 0;

        foreach ($recipients as $user) {
            $delivery = $this->dispatchToUser($campaign, $user, $channel);
            $delivery->status === NotificationDelivery::STATUS_SENT ? $sent++ : $failed++;
        }

        $campaign->update([
            'status' => $failed > 0 && $sent === 0 ? NotificationCampaign::STATUS_FAILED : NotificationCampaign::STATUS_SENT,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'sent_at' => now(),
        ]);

        return $campaign->refresh();
    }

    public function retry(NotificationDelivery $delivery): NotificationDelivery
    {
        $channel = $this->enabledChannelFor($delivery->channel);
        $campaign = $delivery->campaign;

        $result = $channel ? $this->attemptSend($channel, $delivery->recipient, $campaign?->subject, $campaign?->body ?? '') : ['successful' => false, 'provider_message_id' => null, 'error' => 'No enabled channel for retry.'];

        return NotificationDelivery::query()->create([
            'notification_campaign_id' => $delivery->notification_campaign_id,
            'notifiable_id' => $delivery->notifiable_id,
            'notifiable_type' => $delivery->notifiable_type,
            'channel' => $delivery->channel,
            'recipient' => $delivery->recipient,
            'status' => $result['successful'] ? NotificationDelivery::STATUS_SENT : NotificationDelivery::STATUS_FAILED,
            'provider_message_id' => $result['provider_message_id'],
            'error_message' => $result['error'],
            'retry_of_delivery_id' => $delivery->id,
            'sent_at' => $result['successful'] ? now() : null,
        ]);
    }

    private function dispatchToUser(NotificationCampaign $campaign, User $user, ?NotificationChannel $channel): NotificationDelivery
    {
        $recipient = match ($campaign->channel) {
            NotificationChannel::CHANNEL_EMAIL => $user->email,
            NotificationChannel::CHANNEL_SMS, NotificationChannel::CHANNEL_WHATSAPP => $user->phone,
            default => $user->id,
        };

        if (! $recipient) {
            return NotificationDelivery::query()->create([
                'notification_campaign_id' => $campaign->id,
                'notifiable_id' => $user->id,
                'notifiable_type' => User::class,
                'channel' => $campaign->channel,
                'recipient' => '',
                'status' => NotificationDelivery::STATUS_FAILED,
                'error_message' => 'Recipient has no contact value for this channel.',
            ]);
        }

        $result = $channel
            ? $this->attemptSend($channel, $recipient, $campaign->subject, $campaign->body)
            : ['successful' => false, 'provider_message_id' => null, 'error' => 'No enabled channel configured.'];

        return NotificationDelivery::query()->create([
            'notification_campaign_id' => $campaign->id,
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'channel' => $campaign->channel,
            'recipient' => $recipient,
            'status' => $result['successful'] ? NotificationDelivery::STATUS_SENT : NotificationDelivery::STATUS_FAILED,
            'provider_message_id' => $result['provider_message_id'],
            'error_message' => $result['error'],
            'sent_at' => $result['successful'] ? now() : null,
        ]);
    }

    /**
     * @return array{successful: bool, provider_message_id: ?string, error: ?string}
     */
    private function attemptSend(NotificationChannel $channel, string $recipient, ?string $subject, string $body): array
    {
        try {
            return $this->resolver->resolve($channel)->send($recipient, $subject, $body);
        } catch (\Throwable $e) {
            return ['successful' => false, 'provider_message_id' => null, 'error' => $e->getMessage()];
        }
    }
}
