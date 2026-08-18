<?php

namespace App\Domain\Subscription\Services;

use App\Domain\Finance\Models\PaymentTransaction;
use App\Domain\Finance\Services\GatewayDriverResolver;
use App\Domain\Subscription\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns a confirmed payment into an active subscription — from either
 * direction.
 *
 * There are two ways to learn that money arrived, and a system that takes
 * payments needs both:
 *
 *  1. **The webhook.** Fast, and the normal path.
 *  2. **Asking.** A webhook can be missed — a bad signature, an expired
 *     signing secret, a deploy mid-flight, a firewall. Snippe gives up
 *     after five attempts. Without a way to ask, a missed webhook means a
 *     customer who has paid and has no account, and the only repair is
 *     someone editing the database.
 *
 * Both routes converge here so they cannot drift apart in what they check
 * or what they grant — which is precisely the failure that has bitten this
 * codebase repeatedly.
 */
class SubscriptionPaymentReconciler
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly GatewayDriverResolver $drivers,
    ) {}

    /**
     * Apply a payment we have already been told about.
     *
     * @param  array<string, mixed>  $data  The gateway's payment payload.
     */
    public function settle(PaymentTransaction $transaction, array $data): bool
    {
        // A locked row, because the webhook and a customer clicking "check
        // payment" can arrive at the same instant. Without this both could
        // read `pending`, both activate, and the term would be granted
        // twice.
        return DB::transaction(function () use ($transaction, $data): bool {
            /** @var PaymentTransaction $fresh */
            $fresh = PaymentTransaction::query()
                ->whereKey($transaction->getKey())
                ->lockForUpdate()
                ->first();

            if ($fresh === null || $fresh->status === PaymentTransaction::STATUS_SUCCESSFUL) {
                return false;
            }

            $subscription = $fresh->payable instanceof Subscription
                ? $fresh->payable
                : Subscription::query()->whereKey($fresh->metadata['subscription_id'] ?? null)->first();

            if ($subscription === null) {
                Log::warning('A settled payment has no subscription to activate.', [
                    'reference' => $fresh->reference_number,
                ]);

                return false;
            }

            // Underpayment is refused rather than partially honoured.
            // Without it, paying the three-month price against the
            // twelve-month plan would buy a year.
            $paid = (float) (data_get($data, 'amount.value') ?? data_get($data, 'amount') ?? $fresh->amount);
            $expected = (float) $fresh->amount;

            if ($expected > 0 && round($paid) < round($expected)) {
                Log::warning('Payment was short of the amount charged; not activating.', [
                    'reference' => $fresh->reference_number,
                    'paid' => $paid,
                    'expected' => $expected,
                ]);

                return false;
            }

            $fresh->update([
                'status' => PaymentTransaction::STATUS_SUCCESSFUL,
                'paid_at' => now(),
            ]);

            $this->subscriptions->activateAfterPayment($subscription);

            return true;
        });
    }

    /**
     * Ask the gateway whether a pending payment has completed.
     *
     * This is what recovers a webhook that never arrived. It is safe to
     * call repeatedly and from the customer's own screen: it grants nothing
     * on its own, it only believes the gateway.
     */
    public function refresh(PaymentTransaction $transaction): bool
    {
        if ($transaction->status === PaymentTransaction::STATUS_SUCCESSFUL) {
            return true;
        }

        $gateway = $transaction->gateway;
        $externalId = $transaction->external_transaction_id;

        if ($gateway === null || blank($externalId)) {
            return false;
        }

        $result = $this->drivers->resolve($gateway)->verify($externalId);

        if (! ($result['successful'] ?? false)) {
            return false;
        }

        return $this->settle($transaction, $result['raw']['data'] ?? []);
    }
}
