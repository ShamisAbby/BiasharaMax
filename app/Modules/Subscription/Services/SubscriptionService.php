<?php

namespace App\Modules\Subscription\Services;

use App\Modules\Business\Models\Business;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Models\SubscriptionPlan;
use Illuminate\Support\Carbon;

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

    public function hasActiveAccess(Business $business): bool
    {
        $subscription = $business->subscription;

        if ($subscription === null) {
            return false;
        }

        if ($subscription->status === Subscription::STATUS_TRIALING) {
            return $subscription->trial_ends_at?->isFuture() ?? false;
        }

        return $subscription->status === Subscription::STATUS_ACTIVE
            && ($subscription->current_period_end?->isFuture() ?? false);
    }
}
