<?php

namespace App\Domain\Integrations\Drivers;

use App\Domain\Integrations\Contracts\IntegrationTestDriver;
use App\Domain\Integrations\Exceptions\IntegrationNotConfiguredException;
use App\Domain\Integrations\Models\Integration;

abstract class AbstractTestDriver implements IntegrationTestDriver
{
    public function __construct(protected readonly Integration $integration) {}

    protected function ensureConfigured(): void
    {
        if (! $this->integration->isConfigured()) {
            throw IntegrationNotConfiguredException::forIntegration($this->integration);
        }
    }

    /**
     * Credential keys are typed by hand in the admin UI, so `API Key`,
     * `api-key` and `API_KEY` all mean `api_key` to whoever entered
     * them. An exact-match lookup returns null for every one of those
     * and the driver reports an auth failure with no hint that the key
     * was actually present under a different spelling — so the stored
     * keys are normalised before comparing.
     *
     * The exact key still wins when present, so nothing changes for
     * integrations that were already correct.
     */
    protected function credential(string $key): ?string
    {
        $credentials = $this->integration->credentials ?? [];

        if (array_key_exists($key, $credentials)) {
            return $credentials[$key];
        }

        foreach ($credentials as $storedKey => $value) {
            if (Integration::normalizeKey($storedKey) === Integration::normalizeKey($key)) {
                return $value;
            }
        }

        return null;
    }
}
