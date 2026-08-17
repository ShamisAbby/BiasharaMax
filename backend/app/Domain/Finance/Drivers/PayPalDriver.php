<?php

namespace App\Domain\Finance\Drivers;

use App\Domain\Finance\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;

/**
 * Real PayPal Orders v2 API. Sandbox and production are different hosts;
 * every call needs a client-credentials OAuth token fetched fresh first.
 */
class PayPalDriver extends AbstractGatewayDriver
{
    private function baseApiUrl(): string
    {
        return $this->baseUrl('https://api-m.sandbox.paypal.com', 'https://api-m.paypal.com');
    }

    private function accessToken(): ?string
    {
        $response = Http::asForm()
            ->withBasicAuth((string) $this->credential('client_id'), (string) $this->credential('client_secret'))
            ->post($this->baseApiUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        return $response->json('access_token');
    }

    public function charge(PaymentTransaction $transaction): array
    {
        $this->ensureConfigured();

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $transaction->reference_number,
                'amount' => ['currency_code' => $transaction->currency, 'value' => (string) $transaction->amount],
            ]],
        ];

        $response = Http::withToken($this->accessToken())->post($this->baseApiUrl().'/v2/checkout/orders', $payload);

        $body = $response->json() ?? [];
        $successful = $response->successful() && in_array($body['status'] ?? null, ['CREATED', 'COMPLETED'], true);

        $this->log('charge', $payload, $body, $successful, $response->status(), transaction: $transaction);

        return ['successful' => $successful, 'external_id' => $body['id'] ?? null, 'raw' => $body];
    }

    public function verify(string $externalTransactionId): array
    {
        $this->ensureConfigured();

        $response = Http::withToken($this->accessToken())->get($this->baseApiUrl()."/v2/checkout/orders/{$externalTransactionId}");

        $body = $response->json() ?? [];
        $successful = $response->successful() && ($body['status'] ?? null) === 'COMPLETED';

        $this->log('verify', [], $body, $successful, $response->status());

        return ['successful' => $successful, 'status' => $body['status'] ?? 'unknown', 'raw' => $body];
    }

    public function refund(PaymentTransaction $transaction, string $amount): array
    {
        $this->ensureConfigured();

        $payload = ['amount' => ['value' => $amount, 'currency_code' => $transaction->currency]];

        $response = Http::withToken($this->accessToken())
            ->post($this->baseApiUrl()."/v2/payments/captures/{$transaction->external_transaction_id}/refund", $payload);

        $body = $response->json() ?? [];
        $successful = $response->successful() && ($body['status'] ?? null) === 'COMPLETED';

        $this->log('refund', $payload, $body, $successful, $response->status(), transaction: $transaction);

        return ['successful' => $successful, 'raw' => $body];
    }
}
