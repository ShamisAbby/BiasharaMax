<?php

namespace App\Domain\Integrations\Services;

use App\Domain\Integrations\Contracts\IntegrationTestDriver;
use App\Domain\Integrations\Drivers\ClaudeTestDriver;
use App\Domain\Integrations\Drivers\GeminiTestDriver;
use App\Domain\Integrations\Drivers\GenericHttpTestDriver;
use App\Domain\Integrations\Drivers\GoogleMapsTestDriver;
use App\Domain\Integrations\Drivers\OpenAiTestDriver;
use App\Domain\Integrations\Drivers\SlackTestDriver;
use App\Domain\Integrations\Models\Integration;

class IntegrationDriverResolver
{
    /**
     * @var array<string, class-string<IntegrationTestDriver>>
     */
    private const DRIVER_MAP = [
        'openai' => OpenAiTestDriver::class,
        'claude' => ClaudeTestDriver::class,
        'gemini' => GeminiTestDriver::class,
        'google_maps' => GoogleMapsTestDriver::class,
        'slack' => SlackTestDriver::class,
    ];

    public function resolve(Integration $integration): IntegrationTestDriver
    {
        // Normalised, because the provider is free text in the admin UI:
        // an integration saved as "Gemini" would otherwise miss the
        // `gemini` key and fall back to the generic HTTP driver, which
        // then fails with the thoroughly unhelpful "No test_endpoint
        // credential configured" — nothing pointing at the real cause.
        $driverClass = self::DRIVER_MAP[Integration::normalizeKey($integration->provider)]
            ?? GenericHttpTestDriver::class;

        return new $driverClass($integration);
    }
}
