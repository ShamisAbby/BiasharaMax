<?php

namespace App\Domain\ModuleManagement\Services;

use App\Domain\Business\Models\Business;
use App\Domain\ModuleManagement\Models\BusinessModule;
use App\Domain\ModuleManagement\Models\Module;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * Decides which dashboard sections a business can actually reach.
 *
 * Four layers, applied in this order:
 *
 *   1. **Global.** A module whose registry status isn't `active` is off
 *      for everyone. This is the kill switch, and nothing below can
 *      re-enable it — otherwise "disabled platform-wide" would be a
 *      suggestion rather than a statement.
 *   2. **Business type.** A retail business need not carry Payroll.
 *   3. **Subscription plan.** Packaging: Basic gets Sales, Pro adds Finance.
 *   4. **Per business.** An explicit override for one customer, which is
 *      what support actually reaches for. It can turn a section off, or
 *      turn one on that their plan wouldn't otherwise include.
 *
 * ## Absent configuration means enabled
 *
 * This is the single most important rule here, and it is the opposite of
 * what a security-minded reading would suggest.
 *
 * Every one of these tables is empty on an existing installation — no
 * business type has a module list, no plan has one, and until the seeder
 * runs the registry itself has no rows. Treating "no rows" as "nothing
 * allowed" would take every section away from every live tenant the moment
 * this shipped, an outage caused by a feature nobody had switched on yet.
 *
 * So the question this class answers is deliberately inverted: not "which
 * modules are allowed" but **"which modules have been switched off"**.
 * Anything not explicitly disabled is available. That makes an unseeded or
 * half-configured installation behave exactly as it did before this
 * feature existed, which is the only safe way to retrofit gating onto a
 * system that is already running.
 *
 * That is a deliberate trade: this resolver gates *which features a
 * business has*, not *who may use them*. Who may do what inside a module a
 * business has is `hasPermission()`'s job, and that one does fail closed.
 */
class BusinessModuleResolver
{
    /**
     * Memoised per business for the lifetime of the request.
     *
     * `hasModule()` is called by the middleware on every gated route and
     * again for every sidebar section, so without this a single page load
     * would re-run four queries a dozen times over.
     *
     * @var array<string, list<string>>
     */
    private array $cache = [];

    /**
     * The slugs this business may NOT reach.
     *
     * Expressed as the disabled set rather than the enabled one so that an
     * empty registry, an unconfigured plan and an untouched business all
     * mean "nothing is switched off" — see the note above.
     *
     * @return list<string>
     */
    public function disabledSlugs(Business $business): array
    {
        $key = (string) $business->getKey();

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $modules = Module::query()->get(['id', 'slug', 'status']);

        // Layer 1 — the global kill switch. A module missing from the
        // registry entirely is not managed by this feature at all, so it
        // simply never appears in the disabled set.
        $disabled = $modules
            ->filter(fn (Module $module): bool => $module->status !== Module::STATUS_ACTIVE)
            ->pluck('slug')
            ->all();

        $managed = $modules->pluck('slug', 'id');

        // Layer 2 — business type, and layer 3 — plan. Each is a positive
        // list, so it only says anything when it is non-empty; an empty
        // list is "no opinion", not "nothing".
        //
        // Queried through the pivot tables rather than by walking
        // `$business->businessType->modules`, and that is not a style
        // preference. Traversing a relation LOADS it onto the model, and
        // this exact Business instance is the one shared with the frontend
        // as `auth.business`. Eloquent serialises loaded relations
        // alongside attributes, and `businessType` serialises to the key
        // `business_type` — the same name as the string column. Loading it
        // here silently replaced that string with an object in the page
        // props, and every page rendering `{business.business_type}` threw
        // "Objects are not valid as a React child".
        //
        // A resolver has no business mutating the model it was handed.
        foreach ([
            $this->modulesAllowedBy('business_type_module', 'business_type_id', $business->business_type_id),
            $this->modulesAllowedBy('module_subscription_plan', 'subscription_plan_id', $this->planIdFor($business)),
        ] as $allowedIds) {
            if ($allowedIds === null || $allowedIds->isEmpty()) {
                continue;
            }

            foreach ($managed as $id => $slug) {
                if (! $allowedIds->contains($id)) {
                    $disabled[] = $slug;
                }
            }
        }

        // Layer 4 — the per-business override, in both directions. It is
        // applied last so it can grant back a section the plan excludes,
        // but the global switch above is re-applied after it: "off
        // platform-wide" has to mean off.
        $overrides = BusinessModule::query()
            ->where('business_id', $business->getKey())
            ->pluck('is_enabled', 'module_id');

        foreach ($overrides as $moduleId => $isEnabled) {
            $slug = $managed->get($moduleId);

            if ($slug === null) {
                continue;
            }

            $disabled = $isEnabled
                ? array_values(array_diff($disabled, [$slug]))
                : [...$disabled, $slug];
        }

        $globallyOff = $modules
            ->filter(fn (Module $module): bool => $module->status !== Module::STATUS_ACTIVE)
            ->pluck('slug')
            ->all();

        return $this->cache[$key] = array_values(array_unique([...$disabled, ...$globallyOff]));
    }

    /**
     * The module ids a pivot table allows, or null when it has no opinion.
     *
     * @return \Illuminate\Support\Collection<int, string>|null
     */
    private function modulesAllowedBy(string $table, string $column, ?string $value): ?\Illuminate\Support\Collection
    {
        if ($value === null) {
            return null;
        }

        $ids = DB::table($table)->where($column, $value)->pluck('module_id');

        return $ids->isEmpty() ? null : $ids;
    }

    private function planIdFor(Business $business): ?string
    {
        return DB::table('subscriptions')
            ->where('business_id', $business->getKey())
            ->value('subscription_plan_id');
    }

    /**
     * @return Collection<int, Module>
     */
    public function effectiveModules(Business $business): Collection
    {
        $disabled = $this->disabledSlugs($business);

        return Module::query()
            ->whereNotIn('slug', $disabled === [] ? [''] : $disabled)
            ->get();
    }

    public function hasModule(Business $business, string $moduleSlug): bool
    {
        return ! in_array($moduleSlug, $this->disabledSlugs($business), true);
    }

    /**
     * The disabled slugs, for sharing with the frontend.
     *
     * The negative list travels rather than the positive one for the same
     * reason it is computed that way: the UI must hide only what has been
     * switched off, never everything it hasn't been told about.
     *
     * @return list<string>
     */
    public function hiddenSlugs(?Business $business): array
    {
        return $business === null ? [] : $this->disabledSlugs($business);
    }

    /**
     * Drops the memo.
     *
     * Needed the moment a Super Admin toggles something: without it the
     * response that renders the result of the change would still be built
     * from the state before it.
     */
    public function flush(?Business $business = null): void
    {
        if ($business === null) {
            $this->cache = [];

            return;
        }

        unset($this->cache[(string) $business->getKey()]);
    }

    public function install(Business $business, Module $module): BusinessModule
    {
        $this->flush($business);

        return BusinessModule::query()->updateOrCreate(
            ['business_id' => $business->id, 'module_id' => $module->id],
            ['is_enabled' => true, 'installed_at' => Carbon::now(), 'uninstalled_at' => null],
        );
    }

    public function uninstall(Business $business, Module $module): void
    {
        $this->flush($business);

        BusinessModule::query()->updateOrCreate(
            ['business_id' => $business->id, 'module_id' => $module->id],
            ['is_enabled' => false, 'uninstalled_at' => Carbon::now()],
        );
    }
}
