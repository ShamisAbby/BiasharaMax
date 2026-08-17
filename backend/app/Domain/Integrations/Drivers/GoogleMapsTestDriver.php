<?php

namespace App\Domain\Integrations\Drivers;

use Illuminate\Support\Facades\Http;

/**
 * Real Google Maps Geocoding API — a trivial real lookup confirms the
 * API key works without needing a paid Maps JavaScript session.
 */
class GoogleMapsTestDriver extends AbstractTestDriver
{
    public function test(): array
    {
        $this->ensureConfigured();

        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => 'Dar es Salaam',
            'key' => $this->credential('api_key'),
        ]);

        $status = $response->json('status');
        $successful = $response->successful() && $status === 'OK';

        return [
            'successful' => $successful,
            'status_code' => $response->status(),
            'response' => ['google_status' => $status],
            'error' => $successful ? null : ($status ?? 'Google Maps connection failed'),
        ];
    }
}
