<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Platform\Http\Requests\SubscriptionPlanRequest;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionPlanController extends Controller
{
    public function index(Request $request): Response
    {
        $plans = SubscriptionPlan::query()
            ->withCount('subscriptions')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Platform/Subscriptions/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    public function store(SubscriptionPlanRequest $request): RedirectResponse
    {
        SubscriptionPlan::query()->create($request->validated());

        return back()->with('status', 'plan-created');
    }

    public function update(SubscriptionPlanRequest $request, SubscriptionPlan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        return back()->with('status', 'plan-updated');
    }

    public function destroy(SubscriptionPlan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            return back()->withErrors(['plan' => 'This plan has subscribers and cannot be deleted. Deactivate it instead.']);
        }

        $plan->delete();

        return back()->with('status', 'plan-deleted');
    }

    public function activate(SubscriptionPlan $plan): RedirectResponse
    {
        $plan->update(['is_active' => true]);

        return back()->with('status', 'plan-activated');
    }

    public function deactivate(SubscriptionPlan $plan): RedirectResponse
    {
        $plan->update(['is_active' => false]);

        return back()->with('status', 'plan-deactivated');
    }
}
