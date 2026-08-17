<?php

namespace App\Domain\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Services\DashboardAggregatorService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the business owner/employee dashboard.
     *
     * Every widget here is real and permission-gated, assembled in one
     * pass by DashboardAggregatorService: Inventory, Sales, Accounting
     * (Cash/Bank/AR/AP) and CRM (customers/loyalty) each come from their
     * own module's dashboard service. Website analytics still has no
     * real data source, so it isn't shown rather than being faked.
     */
    public function __invoke(Request $request, DashboardAggregatorService $aggregator): Response
    {
        // `role` is the legacy single-role relation; the union across all
        // assigned roles is what authorization actually uses.
        $user = $request->user()->loadMissing(['business.subscription.plan']);
        $business = $user->business;
        $subscription = $business?->subscription;

        return Inertia::render('Dashboard', [
            // `withoutRelations()` because `businessType` serialises to
            // `business_type` — the same key as the string column the page
            // renders. Anything that loads that relation between here and
            // the response would replace the string with an object and
            // crash the page. Sending the attributes only makes the shape
            // of this prop independent of what else touched the model.
            'business' => $business?->withoutRelations(),
            'subscription' => $subscription?->loadMissing('plan'),
            'trialEndsAt' => $business?->trial_ends_at,
            'employeeCount' => $business?->users()->count() ?? 0,
            ...$aggregator->build($user, $business),
        ]);
    }
}
