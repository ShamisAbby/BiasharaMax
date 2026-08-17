<?php

namespace App\Domain\Subscription\Services;

use App\Domain\Business\Models\Business;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Models\SubscriptionTransaction;
use App\Domain\Subscription\Notifications\SubscriptionExpiredNotification;
use App\Domain\Subscription\Notifications\SubscriptionRenewedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Start the 30-day free trial for a newly registered business on the
     * plan it selected at sign-up. The business itself is also flagged as
     * trialing so dashboard banners and access checks have a single source
     * of truth.
     */
    public function startTrial(Business $business, SubscriptionPlan $plan): Subscription
    {
        $trialEndsAt = Carbon::now()->addDays($plan->trial_days);

        $business->forceFill([
            'status' => Business::STATUS_TRIAL,
            'trial_ends_at' => $trialEndsAt,
        ])->save();

        return Subscription::query()->create([
            'business_id' => $business->getKey(),
            'subscription_plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_TRIALING,
            'billing_cycle' => null,
            'trial_ends_at' => $trialEndsAt,
        ]);
    }

    /**
     * Whether the business should currently be let in — true during an
     * active billing/trial period AND during the 7-day grace window after
     * it lapses, false once the subscription is genuinely locked.
     */
    public function hasActiveAccess(Business $business): bool
    {
        // Checked before the subscription, and independently of it. A
        // SuperAdmin suspension is a decision about the account, not about
        // billing, so a business with a perfectly valid paid subscription
        // must still be locked out when the platform has suspended it.
        //
        // The relationship between the two already ran one way —
        // `syncBusinessStatus()` below marks a business suspended when its
        // subscription is suspended — but nothing ran the other way, so
        // suspending the business directly changed a column and nothing
        // else.
        if ($business->isBlockedByPlatform()) {
            return false;
        }

        $subscription = $business->subscription;

        if ($subscription === null) {
            return false;
        }

        return ! $subscription->isLocked();
    }

    /**
     * Activate a paid subscription immediately — used when a business
     * registers using a pre-purchased registration code instead of a trial.
     */
    public function startSubscription(
        Business $business,
        SubscriptionPlan $plan,
        string $billingCycle = 'yearly',
        int $durationMonths = 12,
    ): Subscription {
        $periodEnd = Carbon::now()->addMonths($durationMonths);

        $business->forceFill(['status' => Business::STATUS_ACTIVE])->save();

        return Subscription::query()->create([
            'business_id'          => $business->getKey(),
            'subscription_plan_id' => $plan->getKey(),
            'status'               => Subscription::STATUS_ACTIVE,
            'billing_cycle'        => $billingCycle,
            'current_period_start' => Carbon::now(),
            'current_period_end'   => $periodEnd,
            'trial_ends_at'        => null,
        ]);
    }

    /**
     * Put a business on a plan with a real billing period — used for both
     * the first paid assignment and upgrades/downgrades. The billing cycle
     * defaults to whatever the subscription already had.
     */
    public function changePlan(
        Subscription $subscription,
        SubscriptionPlan $plan,
        ?string $billingCycle = null,
        ?float $customPrice = null,
        ?array $customLimits = null,
    ): Subscription {
        $billingCycle ??= $subscription->billing_cycle ?? 'monthly';

        return DB::transaction(function () use ($subscription, $plan, $billingCycle, $customPrice, $customLimits) {
            $subscription->forceFill([
                'subscription_plan_id' => $plan->getKey(),
                'billing_cycle' => $billingCycle,
                'status' => Subscription::STATUS_ACTIVE,
                'current_period_start' => Carbon::now(),
                'current_period_end' => $this->periodEnd($billingCycle),
                'trial_ends_at' => null,
                'grace_period_ends_at' => null,
                'canceled_at' => null,
                'custom_price' => $customPrice,
                'custom_limits' => $customLimits,
            ])->save();

            $this->syncBusinessStatus($subscription);

            return $subscription->refresh();
        });
    }

    /**
     * Renew the current period without recording a payment — e.g. a
     * courtesy extension. Use renewWithPayment() when money actually
     * changed hands.
     */
    public function renew(Subscription $subscription): Subscription
    {
        $billingCycle = $subscription->billing_cycle ?? 'monthly';

        $subscription->forceFill([
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_start' => Carbon::now(),
            'current_period_end' => $this->periodEnd($billingCycle),
            'grace_period_ends_at' => null,
            'canceled_at' => null,
        ])->save();

        $this->syncBusinessStatus($subscription);

        return $subscription->refresh();
    }

    /**
     * @param  array{amount: float, currency?: string, payment_method?: ?string, notes?: ?string, recorded_by?: ?string, paid_at?: ?Carbon}  $payment
     */
    public function renewWithPayment(Subscription $subscription, array $payment): SubscriptionTransaction
    {
        return DB::transaction(function () use ($subscription, $payment) {
            $this->renew($subscription);

            $transaction = SubscriptionTransaction::query()->create([
                'business_id' => $subscription->business_id,
                'subscription_id' => $subscription->id,
                'amount' => $payment['amount'],
                'currency' => $payment['currency'] ?? 'TZS',
                'billing_cycle' => $subscription->billing_cycle ?? 'monthly',
                'status' => SubscriptionTransaction::STATUS_PAID,
                'payment_method' => $payment['payment_method'] ?? null,
                'notes' => $payment['notes'] ?? null,
                'recorded_by' => $payment['recorded_by'] ?? null,
                'paid_at' => $payment['paid_at'] ?? Carbon::now(),
            ]);

            $subscription->business?->owner?->notify(new SubscriptionRenewedNotification($subscription));

            return $transaction;
        });
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->forceFill([
            'status' => Subscription::STATUS_CANCELED,
            'canceled_at' => Carbon::now(),
            'auto_renew' => false,
        ])->save();

        $this->syncBusinessStatus($subscription);

        return $subscription->refresh();
    }

    public function suspend(Subscription $subscription): Subscription
    {
        $subscription->forceFill(['status' => Subscription::STATUS_SUSPENDED])->save();

        $this->syncBusinessStatus($subscription);

        return $subscription->refresh();
    }

    /**
     * Restore a suspended or canceled subscription back to active (or
     * trialing, if the original trial window hasn't actually passed yet).
     */
    public function restore(Subscription $subscription): Subscription
    {
        $stillTrialing = $subscription->trial_ends_at?->isFuture() ?? false;

        $subscription->forceFill([
            'status' => $stillTrialing ? Subscription::STATUS_TRIALING : Subscription::STATUS_ACTIVE,
            'canceled_at' => null,
            'grace_period_ends_at' => null,
        ])->save();

        $this->syncBusinessStatus($subscription);

        return $subscription->refresh();
    }

    public function extendTrial(Subscription $subscription, int $days): Subscription
    {
        $base = $subscription->trial_ends_at?->isFuture()
            ? $subscription->trial_ends_at
            : Carbon::now();

        $subscription->forceFill([
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => $base->copy()->addDays($days),
            'grace_period_ends_at' => null,
        ])->save();

        $this->syncBusinessStatus($subscription);

        return $subscription->refresh();
    }

    /**
     * Move a lapsed subscription into its grace period rather than
     * locking the business out immediately.
     */
    public function expire(Subscription $subscription): Subscription
    {
        $subscription->forceFill(['status' => Subscription::STATUS_EXPIRED])->save();
        $subscription->startGracePeriod();
        $subscription->save();

        $this->syncBusinessStatus($subscription);

        $subscription->business?->owner?->notify(new SubscriptionExpiredNotification($subscription));

        return $subscription->refresh();
    }

    private function periodEnd(string $billingCycle): ?Carbon
    {
        return match ($billingCycle) {
            'monthly' => Carbon::now()->addMonth(),
            'quarterly' => Carbon::now()->addMonths(3),
            'yearly' => Carbon::now()->addYear(),
            'lifetime' => null,
            default => throw new \InvalidArgumentException("Unknown billing cycle [{$billingCycle}]."),
        };
    }

    private function syncBusinessStatus(Subscription $subscription): void
    {
        $business = $subscription->business;

        if ($business === null) {
            return;
        }

        $status = match (true) {
            $subscription->status === Subscription::STATUS_TRIALING => Business::STATUS_TRIAL,
            $subscription->status === Subscription::STATUS_ACTIVE => Business::STATUS_ACTIVE,
            in_array($subscription->status, [Subscription::STATUS_SUSPENDED, Subscription::STATUS_CANCELED], true) => Business::STATUS_SUSPENDED,
            $subscription->status === Subscription::STATUS_EXPIRED => Business::STATUS_EXPIRED,
            default => Business::STATUS_ACTIVE,
        };

        $business->forceFill(['status' => $status])->save();
    }
}
