<?php

namespace App\Domain\Finance\Drivers;

use App\Domain\Finance\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;

/**
 * Snippe — mobile money and cards for Tanzania.
 *
 * Ported from the working integration in the Tripfy Africa codebase rather
 * than written from the published docs. That distinction matters: the docs
 * describe a Payment Sessions API at `/api/v1/sessions`, and the code that
 * actually takes money in production posts to `/v1/payments` with a
 * different payload. Building from the documentation would have produced
 * something plausible that failed on the first real charge.
 *
 * Two behaviours below exist only because someone hit them for real:
 *
 *  - The **form-encoded retry**. Snippe sometimes answers a valid JSON body
 *    with a list of "<field> is required" errors. Re-posting the identical
 *    payload as form fields succeeds. Detected narrowly (three or more
 *    errors, all of them "is required") so a genuine validation failure is
 *    still reported rather than retried into a second failure.
 *  - **Phone normalisation** to `+255…`. Tanzanian numbers arrive as
 *    `0712…`, `255712…`, `+255 712…` and `712…`, and the API accepts one
 *    shape.
 *
 * @see https://docs.snippe.sh/
 */
class SnippeDriver extends AbstractGatewayDriver
{
    private const BASE_URL = 'https://api.snippe.sh';

    public function charge(PaymentTransaction $transaction): array
    {
        $this->ensureConfigured();

        $payload = $this->buildPayload($transaction);

        // Idempotency-Key is required by Snippe to make a retry safe. Keyed
        // on our own reference so a network timeout followed by a retry
        // returns the original response instead of charging twice.
        $result = $this->post('/v1/payments', $payload, $transaction->reference_number);

        $body = $result['decoded'];
        $successful = is_array($body) && $this->succeeded($body);
        $data = is_array($body) ? ($body['data'] ?? []) : [];

        $this->log('charge', $payload, is_array($body) ? $body : [], $successful, $result['status'], transaction: $transaction);

        return [
            'successful' => $successful,
            'external_id' => $data['reference'] ?? null,
            // The hosted page for card payments. Mobile money has no URL —
            // the customer gets a USSD push instead — so callers must treat
            // a null here as "wait for the webhook", not as a failure.
            'checkout_url' => $this->checkoutUrl(is_array($data) ? $data : []),
            'raw' => is_array($body) ? $body : [],
        ];
    }

    public function verify(string $externalTransactionId): array
    {
        $this->ensureConfigured();

        $response = Http::withHeaders($this->headers())
            ->get(self::BASE_URL.'/v1/payments/'.$externalTransactionId);

        $body = $response->json() ?? [];
        $status = strtolower((string) (data_get($body, 'data.status') ?? ''));
        $successful = $response->successful() && in_array($status, ['completed', 'successful', 'paid'], true);

        $this->log('verify', [], $body, $successful, $response->status());

        return ['successful' => $successful, 'status' => $status ?: 'unknown', 'raw' => $body];
    }

