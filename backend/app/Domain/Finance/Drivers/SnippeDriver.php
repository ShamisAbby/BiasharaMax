<?php

namespace App\Domain\Finance\Drivers;

use App\Domain\Finance\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;

/**
 * Snippe — mobile money and cards for Tanzania.
 *
 * Two methods, two shapes, one endpoint:
 *
 *  - **Mobile money** sends a USSD prompt to the customer's phone. Nothing
 *    to redirect to, so the page they came from has to wait and poll.
 *  - **Card** returns a `payment_url` to redirect the browser to.
 *
 * Snippe's hosted Payment Sessions API was tried and abandoned: its
 * `allowed_methods` accepts only `mobile_money`, so it cannot offer a card
 * at all. Method choice therefore belongs on a BiasharaMax page, which is
 * where it now lives.
 *
 * Two behaviours below exist only because they were hit for real:
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

    /**
     * Open a hosted checkout and hand back its URL.
     *
     * Uses Snippe's **Payment Sessions** API rather than posting a direct
     * charge. The difference is the whole point of this method:
     *
     *  - `/v1/payments` pushes a USSD prompt to one phone number. There is
     *    no page, no method choice, and no return trip — the browser sits
     *    on whatever screen it was on. A customer who paid saw nothing
     *    happen, which is exactly what went wrong in production.
     *  - `/api/v1/sessions` returns a `checkout_url`. The customer picks
     *    their own network there, pays, and Snippe returns them to
     *    `redirect_url`.
     *
     * Branding on that page — merchant name, logo, colour — comes from the
     * payment profile configured in the Snippe dashboard, not from this
     * request. Set that profile to BiasharaMax, or customers will see
     * whatever the account default is.
     */
    /**
     * Start a payment by whichever method the customer chose.
     *
     * Snippe exposes the two through different endpoints, and this is the
     * constraint that shapes the whole checkout:
     *
     *  - **Mobile money** — `/v1/payments` with `payment_type: mobile`.
     *    Sends a USSD prompt to the number given. No page, no redirect.
     *  - **Card** — `/v1/payments` with `payment_type: card`. Returns a
     *    `payment_url` to redirect the browser to.
     *
     * The hosted Payment Sessions API was tried first and abandoned: its
     * `allowed_methods` accepts only `mobile_money`, so a session can never
     * offer a card. Choosing the method has to happen on our own page.
     */
    public function charge(PaymentTransaction $transaction): array
    {
        $this->ensureConfigured();

        $payload = $this->buildPaymentPayload($transaction);

        // Idempotency-Key makes a retry safe: a network timeout followed by
        // a second attempt returns the original response rather than
        // charging twice.
        $result = $this->post('/v1/payments', $payload, $transaction->reference_number);

        $body = $result['decoded'];
        $successful = is_array($body) && $this->succeeded($body);
        $data = is_array($body) ? ($body['data'] ?? []) : [];

        $this->log('charge', $payload, is_array($body) ? $body : [], $successful, $result['status'], transaction: $transaction);

        return [
            'successful' => $successful,
            'external_id' => $data['reference'] ?? null,
            // Present for card, absent for mobile money. A null here is a
            // normal outcome for a USSD push, not a failure — the caller
            // decides what to do with each.
            'checkout_url' => $this->checkoutUrl(is_array($data) ? $data : []),
            'raw' => is_array($body) ? $body : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPaymentPayload(PaymentTransaction $transaction): array
    {
        $meta = $transaction->metadata ?? [];
        $type = ($meta['payment_type'] ?? 'mobile') === 'card' ? 'card' : 'mobile';
        $amount = (int) round((float) $transaction->amount);
        $currency = strtoupper((string) $transaction->currency);
        $phone = $this->normalisePhone((string) ($meta['phone'] ?? ''));

        $payload = [
            'payment_type' => $type,
            'reference' => $transaction->reference_number,
            'currency' => $currency,
            // Whole units. TZS has no subunit in practice and Snippe
            // rejects decimals here.
            'amount' => $amount,
            'phone' => $phone,
            'phone_number' => preg_replace('/\D+/', '', $phone) ?? '',
            'details' => [
                'amount' => $amount,
                'currency' => $currency,
            ],
            'callback_url' => $meta['redirect_url'] ?? url('/'),
            'webhook_url' => route('webhooks.snippe'),
            'metadata' => array_merge(
                // Scalars only — a nested value here is rejected for the
                // whole request.
                array_filter($meta, fn ($value) => is_scalar($value)),
                [
                    'reference' => $transaction->reference_number,
                    'transaction_id' => (string) $transaction->getKey(),
                ],
            ),
            'customer' => array_filter([
                'firstname' => $meta['first_name'] ?? null,
                'lastname' => $meta['last_name'] ?? null,
                'email' => $meta['email'] ?? null,
            ]),
        ];

        if ($type === 'card') {
            // Only the card flow leaves the site, so only it needs
            // somewhere to come back to.
            $payload['details']['redirect_url'] = $meta['redirect_url'] ?? url('/');
            $payload['details']['cancel_url'] = $meta['cancel_url'] ?? url('/');

            // Card requires a billing address. Mobile money does not, and
            // omitting these produced "customer.address is required;
            // customer.city is required; …" — five separate messages that
            // read as a broken integration rather than a missing address.
            //
            // Defaulted rather than demanded from the customer. A card
            // processor wants an address on file; a Zanzibar shop paying by
            // Visa should not be made to type a postcode that does not
            // exist there to renew a subscription. The business's own
            // address is used when we have one.
            $payload['customer'] += [
                'address' => $this->firstFilled($meta['address'] ?? null, 'N/A'),
                'city' => $this->firstFilled($meta['city'] ?? null, 'Dar es Salaam'),
                'state' => $this->firstFilled($meta['state'] ?? null, 'DSM'),
                'postcode' => $this->firstFilled($meta['postcode'] ?? null, '00000'),
                'country' => $this->firstFilled($meta['country'] ?? null, 'TZ'),
            ];
        }

        return $payload;
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

    private function firstFilled(?string $value, string $fallback): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : $fallback;
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
