<?php

namespace App\Domain\Finance\Drivers;

use App\Domain\Finance\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;

/**
 * Real Flutterwave v3 API (https://api.flutterwave.com/v3). One host for
 * both sandbox and production — the secret key itself determines mode.
 */
class FlutterwaveDriver extends AbstractGatewayDriver
{
    private const BASE_URL = 'https://api.flutterwave.com/v3';

    public function charge(PaymentTransaction $transaction): array
    {
        $this->ensureConfigured();

        $payload = [
            'tx_ref' => $transaction->reference_number,
            'amount' => (string) $transaction->amount,
            'currency' => $transaction->currency,
            'redirect_url' => $this->gateway->webhook_url,
            'customer' => ['email' => $transaction->business?->email],
        ];

        $response = Http::withToken($this->credential('secret_key'))
            ->post(self::BASE_URL.'/payments', $payload);

        $body = $response->json() ?? [];
        $successful = $response->successful() && ($body['status'] ?? null) === 'success';

        $this->log('charge', $payload, $body, $successful, $response->status(), transaction: $transaction);

        return ['successful' => $successful, 'external_id' => $body['data']['id'] ?? null, 'raw' => $body];
    }

    public function verify(string $externalTransactionId): array
    {
        $this->ensureConfigured();

        $response = Http::withToken($this->credential('secret_key'))
            ->get(self::BASE_URL."/transactions/{$externalTransactionId}/verify");

        $body = $response->json() ?? [];
        $successful = $response->successful() && ($body['data']['status'] ?? null) === 'successful';

        $this->log('verify', [], $body, $successful, $response->status());

        return ['successful' => $successful, 'status' => $body['data']['status'] ?? 'unknown', 'raw' => $body];
    }

    public function refund(PaymentTransaction $transaction, string $amount): array
    {
        $this->ensureConfigured();

        $payload = ['amount' => $amount];

        $response = Http::withToken($this->credential('secret_key'))
            ->post(self::BASE_URL."/transactions/{$transaction->external_transaction_id}/refund", $payload);

        $body = $response->json() ?? [];
        $successful = $response->successful() && ($body['status'] ?? null) === 'success';

        $this->log('refund', $payload, $body, $successful, $response->status(), transaction: $transaction);

        return ['successful' => $successful, 'raw' => $body];
    }
}
