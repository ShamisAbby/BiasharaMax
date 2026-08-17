<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domain\Business\Models\BusinessType;
use App\Domain\ModuleManagement\Exceptions\ModuleException;
use App\Domain\ModuleManagement\Models\Module;
use App\Domain\ModuleManagement\Services\ModuleService;
use App\Domain\Platform\Http\Requests\ModuleRequest;
use App\Domain\Platform\Http\Resources\ModuleResource;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function index(Request $request): Response
    {
        $modules = Module::query()
            ->withCount('businesses')
            ->with(['dependencies', 'subscriptionPlans', 'businessTypes'])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Platform/Modules/Index', [
            'modules' => ModuleResource::collection($modules),
            'plans' => SubscriptionPlan::query()->orderBy('sort_order')->get(['id', 'name']),
            'businessTypes' => BusinessType::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(ModuleRequest $request, ModuleService $service): RedirectResponse
    {
        $module = $service->create($request->safe()->except('dependency_ids'));

        if ($request->has('dependency_ids')) {
            $service->setDependencies($module, $request->input('dependency_ids', []));
        }

        return back()->with('status', 'module-created');
    }

    public function update(ModuleRequest $request, Module $module, ModuleService $service): RedirectResponse
    {
        $service->update($module, $request->safe()->except('dependency_ids'));

        if ($request->has('dependency_ids')) {
            $service->setDependencies($module, $request->input('dependency_ids', []));
        }

        return back()->with('status', 'module-updated');
    }

    public function destroy(Module $module, ModuleService $service): RedirectResponse
    {
        try {
            $service->delete($module);
        } catch (ModuleException $e) {
            return back()->withErrors(['module' => $e->getMessage()]);
        }

        return back()->with('status', 'module-deleted');
    }

    public function enable(Module $module, ModuleService $service): RedirectResponse
    {
        $service->enable($module);

        return back()->with('status', 'module-enabled');
    }

    public function disable(Module $module, ModuleService $service): RedirectResponse
    {
        $service->disable($module);

        return back()->with('status', 'module-disabled');
    }

    public function updateVersion(Request $request, Module $module, ModuleService $service): RedirectResponse
    {
        $validated = $request->validate([
            'version' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ]);

        $service->updateVersion($module, $validated['version'], $validated['notes'] ?? null, $request->user()->id);

        return back()->with('status', 'module-version-updated');
    }

    public function assignToPlans(Request $request, Module $module, ModuleService $service): RedirectResponse
    {
        $validated = $request->validate([
            'plan_ids' => ['array'],
            'plan_ids.*' => ['uuid', 'exists:subscription_plans,id'],
        ]);

        $service->assignToPlans($module, $validated['plan_ids'] ?? []);

        return back()->with('status', 'module-plans-updated');
    }

    public function assignToBusinessTypes(Request $request, Module $module, ModuleService $service): RedirectResponse
    {
        $validated = $request->validate([
            'business_type_ids' => ['array'],
            'business_type_ids.*' => ['uuid', 'exists:business_types,id'],
        ]);

        $service->assignToBusinessTypes($module, $validated['business_type_ids'] ?? []);

        return back()->with('status', 'module-business-types-updated');
    }
}
