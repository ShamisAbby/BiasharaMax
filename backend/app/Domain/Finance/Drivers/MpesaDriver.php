<?php

namespace App\Domain\Finance\Drivers;

use App\Domain\Finance\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

/**
 * Real Safaricom Daraja M-Pesa API (STK Push / Lipa na M-Pesa Online).
 * Sandbox and production are different hosts; an OAuth token is fetched
 * fresh per call from consumer key/secret.
 */
class MpesaDriver extends AbstractGatewayDriver
{
    private function baseApiUrl(): string
    {
        return $this->baseUrl('https://sandbox.safaricom.co.ke', 'https://api.safaricom.co.ke');
    }

    private function accessToken(): ?string
    {
        $response = Http::withBasicAuth(
            (string) $this->credential('consumer_key'),
            (string) $this->credential('consumer_secret'),
        )->get($this->baseApiUrl().'/oauth/v1/generate', ['grant_type' => 'client_credentials']);

        return $response->json('access_token');
    }

    public function charge(PaymentTransaction $transaction): array
    {
        $this->ensureConfigured();

        $shortcode = (string) $this->credential('shortcode');
        $passkey = (string) $this->credential('passkey');
        $timestamp = Carbon::now()->format('YmdHis');
        $password = base64_encode($shortcode.$passkey.$timestamp);

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) round((float) $transaction->amount),
            'PartyA' => $this->credential('phone_number'),
            'PartyB' => $shortcode,
            'PhoneNumber' => $this->credential('phone_number'),
            'CallBackURL' => $this->gateway->webhook_url,
            'AccountReference' => $transaction->reference_number,
            'TransactionDesc' => "BiasharaMax — {$transaction->reference_number}",
        ];

        $response = Http::withToken($this->accessToken())->post($this->baseApiUrl().'/mpesa/stkpush/v1/processrequest', $payload);

        $body = $response->json() ?? [];
        $successful = $response->successful() && ($body['ResponseCode'] ?? null) === '0';

        $this->log('charge', $this->redactMpesa($payload), $body, $successful, $response->status(), transaction: $transaction);

        return ['successful' => $successful, 'external_id' => $body['CheckoutRequestID'] ?? null, 'raw' => $body];
    }

    public function verify(string $externalTransactionId): array
    {
        $this->ensureConfigured();

        $shortcode = (string) $this->credential('shortcode');
        $passkey = (string) $this->credential('passkey');
        $timestamp = Carbon::now()->format('YmdHis');

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password' => base64_encode($shortcode.$passkey.$timestamp),
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $externalTransactionId,
        ];

        $response = Http::withToken($this->accessToken())->post($this->baseApiUrl().'/mpesa/stkpushquery/v1/query', $payload);

        $body = $response->json() ?? [];
        $successful = $response->successful() && ($body['ResultCode'] ?? null) === '0';

        $this->log('verify', $this->redactMpesa($payload), $body, $successful, $response->status());

        return ['successful' => $successful, 'status' => $body['ResultDesc'] ?? 'unknown', 'raw' => $body];
    }

    /**
     * M-Pesa has no first-class refund API — reversals are a manual
     * back-office process via Daraja's B2C/Reversal API, which requires
     * a security-credential certificate exchange beyond plain API keys.
     * Honest rather than fabricated: this always reports unsupported.
     */
    public function refund(PaymentTransaction $transaction, string $amount): array
    {
        $this->log('refund', [], [], false, null, 'M-Pesa refunds require manual reversal via Safaricom — not automatable here.', $transaction);

        return ['successful' => false, 'raw' => ['error' => 'manual_reversal_required']];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function redactMpesa(array $payload): array
    {
        $payload['Password'] = '••••••••';

        return $payload;
    }
}
