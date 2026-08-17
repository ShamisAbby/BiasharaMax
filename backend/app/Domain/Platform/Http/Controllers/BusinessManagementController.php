<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Models\Business;
use App\Domain\Platform\Http\Resources\BusinessSummaryResource;
use App\Domain\Subscription\Models\SubscriptionPlan;
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

    public function updateSubscription(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'status' => ['required', 'in:trialing,active,past_due,canceled,expired'],
        ]);

        $business->subscription()->updateOrCreate(
            ['business_id' => $business->id],
            [
                'subscription_plan_id' => $validated['subscription_plan_id'],
                'status' => $validated['status'],
            ],
        );

        return back()->with('status', 'subscription-updated');
    }
}
