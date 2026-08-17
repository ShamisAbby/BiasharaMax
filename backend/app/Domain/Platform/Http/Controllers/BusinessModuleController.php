<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Domain\Business\Models\Business;
use App\Domain\ModuleManagement\Models\BusinessModule;
use App\Domain\ModuleManagement\Models\Module;
use App\Domain\ModuleManagement\Services\BusinessModuleResolver;
use App\Domain\ModuleManagement\Support\DashboardModule;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-business module switches — the layer support actually reaches for.
 *
 * Deliberately shows *why* each section is on or off, not just whether.
 * With four layers stacked (global, business type, plan, per-business) a
 * bare toggle is unusable: an admin who flips Finance on and sees nothing
 * change has no way to discover that the plan doesn't include it. So each
 * row reports its effective state and the reason behind it.
 */
class BusinessModuleController extends Controller
{
    public function __construct(
        private readonly BusinessModuleResolver $resolver,
    ) {}

    public function index(Business $business): Response
    {
        $modules = Module::query()
            ->whereIn('slug', DashboardModule::slugs())
            ->orderBy('sort_order')
            ->get();

        $hidden = $this->resolver->hiddenSlugs($business);

        $typeModuleIds = $business->businessType?->modules()->pluck('modules.id');
        $planModuleIds = $business->subscription?->plan?->modules()->pluck('modules.id');
        $overrides = BusinessModule::query()
            ->where('business_id', $business->getKey())
            ->pluck('is_enabled', 'module_id');

        return Inertia::render('Platform/Businesses/Modules', [
            'business' => [
                'id' => $business->getKey(),
                'name' => $business->name,
                'plan' => $business->subscription?->plan?->name,
                'business_type' => $business->businessType?->name,
            ],
            'modules' => $modules->map(fn (Module $module): array => [
                'id' => $module->getKey(),
                'slug' => $module->slug,
                'name' => $module->name,
                'description' => $module->description,
                'enabled' => ! in_array($module->slug, $hidden, true),
                // The override, if one exists — tri-state, because "no
                // override" and "explicitly off" look identical in the
                // effective result but mean very different things when you
                // are trying to change it.
                'override' => $overrides->has($module->getKey())
                    ? (bool) $overrides->get($module->getKey())
                    : null,
                'reason' => $this->reason($module, $typeModuleIds, $planModuleIds, $overrides),
            ])->all(),
        ]);
    }

    public function update(Request $request, Business $business): RedirectResponse
    {
        $validated = $request->validate([
            'module_id' => ['required', 'uuid', Rule::exists(Module::class, 'id')],
            // Tri-state on purpose: null clears the override and lets the
            // business fall back to whatever its type and plan grant, which
            // is not the same as forcing it off.
            'enabled' => ['nullable', 'boolean'],
        ]);

        $module = Module::query()->findOrFail($validated['module_id']);

        if (! array_key_exists('enabled', $validated) || $validated['enabled'] === null) {
            BusinessModule::query()
                ->where('business_id', $business->getKey())
                ->where('module_id', $module->getKey())
                ->delete();

            $this->resolver->flush($business);

            return back()->with('status', 'module-reset');
        }

        if ($validated['enabled']) {
            $this->resolver->install($business, $module);
        } else {
            $this->resolver->uninstall($business, $module);
        }

        return back()->with(
            'status',
            $validated['enabled'] ? 'module-enabled' : 'module-disabled',
        );
    }

    /**
     * A one-line explanation of the effective state.
     *
     * Ordered the same way the resolver applies its layers, so the reason
     * shown is the one that actually decided the outcome rather than the
     * first that happens to apply.
     */
    private function reason(
        Module $module,
        mixed $typeModuleIds,
        mixed $planModuleIds,
        mixed $overrides,
    ): string {
        if ($module->status !== Module::STATUS_ACTIVE) {
            return 'Switched off platform-wide.';
        }

        if ($overrides->has($module->getKey())) {
            return $overrides->get($module->getKey())
                ? 'Enabled for this business specifically.'
                : 'Disabled for this business specifically.';
        }

        if ($typeModuleIds !== null && $typeModuleIds->isNotEmpty()
            && ! $typeModuleIds->contains($module->getKey())) {
            return 'Not part of this business type.';
        }

        if ($planModuleIds !== null && $planModuleIds->isNotEmpty()
            && ! $planModuleIds->contains($module->getKey())) {
            return 'Not included in their subscription plan.';
        }

        return 'Available by default.';
    }
}
