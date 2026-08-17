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
        if ($request->routeIs('settings.subscription.show', 'suspended', 'logout', 'profile.*')) {
            return $next($request);
        }

        $business = $request->user()?->business;

        if ($business === null || $this->subscriptions->hasActiveAccess($business)) {
            return $next($request);
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

        return redirect()->route('settings.subscription.show')
            ->with('status', 'subscription-locked');
    }
}
