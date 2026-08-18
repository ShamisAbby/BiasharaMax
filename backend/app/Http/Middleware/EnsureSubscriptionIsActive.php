<?php

namespace App\Http\Middleware;

use App\Domain\Subscription\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Locks a business out of the tenant app once its subscription is
 * genuinely expired (trial/period lapsed AND the 7-day grace period has
 * also passed) or has been suspended/canceled by a SuperAdmin. During the
 * grace period itself, access is still allowed — the dashboard banner is
 * what nudges the owner to renew.
 */
class EnsureSubscriptionIsActive
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('settings.subscription.show', 'suspended', 'plan.expired', 'subscription.renew', 'subscription.payment-status', 'logout', 'profile.*')) {
            return $next($request);
        }

        $business = $request->user()?->business;

        if ($business === null || $this->subscriptions->hasActiveAccess($business)) {
            return $next($request);
        }

        // XHR gets an answer, not a redirect to a web page.
        //
        // Everything under this gate includes JSON endpoints the app polls
        // in the background — `notifications.index` runs every 30 seconds
        // on every screen. Redirecting those meant axios followed the 302
        // and received the *HTML of the subscription page*, which the
        // caller then read as JSON. The notification bell stored
        // `data.notifications` (undefined), rendered `.length` on it, and
        // blanked the entire application the moment a subscription lapsed.
        //
        // A redirect is an answer to "which page should I show". It is not
        // an answer to "what are this user's notifications", and sending
        // one to a caller that asked for JSON guarantees the failure shows
        // up somewhere far away from here.
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => $business->isBlockedByPlatform()
                    ? 'This business is suspended.'
                    : 'This subscription is not active.',
                'reason' => $business->isBlockedByPlatform()
                    ? 'business_suspended'
                    : 'subscription_locked',
            ], 402); // Payment Required.
        }

        // Two different situations, two different destinations.
        //
        // A suspension is not a billing state. Sending a suspended owner
        // to the subscription page invites them to pay for something that
        // will not lift it — and that page renders the full authenticated
        // layout, which builds a sidebar, module list and notification
        // poller from data the account no longer has. That is what was
        // throwing before the page could paint.
        if ($business->isBlockedByPlatform()) {
            return redirect()->route('suspended');
        }

        // An expired plan is an invoice, not an accusation: its own page,
        // with the plans and a renew button, and no offer of another free
        // trial.
        return redirect()->route('plan.expired');
    }
}
