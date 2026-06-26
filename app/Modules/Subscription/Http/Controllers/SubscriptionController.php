<?php

namespace App\Modules\Subscription\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Subscription\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    /**
     * Display the business's current subscription and the available plans.
     *
     * Sprint 1 ships visibility into the trial/plan status; upgrading,
     * downgrading and billing-cycle changes are handled by the Subscription
     * module's billing sprint once payment processing is integrated.
     */
    public function show(Request $request): Response
    {
        $this->authorize('view', $request->user()->business);

        $business = $request->user()->business->loadMissing('subscription.plan');

        return Inertia::render('Subscription/Show', [
            'subscription' => $business->subscription,
            'plans' => SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }
}
