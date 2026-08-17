<?php

namespace App\Http\Middleware;

use App\Domain\Business\Models\Business;
use App\Domain\Website\Models\BusinessWebsite;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Takes the whole public surface of a business offline once its owner has
 * explicitly unpublished the site, replacing it with an explanatory page
 * rather than a bare error.
 *
 * The distinction this enforces:
 *
 *  - NEVER published (`published_at` is null) — the owner simply hasn't
 *    got to it yet, so the business type's template still shows and a new
 *    business is never a blank page on day one.
 *  - Published and then WITHDRAWN (`published_at` set, status back to
 *    draft) — the owner deliberately took it down, so it must actually
 *    disappear. `unpublish()` leaves `published_at` in place, which is
 *    what makes the two cases distinguishable.
 *
 * Applied as middleware rather than checked in one controller because the
 * storefront, cart, checkout and blog all hang off the same
 * `site/{business:slug}` prefix — a check in the homepage controller alone
 * would leave every one of those still reachable by direct URL.
 */
class EnsureBusinessWebsiteIsNotWithdrawn
{
    public function handle(Request $request, Closure $next): Response
    {
        $business = $request->route('business');

        if ($business instanceof Business) {
            $site = BusinessWebsite::query()
                ->where('business_id', $business->getKey())
                ->first();

            if ($site
                && $site->status === BusinessWebsite::STATUS_DRAFT
                && $site->published_at !== null
            ) {
                // 503, not 404. The business exists and is trading — only
                // its site is down — so "not found" would be both untrue
                // and bad for the site's search ranking. 503 is the
                // "temporarily unavailable, try later" status, and
                // crawlers treat it as a reason to come back rather than
                // to drop the page.
                return Inertia::render('PublicWebsite/Unavailable', [
                    'business' => [
                        'name' => $business->name,
                        // Kept visible: someone who followed a saved link
                        // or a business card still needs a way to reach
                        // them while the site is down.
                        'email' => $business->email,
                        'phone' => $business->phone,
                        'address' => $business->address,
                        'city' => $business->city,
                    ],
                ])->toResponse($request)->setStatusCode(503);
            }
        }

        return $next($request);
    }
}
