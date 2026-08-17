<?php

namespace App\Domain\Notifications\Drivers;

use Illuminate\Support\Facades\Http;

/**
 * Real Firebase Cloud Messaging HTTP v1 API.
 */
class FcmPushDriver extends AbstractChannelDriver
{
    public function send(string $recipient, ?string $subject, string $body): array
    {
        $this->ensureConfigured();

        $projectId = $this->credential('project_id');

        $response = Http::withToken($this->credential('access_token'))
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $recipient,
                    'notification' => ['title' => $subject ?? 'BiasharaMax', 'body' => $body],
                ],
            ]);

        $responseBody = $response->json() ?? [];
        $successful = $response->successful() && isset($responseBody['name']);

        return [
            'successful' => $successful,
            'provider_message_id' => $responseBody['name'] ?? null,
            'error' => $successful ? null : ($responseBody['error']['message'] ?? 'Push send failed'),
        ];
    }
}
