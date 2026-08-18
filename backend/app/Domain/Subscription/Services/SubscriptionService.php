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
     * A paid plan chosen but not yet paid for.
     *
     * Deliberately grants nothing. The business exists so the customer can
     * return to a real account after paying, but every gate treats this as
     * locked until a confirmed payment moves it to active — see
     * `Subscription::isLocked()`.
     *
     * `current_period_end` is left null on purpose: the term starts when
     * the money arrives, not when the form was submitted. Dating it from
     * signup would quietly shorten the plan by however long the customer
     * took to pay.
     */
    public function startPendingPayment(Business $business, SubscriptionPlan $plan): Subscription
    {
        // `expired`, not `trial`. A business that has never paid and never
        // trialled is not on a trial, and labelling it one put a third
        // wrong word in this column — the admin list would show "Trial"
        // for an account that had been offered nothing.
        $business->forceFill([
            'status' => Business::STATUS_EXPIRED,
            'trial_ends_at' => null,
        ])->save();

        return Subscription::query()->create([
            'business_id' => $business->getKey(),
            'subscription_plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_PENDING_PAYMENT,
            'billing_cycle' => null,
            'trial_ends_at' => null,
        ]);
    }

    /**
     * A returning customer choosing a plan after theirs ran out.
     *
     * Reuses the existing subscription row rather than creating a second
     * one, so the business keeps a single subscription history instead of
     * accumulating a row per lapse — which would make "when did they last
     * pay" a question about ordering rather than a lookup.
     *
     * Never grants a trial. That is the whole point of a separate method:
     * `startTrial()` is reachable only from registration, so no renewal
     * path can hand out another 30 free days by reusing it.
     */
    public function beginRenewal(Business $business, SubscriptionPlan $plan): Subscription
    {
        $subscription = $business->subscription;

        if ($subscription === null) {
            return $this->startPendingPayment($business, $plan);
        }

        $subscription->forceFill([
            'subscription_plan_id' => $plan->getKey(),
            'status' => Subscription::STATUS_PENDING_PAYMENT,
            // Cleared so a stale grace window from the previous term
            // cannot let the account back in before the renewal is paid.
            'grace_period_ends_at' => null,
            'trial_ends_at' => null,
        ])->save();

        return $subscription;
    }

    /**
     * Turn a paid-for plan on, once payment is confirmed.
     *
     * The term is measured from now rather than from signup, and its
     * length comes from the plan itself — a 3, 6 or 12-month purchase.
     */
    public function activateAfterPayment(Subscription $subscription): Subscription
    {
        $months = $subscription->plan?->duration_months ?? 12;

        $subscription->forceFill([
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_start' => Carbon::now(),
            'current_period_end' => Carbon::now()->addMonths($months),
            'grace_period_ends_at' => null,
        ])->save();

        $subscription->business?->forceFill([
            'status' => Business::STATUS_ACTIVE,
        ])->save();

        return $subscription;
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

        // `businesses.status` must never be set to `suspended` from here.
        //
        // Suspension means BiasharaMax made a decision about this account.
        // Every status this method can see is a *billing* outcome the
        // customer can fix by paying — so writing `suspended` for a
        // cancelled or lapsed subscription puts a word in the column that
        // did not happen, and every reader downstream believes it.
        //
        // That is exactly what went wrong: a cancelled subscription marked
        // the business suspended, the access gate read `suspended`, and the
        // owner was shown "This account is temporarily suspended" with a
        // support number instead of a renew button. The gate was right; the
        // data it was given was a lie told two layers earlier.
        //
        // A suspended subscription is left alone rather than mapped: it is
        // set by an admin acting on the subscription, and the business
        // status that goes with it is theirs to set too.
        $status = match ($subscription->status) {
            Subscription::STATUS_TRIALING => Business::STATUS_TRIAL,
            Subscription::STATUS_ACTIVE => Business::STATUS_ACTIVE,
            Subscription::STATUS_EXPIRED,
            Subscription::STATUS_CANCELED,
            Subscription::STATUS_PENDING_PAYMENT => Business::STATUS_EXPIRED,
            default => null,
        };

        // Nothing to say about this subscription state — leave the column
        // as it is rather than guessing `active`, which is how a suspended
        // business used to quietly un-suspend itself the next time any
        // subscription write touched it.
        if ($status === null) {
            return;
        }

        // A platform suspension outranks any billing state. Without this,
        // renewing or expiring a suspended business's subscription would
        // overwrite the suspension and hand access back.
        if ($business->status === Business::STATUS_SUSPENDED) {
            return;
        }

        $business->forceFill(['status' => $status])->save();
    }
}