    /**
     * Not supported.
     *
     * Snippe's public API has no refund endpoint. Returning a failure with
     * a message beats throwing: the caller can show the operator what to do
     * rather than surfacing an exception that reads like a bug in this app.
     */
    public function refund(PaymentTransaction $transaction, string $amount): array
    {
        $this->log('refund', ['amount' => $amount], [], false, null, 'Snippe has no refund API', $transaction);

        return [
            'successful' => false,
            'raw' => ['message' => 'Refunds must be issued from the Snippe dashboard.'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(PaymentTransaction $transaction): array
    {
        $currency = strtoupper((string) $transaction->currency);

        return [
            // `mobile` by default: M-Pesa, Airtel Money, Mixx by Yas and
            // Halotel between them cover far more of this market than cards.
            'payment_type' => $transaction->metadata['payment_type'] ?? 'mobile',
            'reference' => $transaction->reference_number,
            'currency' => $currency,
            // Whole units, not minor units. TZS has no subunit in practice
            // and Snippe rejects decimals here.
            'amount' => (int) round((float) $transaction->amount),
            'phone' => $this->normalisePhone((string) ($transaction->metadata['phone'] ?? '')),
            'phone_number' => preg_replace('/\D+/', '', $this->normalisePhone((string) ($transaction->metadata['phone'] ?? ''))) ?? '',
            'details' => [
                'amount' => (int) round((float) $transaction->amount),
                'currency' => $currency,
                'redirect_url' => $transaction->metadata['redirect_url'] ?? url('/'),
                'cancel_url' => $transaction->metadata['cancel_url'] ?? url('/'),
            ],
            'callback_url' => $transaction->metadata['redirect_url'] ?? url('/'),
            'webhook_url' => route('webhooks.snippe'),
            // Our own identifiers travel with the payment and come back on
            // the webhook, so a completed payment can be matched without a
            // second API call.
            'metadata' => [
                'reference' => $transaction->reference_number,
                'transaction_id' => (string) $transaction->getKey(),
            ],
            'customer' => array_filter([
                'firstname' => $transaction->metadata['first_name'] ?? null,
                'lastname' => $transaction->metadata['last_name'] ?? null,
                'email' => $transaction->metadata['email'] ?? null,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{decoded: mixed, status: int}
     */
    private function post(string $path, array $payload, string $idempotencyKey): array
    {
        $response = Http::withHeaders($this->headers($idempotencyKey))
            ->post(self::BASE_URL.$path, $payload);

        $decoded = $response->json();

        if (! $this->looksLikeMissingPayload($decoded)) {
            return ['decoded' => $decoded, 'status' => $response->status()];
        }

        // The retry described in the class docblock. Same payload, flattened
        // into form fields.
        $formResponse = Http::asForm()
            ->withHeaders($this->headers($idempotencyKey))
            ->post(self::BASE_URL.$path, $this->flatten($payload));

        return ['decoded' => $formResponse->json(), 'status' => $formResponse->status()];
    }

    /**
     * @return array<string, string>
     */
    private function headers(?string $idempotencyKey = null): array
    {
        return array_filter([
            'Authorization' => 'Bearer '.$this->credential('api_key'),
            'Accept' => 'application/json',
            'Idempotency-Key' => $idempotencyKey,
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function succeeded(array $body): bool
    {
        return ($body['status'] ?? null) === 'success' || ($body['success'] ?? false) === true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function checkoutUrl(array $data): ?string
    {
        foreach (['payment_url', 'checkout_url', 'authorization_url', 'url', 'link'] as $key) {
            $value = is_string($data[$key] ?? null) ? trim($data[$key]) : '';

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Is this the "everything is required" response that a form re-post fixes?
     *
     * Deliberately narrow. Retrying every validation failure would turn one
     * clear error into two confusing ones.
     */
    private function looksLikeMissingPayload(mixed $decoded): bool
    {
        if (! is_array($decoded)) {
            return false;
        }

        $errors = $decoded['errors'] ?? $decoded['data'] ?? [];

        if (! is_array($errors) || count($errors, COUNT_RECURSIVE) < 3) {
            return false;
        }

        $flat = [];
        array_walk_recursive($errors, function ($value) use (&$flat) {
            $flat[] = (string) $value;
        });

        if (count($flat) < 3) {
            return false;
        }

        foreach ($flat as $message) {
            if (! preg_match('/is required[.;]?$/i', trim($message))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function flatten(array $payload, string $prefix = ''): array
    {
        $flat = [];

        foreach ($payload as $key => $value) {
            $field = $prefix === '' ? (string) $key : "{$prefix}[{$key}]";

            if (is_array($value)) {
                $flat += $this->flatten($value, $field);

                continue;
            }

            if ($value !== null) {
                $flat[$field] = is_bool($value) ? ($value ? '1' : '0') : $value;
            }
        }

        return $flat;
    }

    /**
     * Tanzanian numbers into the one shape Snippe accepts.
     *
     * Handles `0712…`, `712…`, `255712…`, `+255 712 …` and `00255…`.
     */
    private function normalisePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', trim($phone)) ?? '';

        if ($phone === '') {
            return '';
        }

        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        if (str_starts_with($phone, '0')) {
            $phone = '255'.substr($phone, 1);
        }

        // A bare local number with no country code at all.
        if (! str_starts_with($phone, '255') && strlen($phone) === 9) {
            $phone = '255'.$phone;
        }

        return '+'.$phone;
    }
}
