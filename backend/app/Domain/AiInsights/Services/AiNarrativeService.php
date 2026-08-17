<?php

namespace App\Domain\AiInsights\Services;

use App\Domain\Integrations\Models\Integration;
use Illuminate\Support\Facades\Log;

/**
 * Generates a narrative summary over real computed statistics by
 * calling whichever AI provider integration is actually enabled and
 * configured. Returns null — never a fabricated sentence — if no AI
 * integration is configured.
 */
class AiNarrativeService
{
    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * The most recent reason a summary could not be produced.
     *
     * `summarize()` returns null for four quite different reasons, and
     * the callers were reporting all of them as "No AI provider is
     * configured" — which is the truth for exactly one, and actively
     * misleading for the rest. An operator staring at an integration
     * card that says Enabled, Configured and last-tested-successful is
     * being told the opposite of what they can see.
     */
    private ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Why a summary cannot be generated right now, or null if it can.
     *
     * Checked without calling the provider, so a screen can explain
     * itself before anyone presses the button.
     */
    public function unavailableReason(): ?string
    {
        $integration = $this->aiIntegration();

        if (! $integration) {
            return 'No AI integration is enabled. Enable one under Integrations to generate narrative summaries.';
        }

        if (! $integration->isConfigured()) {
            return "The \"{$integration->name}\" integration is enabled but has no credentials saved. Add them under Configure.";
        }

        if (! $this->driverFor($integration)) {
            return "The \"{$integration->name}\" integration has provider \"{$integration->provider}\", which does not match a supported AI driver. Use openai, claude or gemini.";
        }

        return null;
    }

    public function summarize(string $title, array $data): ?string
    {
        $this->lastError = $this->unavailableReason();

        if ($this->lastError !== null) {
            return null;
        }

        $integration = $this->aiIntegration();
        $driver = $this->driverFor($integration);

        $prompt = "You are a business intelligence assistant for a retail/wholesale business. "
            . "Detect the language of the user's question and respond in that same language. "
            . "If the question is in Swahili, answer fully in Swahili. "
            . "If the question is in English, answer fully in English. "
            . "Answer using ONLY the provided real business data below. "
            . "Be concise (1-3 sentences). Never fabricate numbers not present in the data. "
            . "If the data is insufficient to answer, say so clearly in the same language as the question.\n\n"
            . "User question: {$title}\n\n"
            . "Business data:\n" . json_encode($data, JSON_PRETTY_PRINT);

        try {
            $summary = $driver->complete($prompt);

            if ($summary === '') {
                $this->lastError = "The {$integration->provider} provider returned an empty response.";

                return null;
            }

            return $summary;
        } catch (\Throwable $e) {
            $this->lastError = "The {$integration->provider} provider returned an error: {$e->getMessage()}";

            // Still returns null — callers must never show a fabricated
            // sentence — but the reason is logged rather than discarded.
            // Swallowing it silently is what made a retired Gemini model
            // look like "the AI just doesn't answer": the request was
            // 404ing every time and nothing anywhere said so.
            Log::warning('AI narrative generation failed.', [
                'provider' => $integration->provider,
                'integration_id' => $integration->getKey(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function aiIntegration(): ?Integration
    {
        return Integration::query()
            ->where('category', Integration::CATEGORY_AI)
            ->where('is_enabled', true)
            ->first();
    }

    /**
     * Picks a driver from the integration's provider string.
     *
     * Matched loosely, on purpose. `provider` is a free-text field —
     * IntegrationRequest validates only `string|max:40` — so an admin
     * creating an integration by hand types whatever reads naturally.
     * The previous exact `match ($integration->provider)` against
     * lowercase keys meant "Gemini", "Google Gemini" or "gemini-1.5"
     * all silently produced no driver, and the UI then reported the one
     * thing that was definitely not true: that nothing was configured.
     *
     * Substring matching is the pragmatic fix while the field stays
     * free-text. Constraining it to an enum would be the better one, and
     * would break every integration already created under the old rule.
     */
    private function driverFor(?Integration $integration): ?object
    {
        if (! $integration) {
            return null;
        }

        $provider = mb_strtolower(trim((string) $integration->provider));

        $driver = match (true) {
            str_contains($provider, 'openai'),
            str_contains($provider, 'gpt') => new \App\Domain\Integrations\Drivers\OpenAiTestDriver($integration),

            str_contains($provider, 'claude'),
            str_contains($provider, 'anthropic') => new \App\Domain\Integrations\Drivers\ClaudeTestDriver($integration),

            str_contains($provider, 'gemini'),
            str_contains($provider, 'google') => new \App\Domain\Integrations\Drivers\GeminiTestDriver($integration),

            default => null,
        };

        // A driver that cannot complete a prompt is no use here — the
        // test drivers exist primarily to verify credentials.
        return $driver && method_exists($driver, 'complete') ? $driver : null;
    }
}
