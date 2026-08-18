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
        SubscriptionPlan::query()->create($this->withDerivedPrices($request->validated()));

        return back()->with('status', 'plan-created');
    }

    public function update(SubscriptionPlanRequest $request, SubscriptionPlan $plan): RedirectResponse
    {
        $plan->update($this->withDerivedPrices($request->validated()));

        return back()->with('status', 'plan-updated');
    }

    /**
     * Keep the legacy price columns in step with the real one.
     *
     * `price` and `duration_months` are what the product is sold on;
     * `price_monthly`, `price_quarterly` and `price_yearly` predate that
     * and are still read by `priceFor()` and by older subscription rows.
     *
     * Derived rather than typed in. Asking an operator to keep four
     * numbers consistent by hand guarantees they eventually will not, and
     * the first sign would be an invoice nobody could explain.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withDerivedPrices(array $data): array
    {
        $months = max(1, (int) ($data['duration_months'] ?? 1));
        $price = (float) ($data['price'] ?? 0);

        $data['price_monthly'] = round($price / $months, 2);
        $data['price_quarterly'] = $months === 3 ? $price : 0;
        $data['price_yearly'] = $months === 12 ? $price : 0;

        return $data;
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
