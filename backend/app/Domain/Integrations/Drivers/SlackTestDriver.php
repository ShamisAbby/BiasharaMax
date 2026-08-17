<?php

namespace App\Domain\Integrations\Drivers;

use Illuminate\Support\Facades\Http;

/**
 * Real Slack Web API — auth.test confirms the bot token is valid.
 */
class SlackTestDriver extends AbstractTestDriver
{
    public function test(): array
    {
        $this->ensureConfigured();

        $response = Http::withToken($this->credential('bot_token'))->post('https://slack.com/api/auth.test');

        $successful = $response->successful() && $response->json('ok') === true;

        return [
            'successful' => $successful,
            'status_code' => $response->status(),
            'response' => ['team' => $response->json('team'), 'user' => $response->json('user')],
            'error' => $successful ? null : ($response->json('error') ?? 'Slack connection failed'),
        ];
    }
}
