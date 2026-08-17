<?php

namespace App\Domain\Notifications\Drivers;

use Illuminate\Support\Facades\Http;

/**
 * For any custom/regional SMS or push provider without a hardcoded
 * driver — posts to a configurable endpoint, same fallback pattern as
 * the Finance module's GenericHttpGatewayDriver.
 */
class GenericHttpChannelDriver extends AbstractChannelDriver
{
    public function send(string $recipient, ?string $subject, string $body): array
    {
        $this->ensureConfigured();

        $response = Http::withToken($this->credential('api_key'))
            ->post((string) $this->credential('send_endpoint'), [
                'to' => $recipient,
                'subject' => $subject,
                'message' => $body,
            ]);

        $responseBody = $response->json() ?? [];
        $successful = $response->successful() && (bool) ($responseBody['successful'] ?? $responseBody['success'] ?? false);

        return [
            'successful' => $successful,
            'provider_message_id' => $responseBody['message_id'] ?? null,
            'error' => $successful ? null : ($responseBody['error'] ?? 'Send failed'),
        ];
    }
}
