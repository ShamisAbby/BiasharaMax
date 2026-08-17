<?php

namespace App\Domain\Integrations\Drivers;

use Illuminate\Support\Facades\Http;

/**
 * For Discord, Zapier, Make.com, Dropbox/OneDrive/Drive, FTP-backed
 * storage, OAuth providers, and "Custom" — no single hardcoded
 * provider contract to trust. Credentials configure a real test
 * endpoint + bearer token; this driver pings it directly. Same
 * fallback pattern used for payment gateways and notification
 * channels elsewhere in this codebase.
 */
class GenericHttpTestDriver extends AbstractTestDriver
{
    public function test(): array
    {
        $this->ensureConfigured();

        $endpoint = $this->credential('test_endpoint');

        if (! $endpoint) {
            return ['successful' => false, 'status_code' => null, 'response' => [], 'error' => 'No test_endpoint credential configured.'];
        }

        $response = Http::withToken($this->credential('api_key'))->get($endpoint);

        return [
            'successful' => $response->successful(),
            'status_code' => $response->status(),
            'response' => $response->successful() ? ['ok' => true] : ($response->json() ?? []),
            'error' => $response->successful() ? null : 'Connection test failed.',
        ];
    }
}
