<?php

namespace App\Domain\Finance\Drivers;

use App\Domain\Finance\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;

/**
 * Snippe — mobile money and cards for Tanzania.
 *
 * Payments run through Snippe's hosted checkout (Payment Sessions), so the
 * customer chooses their own network — M-Pesa, Airtel Money, Mixx by Yas,
 * Halotel — on a Snippe-branded page and is returned here afterwards.
 *
 * An earlier version posted directly to `/v1/payments`, which pushes a USSD
 * prompt to a single number and never navigates the browser anywhere. That
 * shape works, but it left a paying customer staring at an unchanged screen
 * with no way to tell success from failure.
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
    public function charge(PaymentTransaction $transaction): array
    {
        $this->ensureConfigured();

        $payload = $this->buildSessionPayload($transaction);

        $result = $this->post('/api/v1/sessions', $payload, $transaction->reference_number);

        $body = $result['decoded'];
        $data = is_array($body) ? ($body['data'] ?? []) : [];
        $checkoutUrl = $this->checkoutUrl(is_array($data) ? $data : []);

        // A session is only useful if it produced somewhere to send the
        // customer. A 201 with no `checkout_url` is a failure however
        // cheerful the status code, so success is judged on the URL rather
        // than on the response code alone.
        $successful = $checkoutUrl !== null;

        $this->log('charge', $payload, is_array($body) ? $body : [], $successful, $result['status'], transaction: $transaction);

        return [
            'successful' => $successful,
            // The session reference, which `verify()` reads back and the
            // webhook echoes as `session_reference`.
            'external_id' => $data['reference'] ?? null,
            'checkout_url' => $checkoutUrl,
            'raw' => is_array($body) ? $body : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSessionPayload(PaymentTransaction $transaction): array
    {
        $meta = $transaction->metadata ?? [];

        $name = trim(($meta['first_name'] ?? '').' '.($meta['last_name'] ?? ''));

        return [
            // Whole units. Snippe's minimum is 500 TZS.
            'amount' => (int) round((float) $transaction->amount),
            'currency' => strtoupper((string) $transaction->currency),
            'allowed_methods' => ['mobile_money'],
            'customer' => array_filter([
                'name' => $name !== '' ? $name : null,
                'phone' => $this->normalisePhone((string) ($meta['phone'] ?? '')) ?: null,
                'email' => $meta['email'] ?? null,
            ]),
            // Where Snippe sends the browser once the customer is done.
            'redirect_url' => $meta['redirect_url'] ?? url('/'),
            'webhook_url' => route('webhooks.snippe'),
            'description' => $meta['description'] ?? 'BiasharaMax subscription',
            // Shown on the checkout page so the customer can see what they
            // are paying for before they choose a network.
            'line_items' => [[
                'name' => $meta['plan_name'] ?? 'BiasharaMax subscription',
                'quantity' => 1,
                'unit_price' => (int) round((float) $transaction->amount),
            ]],
            'display' => [
                'show_line_items' => true,
                'show_description' => true,
                'button_text' => 'Pay now',
            ],
            'metadata' => array_merge(
                // Scalars only: Snippe rejects nested values and caps
                // metadata at 50 keys.
                array_filter($meta, fn ($value) => is_scalar($value)),
                [
                    'reference' => $transaction->reference_number,
                    'transaction_id' => (string) $transaction->getKey(),
                ],
            ),
            // An hour. Long enough for someone to find their phone, short
            // enough that an abandoned session does not sit open all week.
            'expires_in' => 3600,
        ];
    }

    public function verify(string $externalTransactionId): array
    {
        $this->ensureConfigured();

        // Sessions, matching `charge()`. Asking the payments endpoint for
        // a session reference returns 404, which would read as "not paid"
        // and quietly strand a customer who had paid.
        $response = Http::withHeaders($this->headers())
            ->get(self::BASE_URL.'/api/v1/sessions/'.$externalTransactionId);

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
