<?php

namespace App\Domain\Finance\Drivers;

use App\Domain\Finance\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;

/**
 * For regional mobile-money providers (Airtel Money, Tigo Pesa, HaloPesa,
 * Mixx by Yas, Snippe) and any "Custom Gateway" entry: there is no single
 * stable public REST contract worth hardcoding per-provider with
 * confidence. Instead, the gateway's own credentials configure a real
 * HTTP endpoint + auth scheme, and this driver posts to it directly —
 * genuinely callable, just generic rather than provider-specific.
 */
class GenericHttpGatewayDriver extends AbstractGatewayDriver
{
    public function charge(PaymentTransaction $transaction): array
    {
        $this->ensureConfigured();

        $endpoint = $this->credential('charge_endpoint');
        $payload = [
            'reference' => $transaction->reference_number,
            'amount' => (string) $transaction->amount,
            'currency' => $transaction->currency,
            'callback_url' => $this->gateway->webhook_url,
        ];

        $response = Http::withToken($this->credential('api_key'))->post((string) $endpoint, $payload);

        $body = $response->json() ?? [];
        $successful = $response->successful() && (bool) ($body['successful'] ?? $body['success'] ?? false);

        $this->log('charge', $payload, $body, $successful, $response->status(), transaction: $transaction);

        return ['successful' => $successful, 'external_id' => $body['transaction_id'] ?? null, 'raw' => $body];
    }

    public function verify(string $externalTransactionId): array
    {
        $this->ensureConfigured();

        $endpoint = $this->credential('verify_endpoint');

        $response = Http::withToken($this->credential('api_key'))->get((string) $endpoint, ['transaction_id' => $externalTransactionId]);

        $body = $response->json() ?? [];
        $successful = $response->successful() && (bool) ($body['successful'] ?? $body['success'] ?? false);

        $this->log('verify', [], $body, $successful, $response->status());

        return ['successful' => $successful, 'status' => $body['status'] ?? 'unknown', 'raw' => $body];
    }

    public function refund(PaymentTransaction $transaction, string $amount): array
    {
        $this->ensureConfigured();

        $endpoint = $this->credential('refund_endpoint');
        $payload = ['transaction_id' => $transaction->external_transaction_id, 'amount' => $amount];

        $response = Http::withToken($this->credential('api_key'))->post((string) $endpoint, $payload);

        $body = $response->json() ?? [];
        $successful = $response->successful() && (bool) ($body['successful'] ?? $body['success'] ?? false);

        $this->log('refund', $payload, $body, $successful, $response->status(), transaction: $transaction);

        return ['successful' => $successful, 'raw' => $body];
    }
}
