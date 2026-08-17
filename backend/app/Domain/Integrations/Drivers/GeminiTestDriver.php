<?php

namespace App\Domain\Integrations\Drivers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Real Google Gemini API, via the Interactions API.
 *
 * Three things worth knowing if this ever stops working again:
 *
 *  - Google retires model names, and quickly. `gemini-1.5-flash` (the
 *    previous default here) no longer exists at all, and even
 *    `gemini-2.0-flash` is shut down. A request naming a retired model
 *    returns 404 NOT_FOUND. The model is therefore configurable per
 *    integration via a `model` credential — see DEFAULT_MODEL.
 *  - The endpoint is `POST /v1beta/interactions`, NOT the older
 *    `/v1beta/models/{model}:generateContent`. The Interactions API is
 *    now the documented and recommended surface for text generation.
 *  - The API key goes in the `x-goog-api-key` header. The older
 *    `?key=...` query string still works, but a key in a URL is far more
 *    likely to end up in a log or proxy trace than one in a header.
 *
 * `store: false` is sent on every request: this prompt carries the
 * business's own figures, and there is no reason to have Google retain
 * conversation state for a one-shot summary.
 */
class GeminiTestDriver extends AbstractTestDriver
{
    /**
     * Overridable per integration via the `model` credential, so a model
     * retirement can be worked around from the admin UI without a deploy.
     */
    public const DEFAULT_MODEL = 'gemini-3.6-flash';

    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/interactions';

    public function test(): array
    {
        $this->ensureConfigured();

        // A real (tiny) generation rather than a model listing. Listing
        // would pass with a retired model still configured, which is
        // exactly how this integration reported "connected" while every
        // actual request failed. This checks key, model and endpoint at
        // once — the three things that can be wrong.
        $response = $this->request()->post(self::ENDPOINT, [
            'model' => $this->model(),
            'input' => 'Reply with the single word: ok',
            'store' => false,
        ]);

        return [
            'successful' => $response->successful(),
            'status_code' => $response->status(),
            'response' => $response->successful()
                ? ['model' => $this->model(), 'reply' => $this->extractText($response->json() ?? [])]
                : ($response->json() ?? []),
            'error' => $response->successful()
                ? null
                : ($response->json('error.message') ?? 'Gemini connection failed'),
        ];
    }

    /**
     * @throws RuntimeException when the API rejects the request or returns
     *                          no usable text, so the caller can report
     *                          why instead of treating failure as silence.
     */
    public function complete(string $prompt, ?string $model = null): string
    {
        $this->ensureConfigured();

        $response = $this->request()->post(self::ENDPOINT, [
            'model' => $model ?? $this->model(),
            'input' => $prompt,
            'store' => false,
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Gemini request failed ('.$response->status().'): '
                .($response->json('error.message') ?? 'unknown error'),
            );
        }

        $text = $this->extractText($response->json() ?? []);

        if ($text === '') {
            throw new RuntimeException('Gemini returned no text content.');
        }

        return $text;
    }

    /**
     * Pulls the reply out of an Interactions response.
     *
     * The response is a list of `steps`, and only some carry text — a
     * model with thinking enabled also returns thought steps, and tool
     * use adds more. This mirrors the SDKs' `output_text` helper: take
     * the text blocks of the LAST step that has any, so reasoning is
     * never mistaken for the answer.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractText(array $payload): string
    {
        foreach (array_reverse($payload['steps'] ?? []) as $step) {
            $text = collect($step['content'] ?? [])
                ->filter(fn ($block): bool => ($block['type'] ?? null) === 'text')
                ->pluck('text')
                ->filter(fn ($value): bool => is_string($value) && $value !== '')
                ->implode('');

            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function model(): string
    {
        $model = $this->credential('model');

        return is_string($model) && $model !== '' ? $model : self::DEFAULT_MODEL;
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders(['x-goog-api-key' => $this->credential('api_key')])
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }
}
