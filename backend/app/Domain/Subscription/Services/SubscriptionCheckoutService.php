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
    public function start(Business $business, SubscriptionPlan $plan, Subscription $subscription, ?string $phone = null): array
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

        $phone = $phone
            ?: $business->phone
            ?: $business->owner?->phone;

        if (blank($phone)) {
            return [
                'ok' => false,
                'checkout_url' => null,
                'message' => 'Add a phone number to your business profile before paying by mobile money.',
            ];
        }

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
            'payment_method' => 'mobile_money',
            // Everything the driver needs, and everything the webhook needs
            // to find its way back to this subscription without a lookup by
            // amount or timing.
            'metadata' => [
                'payment_type' => 'mobile',
                'phone' => $phone,
                'email' => $business->owner?->email,
                'first_name' => Str::before((string) $business->owner?->name, ' '),
                'last_name' => Str::after((string) $business->owner?->name, ' '),
                'business_id' => $business->getKey(),
                'subscription_id' => $subscription->getKey(),
                'plan_id' => $plan->getKey(),
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

            return [
                'ok' => false,
                'checkout_url' => null,
                'message' => 'We could not start the payment. Please try again, or contact us.',
            ];
        }

        $transaction->update([
            'status' => PaymentTransaction::STATUS_PROCESSING,
            'external_transaction_id' => $result['external_id'] ?? null,
        ]);

        return [
            'ok' => true,
            // Null for mobile money: the customer approves a USSD prompt on
            // the handset instead of visiting a page. A null here is a
            // normal outcome, not a failure.
            'checkout_url' => $result['checkout_url'] ?? null,
            'message' => $result['checkout_url'] ?? null
                ? null
                : 'Check your phone and approve the payment prompt. Access returns as soon as it clears.',
        ];
    }
}
