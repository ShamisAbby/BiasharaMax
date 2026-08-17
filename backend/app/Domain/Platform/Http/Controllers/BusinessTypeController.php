<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Exceptions\BusinessTypeInUseException;
use App\Domain\Business\Models\BusinessType;
use App\Domain\Business\Services\BusinessTypeService;
use App\Domain\ModuleManagement\Models\Module;
use App\Domain\Platform\Http\Requests\BusinessTypeRequest;
use App\Domain\Platform\Http\Resources\BusinessTypeResource;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $types = BusinessType::query()
            ->withCount('businesses')
            ->with(['modules', 'subscriptionPlans'])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Platform/BusinessTypes/Index', [
            'businessTypes' => BusinessTypeResource::collection($types),
            'modules' => Module::query()->orderBy('name')->get(['id', 'name']),
            'plans' => SubscriptionPlan::query()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function store(BusinessTypeRequest $request, BusinessTypeService $service): RedirectResponse
    {
        $type = $service->create($request->validated());

        $this->syncAssociations($request, $type);

        return back()->with('status', 'business-type-created');
    }

    public function update(BusinessTypeRequest $request, BusinessType $businessType, BusinessTypeService $service): RedirectResponse
    {
        $service->update($businessType, $request->validated());

        $this->syncAssociations($request, $businessType);

        return back()->with('status', 'business-type-updated');
    }

    public function destroy(BusinessType $businessType, BusinessTypeService $service): RedirectResponse
    {
        try {
            $service->delete($businessType);
        } catch (BusinessTypeInUseException $e) {
            return back()->withErrors(['business_type' => $e->getMessage()]);
        }

        return back()->with('status', 'business-type-deleted');
    }

    public function archive(BusinessType $businessType, BusinessTypeService $service): RedirectResponse
    {
        $service->archive($businessType);

        return back()->with('status', 'business-type-archived');
    }

    public function activate(BusinessType $businessType, BusinessTypeService $service): RedirectResponse
    {
        $service->activate($businessType);

        return back()->with('status', 'business-type-activated');
    }

    public function deactivate(BusinessType $businessType, BusinessTypeService $service): RedirectResponse
    {
        $service->deactivate($businessType);

        return back()->with('status', 'business-type-deactivated');
    }

    public function clone(Request $request, BusinessType $businessType, BusinessTypeService $service): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $service->clone($businessType, $validated['name']);

        return back()->with('status', 'business-type-cloned');
    }

    private function syncAssociations(Request $request, BusinessType $type): void
    {
        if ($request->has('module_ids')) {
            $type->modules()->sync($request->input('module_ids', []));
        }

        if ($request->has('subscription_plan_ids')) {
            $type->subscriptionPlans()->sync($request->input('subscription_plan_ids', []));
        }
    }
}
