<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Models\Business;
use App\Domain\Platform\Http\Resources\BusinessSummaryResource;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $businesses = Business::query()
            ->with(['owner', 'subscription.plan'])
            ->when($request->string('search')->trim()->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search')->trim()->value();
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery->where('email', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Platform/Businesses/Index', [
            'businesses' => BusinessSummaryResource::collection($businesses),
            'filters' => $request->only(['search', 'status']),
            'plans' => SubscriptionPlan::query()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function suspend(Business $business): RedirectResponse
    {
        $business->update(['status' => Business::STATUS_SUSPENDED]);

        return back()->with('status', 'business-suspended');
    }

    public function activate(Business $business): RedirectResponse
    {
        $business->update(['status' => Business::STATUS_ACTIVE]);

        return back()->with('status', 'business-activated');
    }

    public function updateSubscription(
        Request $request,
        Business $business,
        SubscriptionService $subscriptions,
    ): RedirectResponse {
        $validated = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            // `pending_payment` and `suspended` were missing, so a
            // subscription in either state could not be edited back out of
            // it from here at all.
            'status' => ['required', 'in:pending_payment,trialing,active,past_due,canceled,expired,suspended'],
        ]);

        $subscription = $business->subscription()->updateOrCreate(
            ['business_id' => $business->id],
            [
                'subscription_plan_id' => $validated['subscription_plan_id'],
                'status' => $validated['status'],
            ],
        );

        // Setting a subscription active must give the business a term to be
        // active for. Without dates, `isLocked()` reads a null
        // `current_period_end`, the account stays shut, and the admin sees
        // "active" next to a customer who still cannot sign in.
        if ($validated['status'] === Subscription::STATUS_ACTIVE && $subscription->current_period_end === null) {
            $subscriptions->activateAfterPayment($subscription);
        }

        // The line that was missing. `businesses.status` is derived from
        // the subscription and is what the businesses table renders, so
        // writing one without the other made this form appear to do
        // nothing.
        $subscriptions->syncBusinessStatus($subscription->refresh());

        return back()->with('status', 'subscription-updated');
    }
}
