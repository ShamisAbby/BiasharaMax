<?php

namespace App\Domain\Subscription\Http\Controllers;

use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\SubscriptionCheckoutService;
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
        private readonly SubscriptionCheckoutService $checkout,
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
            // Whatever the gateway actually said. The page used to state
            // "online payment is not switched on yet" unconditionally,
            // which stayed on screen after Snippe was switched on — an
            // interface contradicting the system it describes.
            'checkoutMessage' => session('checkout_message'),
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

        $subscription = $this->subscriptions->beginRenewal($business, $plan);

        $result = $this->checkout->start($business, $plan, $subscription, $request->input('phone'));

        // A hosted page exists only for card payments. Mobile money sends a
        // USSD prompt to the handset instead, so there is nowhere to send
        // the browser — it stays here and waits for the webhook.
        if ($result['ok'] && $result['checkout_url'] !== null) {
            return redirect()->away($result['checkout_url']);
        }

        return redirect()->route('plan.expired')
            ->with('status', $result['ok'] ? 'payment-pending' : 'payment-failed')
            ->with('checkout_message', $result['message']);
    }
}
