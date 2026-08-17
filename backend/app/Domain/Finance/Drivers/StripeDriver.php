<?php

namespace App\Domain\Finance\Drivers;

use App\Domain\Finance\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;

/**
 * Real Stripe PaymentIntents API (https://api.stripe.com/v1/payment_intents).
 * Sandbox and production use the same host — the secret key prefix
 * (sk_test_ vs sk_live_) determines the mode, so `baseUrl()` isn't needed.
 */
class StripeDriver extends AbstractGatewayDriver
{
    private const BASE_URL = 'https://api.stripe.com/v1';

    public function charge(PaymentTransaction $transaction): array
    {
        $this->ensureConfigured();

        $payload = [
            'amount' => (int) round(((float) $transaction->amount) * 100),
            'currency' => strtolower($transaction->currency),
            'description' => "BiasharaMax — {$transaction->reference_number}",
            'metadata' => ['reference_number' => $transaction->reference_number],
        ];

        $response = Http::asForm()
            ->withToken($this->credential('secret_key'))
            ->post(self::BASE_URL.'/payment_intents', $payload);

        $body = $response->json() ?? [];
        $successful = $response->successful() && ($body['status'] ?? null) === 'succeeded';

        $this->log('charge', $payload, $body, $successful, $response->status(), transaction: $transaction);

        return ['successful' => $successful, 'external_id' => $body['id'] ?? null, 'raw' => $body];
    }

    public function verify(string $externalTransactionId): array
    {
        $this->ensureConfigured();

        $response = Http::withToken($this->credential('secret_key'))
            ->get(self::BASE_URL."/payment_intents/{$externalTransactionId}");

        $body = $response->json() ?? [];
        $successful = $response->successful() && ($body['status'] ?? null) === 'succeeded';

        $this->log('verify', [], $body, $successful, $response->status());

        return ['successful' => $successful, 'status' => $body['status'] ?? 'unknown', 'raw' => $body];
    }

    public function refund(PaymentTransaction $transaction, string $amount): array
    {
        $this->ensureConfigured();

        $payload = [
            'payment_intent' => $transaction->external_transaction_id,
            'amount' => (int) round(((float) $amount) * 100),
        ];

        $response = Http::asForm()->withToken($this->credential('secret_key'))->post(self::BASE_URL.'/refunds', $payload);
        $body = $response->json() ?? [];
        $successful = $response->successful() && ($body['status'] ?? null) === 'succeeded';

        $this->log('refund', $payload, $body, $successful, $response->status(), transaction: $transaction);

        return ['successful' => $successful, 'raw' => $body];
    }
}
