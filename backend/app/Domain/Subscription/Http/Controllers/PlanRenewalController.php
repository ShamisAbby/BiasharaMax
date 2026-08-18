<?php

namespace App\Domain\Subscription\Http\Controllers;

use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\SubscriptionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The expired-plan screen, and the renewal it offers.
 *
 * Kept apart from the suspension screen because the two situations read
 * completely differently to the person in front of them — one is an
 * invoice, the other an accusation — even though both end in "no access".
 */
class PlanRenewalController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function show(Request $request): Response|HttpResponse
    {
        $business = $request->user()?->business;

        // Only reachable when it applies, same rule as the suspension
        // page: telling a working account its plan has expired is both
        // alarming and false.
        if ($business === null || $this->subscriptions->hasActiveAccess($business)) {
            return redirect()->route('dashboard');
        }

        // A suspended business is not an expired one and must not be
        // offered a renew button — paying would not lift the suspension.
        if ($business->isBlockedByPlatform()) {
            return redirect()->route('suspended');
        }

        $subscription = $business->subscription;

        return Inertia::render('PlanExpired', [
            'businessName' => $business->name,
            'status' => session('status'),
            // Named so the page can say which plan is waiting rather than
            // just that something is. "6 Months selected, payment not yet
            // received" answers the question the customer actually has.
            'pendingPlanName' => $subscription?->status === \App\Domain\Subscription\Models\Subscription::STATUS_PENDING_PAYMENT
                ? $subscription->plan?->name
                : null,
            'expiredOn' => $subscription?->current_period_end?->format('j F Y')
                ?? $subscription?->trial_ends_at?->format('j F Y'),
            'plans' => SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderBy('duration_months')
                ->get(['id', 'name', 'slug', 'description', 'duration_months', 'price', 'price_monthly', 'features']),
        ]);
    }

    /**
     * Start a renewal.
     *
     * Deliberately never calls `startTrial()`. The trial is a one-time
     * introduction to the product; offering it again at renewal would let
     * anyone use the system indefinitely for free by letting the plan
     * lapse every 30 days. The renewal goes straight to a pending payment
     * and access returns only when that payment is confirmed.
     */
    public function renew(Request $request, SubscriptionPlan $plan): RedirectResponse
    {
        $business = $request->user()?->business;

        abort_if($business === null, 403);
        abort_unless($plan->is_active, 404);

        // Suspension is not something a customer can pay their way out of.
        if ($business->isBlockedByPlatform()) {
            return redirect()->route('suspended');
        }

        $this->subscriptions->beginRenewal($business, $plan);

        // Checkout lands here once the Snippe session exists. Until then
        // the renewal sits as an unpaid record, which is the honest state:
        // the customer has asked to renew and has not yet paid.
        return redirect()->route('plan.expired')
            ->with('status', 'renewal-started');
    }
}
