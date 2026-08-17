<?php

namespace App\Domain\Integrations\Drivers;

use Illuminate\Support\Facades\Http;

/**
 * Real Anthropic Claude API.
 */
class ClaudeTestDriver extends AbstractTestDriver
{
    public function test(): array
    {
        $this->ensureConfigured();

        $response = $this->ping();

        return [
            'successful' => $response->successful() || $response->status() === 400,
            'status_code' => $response->status(),
            'response' => $response->json() ?? [],
            'error' => $response->successful() ? null : ($response->json('error.message') ?? 'Claude connection failed'),
        ];
    }

    public function complete(string $prompt, string $model = 'claude-3-5-sonnet-20241022'): string
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->credential('api_key'),
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $model,
            'max_tokens' => 512,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        return $response->json('content.0.text') ?? '';
    }

    private function ping()
    {
        return Http::withHeaders([
            'x-api-key' => $this->credential('api_key'),
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 1,
            'messages' => [['role' => 'user', 'content' => 'ping']],
        ]);
    }
}
