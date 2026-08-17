<?php

namespace App\Domain\Integrations\Drivers;

use Illuminate\Support\Facades\Http;

/**
 * Real OpenAI API — lists models to confirm the API key is valid.
 */
class OpenAiTestDriver extends AbstractTestDriver
{
    public function test(): array
    {
        $this->ensureConfigured();

        $response = Http::withToken($this->credential('api_key'))->get('https://api.openai.com/v1/models');

        return [
            'successful' => $response->successful(),
            'status_code' => $response->status(),
            'response' => $response->successful() ? ['model_count' => count($response->json('data', []))] : ($response->json() ?? []),
            'error' => $response->successful() ? null : ($response->json('error.message') ?? 'OpenAI connection failed'),
        ];
    }

    /**
     * Real chat completion call used for generating AI-narrated insights.
     */
    public function complete(string $prompt, string $model = 'gpt-4o-mini'): string
    {
        $response = Http::withToken($this->credential('api_key'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.4,
            ]);

        return $response->json('choices.0.message.content') ?? '';
    }
}
