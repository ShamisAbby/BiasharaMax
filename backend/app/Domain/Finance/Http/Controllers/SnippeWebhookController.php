<?php

namespace App\Domain\Finance\Http\Controllers;

use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Finance\Support\SnippeSignatureVerifier;
use App\Domain\Subscription\Services\SubscriptionPaymentReconciler;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Where Snippe tells us a payment landed.
 *
 * This endpoint is unauthenticated and publicly reachable — it has to be —
 * so the signature is the only thing standing between it and anyone who
 * wants a free subscription. Every early return below is deliberate.
 *
 * It also answers quickly and always with a 2xx once the signature checks
 * out. Snippe retries up to five times on a non-2xx, so returning 500 for
 * an internal problem earns four more copies of the same failure rather
 * than fixing it.
 */
class SnippeWebhookController extends Controller
{
    public function __construct(
        private readonly SubscriptionPaymentReconciler $reconciler,
    ) {}

    public function __invoke(Request $request): Response
    {
        // The RAW body, not the parsed-and-re-encoded one. Re-serialising
        // JSON can reorder keys or change whitespace, and the signature is
        // over the exact bytes Snippe sent — so a re-encode turns every
        // legitimate webhook into an invalid one.
        $rawBody = $request->getContent();

        $gateway = PaymentGateway::query()
            ->where('provider', PaymentGateway::PROVIDER_SNIPPE)
            ->first();

        if ($gateway === null) {
            Log::warning('Snippe webhook received but no Snippe gateway is configured.');

            // 200: there is nothing Snippe can do about our configuration,
            // and retrying will not fix it.
            return response('OK', 200);
        }

        $secret = (string) ($gateway->webhook_secret ?? '');

        if (! SnippeSignatureVerifier::isValid($request, $rawBody, $secret)) {
            Log::warning('Rejected a Snippe webhook with an invalid signature.', [
                'ip' => $request->ip(),
            ]);

            return response('Invalid signature', 400);
        }

        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return response('Invalid payload', 400);
        }

        $eventId = (string) ($payload['id'] ?? '');
        $data = $payload['data'] ?? $payload;
        $eventType = (string) ($payload['type'] ?? $payload['event'] ?? $request->header('X-Webhook-Event', ''));

        // Snippe may deliver the same event more than once, by design. A
        // second delivery of `payment.completed` must not extend the
        // subscription a second time.
        if ($eventId !== '' && ! Cache::add('snippe:event:'.$eventId, true, now()->addDays(3))) {
            return response('OK', 200);
        }

        $reference = data_get($data, 'metadata.reference')
            ?? data_get($data, 'external_reference')
            ?? data_get($data, 'reference');

        if (! is_string($reference) || $reference === '') {
            Log::warning('Snippe webhook carried no reference to match.', ['event' => $eventType]);

            return response('OK', 200);
        }

        $completed = in_array($eventType, ['payment.completed', 'payment.successful', 'payment.paid'], true)
            || in_array(strtolower((string) data_get($data, 'status')), ['completed', 'successful', 'paid'], true);

        if (! $completed) {
            // Failures and expiries are recorded, not acted on. The
            // subscription is already unpaid and already locked; there is
            // nothing to take away.
            Log::info('Snippe payment did not complete.', [
                'event' => $eventType,
                'reference' => $reference,
            ]);

            return response('OK', 200);
        }

        // Resolved through our own transaction, not through whatever keys
        // Snippe happens to echo back.
        //
        // The first version looked for `metadata.business_id` and
        // `metadata.subscription_id`, which the driver never sent — so a
        // valid webhook found nothing and returned 200 while the customer's
        // money was gone. Our reference is on the payment either way, and
        // the transaction knows which subscription it belongs to.
        $transaction = PaymentTransaction::query()
            ->where('reference_number', $reference)
            ->orWhere('external_transaction_id', data_get($data, 'reference'))
            ->orWhereKey(data_get($data, 'metadata.transaction_id'))
            ->latest('created_at')
            ->first();

        if ($transaction === null) {
            Log::warning('Snippe reported a payment we have no transaction for.', [
                'reference' => $reference,
            ]);

            return response('OK', 200);
        }

        $activated = $this->reconciler->settle($transaction, $data);

        Log::info('Snippe webhook processed.', [
            'reference' => $transaction->reference_number,
            'activated' => $activated,
        ]);

        return response('OK', 200);
    }
}
