<?php

namespace App\Modules\Business\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the business owner/employee dashboard.
     *
     * Sprint 1 surfaces business and subscription health only; sales,
     * inventory and financial KPIs are added once those modules exist.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user()->loadMissing(['business.subscription.plan', 'role']);
        $business = $user->business;
        $subscription = $business?->subscription;

        return Inertia::render('Dashboard', [
            'business' => $business,
            'subscription' => $subscription?->loadMissing('plan'),
            'trialDaysRemaining' => $business?->trial_ends_at
                ? max(0, now()->diffInDays($business->trial_ends_at, false))
                : null,
            'employeeCount' => $business?->users()->count() ?? 0,
        ]);
    }
}
