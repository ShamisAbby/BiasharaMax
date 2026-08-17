<?php

namespace App\Domain\Notifications\Drivers;

use Illuminate\Support\Facades\Http;

/**
 * Real WhatsApp Business Cloud API (Meta Graph API).
 */
class WhatsAppDriver extends AbstractChannelDriver
{
    public function send(string $recipient, ?string $subject, string $body): array
    {
        $this->ensureConfigured();

        $phoneNumberId = $this->credential('phone_number_id');

        $response = Http::withToken($this->credential('access_token'))
            ->post("https://graph.facebook.com/v19.0/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'text',
                'text' => ['body' => $body],
            ]);

        $responseBody = $response->json() ?? [];
        $successful = $response->successful() && ! empty($responseBody['messages']);

        return [
            'successful' => $successful,
            'provider_message_id' => $responseBody['messages'][0]['id'] ?? null,
            'error' => $successful ? null : ($responseBody['error']['message'] ?? 'WhatsApp send failed'),
        ];
    }
}
