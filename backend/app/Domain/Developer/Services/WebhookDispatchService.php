<?php

namespace App\Domain\Developer\Services;

use App\Domain\Developer\Models\Webhook;
use App\Domain\Developer\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;

class WebhookDispatchService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(Webhook $webhook, string $event, array $payload): WebhookDelivery
    {
        $signature = hash_hmac('sha256', json_encode($payload), $webhook->secret ?? '');

        try {
            $response = Http::withHeaders(['X-BiasharaMax-Signature' => $signature])
                ->timeout(10)
                ->post($webhook->url, $payload);

            return WebhookDelivery::query()->create([
                'webhook_id' => $webhook->id,
                'event' => $event,
                'payload' => $payload,
                'response_status' => $response->status(),
                'response_body' => substr($response->body(), 0, 2000),
                'is_successful' => $response->successful(),
                'delivered_at' => now(),
            ]);
        } catch (\Throwable $e) {
            return WebhookDelivery::query()->create([
                'webhook_id' => $webhook->id,
                'event' => $event,
                'payload' => $payload,
                'response_status' => null,
                'response_body' => $e->getMessage(),
                'is_successful' => false,
            ]);
        }
    }

    public function retry(WebhookDelivery $delivery): WebhookDelivery
    {
        $result = $this->dispatch($delivery->webhook, $delivery->event, $delivery->payload);
        $result->update(['attempt' => $delivery->attempt + 1]);

        return $result;
    }

    /**
     * Fan-out to every active webhook subscribed to this event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fireEvent(string $event, array $payload, ?string $businessId = null): void
    {
        Webhook::query()
            ->where('is_active', true)
            ->when($businessId, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('business_id')->orWhere('business_id', $businessId)))
            ->get()
            ->filter(fn (Webhook $webhook) => $webhook->isSubscribedTo($event))
            ->each(fn (Webhook $webhook) => $this->dispatch($webhook, $event, $payload));
    }
}
