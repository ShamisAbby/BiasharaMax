<?php

namespace App\Domain\Subscription\Services;

use App\Domain\Business\Models\Business;
use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Finance\Services\GatewayDriverResolver;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns "I want this plan" into a payment the customer can actually make.
 *
 * Sits between the subscription flow and the gateway so neither has to know
 * about the other: the renewal controller does not build API payloads, and
 * the driver does not know what a subscription is.
 *
 * Nothing here grants access. It creates a transaction and asks Snippe to
 * collect — the subscription only becomes active when the webhook confirms
 * the money arrived. That separation is the point: this method runs in the
 * customer's browser session and could be replayed, refreshed or abandoned
 * at any moment, none of which should be able to produce a paid account.
 */
class SubscriptionCheckoutService
{
    public function __construct(
        private readonly GatewayDriverResolver $drivers,
    ) {}

    /**
     * @return array{ok: bool, checkout_url: ?string, message: ?string}
     */
    public function start(
        Business $business,
        SubscriptionPlan $plan,
        Subscription $subscription,
        ?string $phone = null,
        string $method = 'mobile',
    ): array
    {
        $gateway = PaymentGateway::query()
            ->where('provider', PaymentGateway::PROVIDER_SNIPPE)
            ->first();

        // Said plainly rather than swallowed. An unconfigured gateway is an
        // operator problem, and the customer needs to know that clicking
        // again will not help.
        if ($gateway === null || ! $gateway->isUsable()) {
            return [
                'ok' => false,
                'checkout_url' => null,
                'message' => 'Online payment is not available right now. Please contact us to pay.',
            ];
        }

        // Pre-filled if we have it, but no longer required. The hosted
        // checkout collects the number and the network itself, so a
        // missing or wrong phone on file is no longer a dead end — which
        // is what it was when the driver pushed USSD to one number and a
        // placeholder value produced "unsupported mobile carrier".
        $phone = $phone
            ?: $business->phone
            ?: $business->owner?->phone;

        $transaction = PaymentTransaction::query()->create([
            'business_id' => $business->getKey(),
            'payment_gateway_id' => $gateway->getKey(),
            'payable_id' => $subscription->getKey(),
            'payable_type' => Subscription::class,
            'type' => PaymentTransaction::TYPE_SUBSCRIPTION_PAYMENT,
            // Prefixed and random rather than sequential: this reference
            // travels to Snippe and comes back on a public webhook, so it
            // must not be guessable from another customer's.
            'reference_number' => 'BM-'.strtoupper(Str::random(12)),
            'amount' => $plan->price ?? $plan->price_monthly,
            'currency' => 'TZS',
            'status' => PaymentTransaction::STATUS_PENDING,
            'payment_method' => $method === 'card' ? 'card' : 'mobile_money',
            // Everything the driver needs, and everything the webhook needs
            // to find its way back to this subscription without a lookup by
            // amount or timing.
            'metadata' => [
                'payment_type' => $method === 'card' ? 'card' : 'mobile',
                'phone' => $phone,
                'email' => $business->owner?->email,
                'first_name' => Str::before((string) $business->owner?->name, ' '),
                'last_name' => Str::after((string) $business->owner?->name, ' '),
                'business_id' => $business->getKey(),
                'subscription_id' => $subscription->getKey(),
                // Who asked for this. `created_by` cannot hold it — that
                // column is foreign-keyed to `platform_users` and this is a
                // vendor — so the attribution lives here instead of being
                // lost.
                'initiated_by_user_id' => $business->owner?->getKey(),
                'plan_id' => $plan->getKey(),
                // Shown on Snippe's checkout page so the customer can see
                // what they are paying for before choosing a network.
                'plan_name' => $plan->name,
                // Card payments need a billing address. Taken from the
                // business where it exists so the customer is not asked to
                // retype what they already gave us.
                'address' => $business->address,
                'city' => $business->city,
                'country' => $business->country,
                'description' => 'BiasharaMax — '.$plan->name,
                'redirect_url' => route('plan.expired'),
                'cancel_url' => route('plan.expired'),
            ],
        ]);

        $result = $this->drivers->resolve($gateway)->charge($transaction);

        if (! ($result['successful'] ?? false)) {
            $transaction->update(['status' => PaymentTransaction::STATUS_FAILED]);

            Log::warning('Snippe refused to start a subscription payment.', [
                'reference' => $transaction->reference_number,
                'raw' => $result['raw'] ?? null,
            ]);

            // Snippe's own words, not mine.
            //
            // The first version of this returned "We could not start the
            // payment" for everything. Snippe had actually said
            // "unsupported mobile carrier" — which tells the customer the
            // number is wrong and they can fix it — and that got discarded
            // in favour of a sentence that gives them nothing to act on.
            //
            // Prefixed so it is clear the message comes from the payment
            // provider rather than from us, and length-capped so a verbose
            // upstream error cannot become the whole page.
            $reason = is_string($result['raw']['message'] ?? null)
                ? Str::limit(trim($result['raw']['message']), 120)
                : null;

            return [
                'ok' => false,
                'checkout_url' => null,
                'message' => $reason !== null
                    ? "Payment could not start: {$reason}."
                    : 'We could not start the payment. Please try again, or contact us.',
            ];
        }

        $transaction->update([
            'status' => PaymentTransaction::STATUS_PROCESSING,
            'external_transaction_id' => $result['external_id'] ?? null,
        ]);

        return [
            'ok' => true,
            // Card returns a URL to redirect to. Mobile money does not —
            // the customer approves a prompt on the handset — so a null
            // here is a normal outcome for that method, not a failure.
            'checkout_url' => $result['checkout_url'] ?? null,
            'message' => ($result['checkout_url'] ?? null) === null
                ? 'Check your phone and enter your PIN to approve the payment.'
                : null,
        ];
    }
}
