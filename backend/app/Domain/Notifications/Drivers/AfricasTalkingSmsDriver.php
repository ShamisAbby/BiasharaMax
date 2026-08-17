<?php

namespace App\Domain\Notifications\Drivers;

use Illuminate\Support\Facades\Http;

/**
 * Real Africa's Talking SMS API (https://api.africastalking.com/version1/messaging).
 */
class AfricasTalkingSmsDriver extends AbstractChannelDriver
{
    public function send(string $recipient, ?string $subject, string $body): array
    {
        $this->ensureConfigured();

        $response = Http::asForm()
            ->withHeaders(['apiKey' => $this->credential('api_key'), 'Accept' => 'application/json'])
            ->post('https://api.africastalking.com/version1/messaging', [
                'username' => $this->credential('username'),
                'to' => $recipient,
                'message' => $body,
                'from' => $this->channel->sender_id,
            ]);

        $body = $response->json() ?? [];
        $recipients = $body['SMSMessageData']['Recipients'] ?? [];
        $successful = $response->successful() && ! empty($recipients) && ($recipients[0]['status'] ?? null) === 'Success';

        return [
            'successful' => $successful,
            'provider_message_id' => $recipients[0]['messageId'] ?? null,
            'error' => $successful ? null : ($recipients[0]['status'] ?? 'SMS send failed'),
        ];
    }
}
