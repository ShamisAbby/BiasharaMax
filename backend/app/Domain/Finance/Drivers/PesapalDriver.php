<?php

namespace App\Domain\Finance\Drivers;

use App\Domain\Finance\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;

/**
 * Real Pesapal v3 API. Sandbox and production are different hosts, and
 * every call requires a short-lived OAuth bearer token fetched fresh via
 * consumer key/secret before the actual order/status call.
 */
class PesapalDriver extends AbstractGatewayDriver
{
    private function baseApiUrl(): string
    {
        return $this->baseUrl(
            'https://cybqa.pesapal.com/pesapalv3/api',
            'https://pay.pesapal.com/v3/api',
        );
    }

    private function accessToken(): ?string
    {
        $response = Http::asJson()->post($this->baseApiUrl().'/Auth/RequestToken', [
            'consumer_key' => $this->credential('consumer_key'),
            'consumer_secret' => $this->credential('consumer_secret'),
        ]);

        return $response->json('token');
    }

    public function charge(PaymentTransaction $transaction): array
    {
        $this->ensureConfigured();

        $payload = [
            'id' => $transaction->reference_number,
            'currency' => $transaction->currency,
            'amount' => (float) $transaction->amount,
            'description' => "BiasharaMax — {$transaction->reference_number}",
            'callback_url' => $this->gateway->webhook_url,
            'notification_id' => $this->credential('ipn_id'),
            'billing_address' => ['email_address' => $transaction->business?->email],
        ];

        $response = Http::withToken($this->accessToken())->post($this->baseApiUrl().'/Transactions/SubmitOrderRequest', $payload);

        $body = $response->json() ?? [];
        $successful = $response->successful() && empty($body['error']);

        $this->log('charge', $payload, $body, $successful, $response->status(), transaction: $transaction);

        return ['successful' => $successful, 'external_id' => $body['order_tracking_id'] ?? null, 'raw' => $body];
    }

    public function verify(string $externalTransactionId): array
    {
        $this->ensureConfigured();

        $response = Http::withToken($this->accessToken())
            ->get($this->baseApiUrl().'/Transactions/GetTransactionStatus', ['orderTrackingId' => $externalTransactionId]);

        $body = $response->json() ?? [];
        $successful = $response->successful() && ($body['payment_status_description'] ?? null) === 'Completed';

        $this->log('verify', [], $body, $successful, $response->status());

        return ['successful' => $successful, 'status' => $body['payment_status_description'] ?? 'unknown', 'raw' => $body];
    }

    public function refund(PaymentTransaction $transaction, string $amount): array
    {
        $this->ensureConfigured();

        $payload = [
            'confirmation_code' => $transaction->external_transaction_id,
            'amount' => (float) $amount,
            'username' => $this->credential('username'),
            'remarks' => "Refund for {$transaction->reference_number}",
        ];

        $response = Http::withToken($this->accessToken())->post($this->baseApiUrl().'/Transactions/RefundRequest', $payload);

        $body = $response->json() ?? [];
        $successful = $response->successful() && ($body['status'] ?? null) === '200';

        $this->log('refund', $payload, $body, $successful, $response->status(), transaction: $transaction);

        return ['successful' => $successful, 'raw' => $body];
    }
}
