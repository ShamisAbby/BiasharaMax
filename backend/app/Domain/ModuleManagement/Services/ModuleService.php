<?php

namespace App\Domain\ModuleManagement\Services;

use App\Domain\ModuleManagement\Exceptions\ModuleException;
use App\Domain\ModuleManagement\Models\Module;
use App\Domain\ModuleManagement\Models\ModuleVersionHistory;
use Illuminate\Support\Facades\DB;

class ModuleService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Module
    {
        return Module::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Module $module, array $data): Module
    {
        $module->update($data);

        return $module->refresh();
    }

    /**
     * @throws ModuleException
     */
    public function delete(Module $module): void
    {
        if ($module->businesses()->exists()) {
            throw ModuleException::inUse($module->name);
        }

        $module->delete();
    }

    public function enable(Module $module): Module
    {
        $module->update(['status' => Module::STATUS_ACTIVE]);

        return $module->refresh();
    }

    public function disable(Module $module): Module
    {
        $module->update(['status' => Module::STATUS_INACTIVE]);

        return $module->refresh();
    }

    public function updateVersion(Module $module, string $newVersion, ?string $notes = null, ?string $changedBy = null): Module
    {
        return DB::transaction(function () use ($module, $newVersion, $notes, $changedBy) {
            ModuleVersionHistory::query()->create([
                'module_id' => $module->id,
                'from_version' => $module->version,
                'to_version' => $newVersion,
                'changed_by' => $changedBy,
                'notes' => $notes,
            ]);

            $module->update(['version' => $newVersion]);

            return $module->refresh();
        });
    }

    /**
     * @param  array<int, string>  $planIds
     */
    public function assignToPlans(Module $module, array $planIds): void
    {
        $module->subscriptionPlans()->sync($planIds);
    }

    /**
     * @param  array<int, string>  $businessTypeIds
     */
    public function assignToBusinessTypes(Module $module, array $businessTypeIds): void
    {
        $module->businessTypes()->sync($businessTypeIds);
    }

    /**
     * @param  array<int, string>  $dependsOnModuleIds
     */
    public function setDependencies(Module $module, array $dependsOnModuleIds): void
    {
        $module->dependencies()->sync($dependsOnModuleIds);
    }

    /**
     * @throws ModuleException
     */
    public function ensureDependenciesSatisfied(Module $module, array $enabledModuleIds): void
    {
        $missing = $module->dependencies()
            ->whereNotIn('modules.id', $enabledModuleIds)
            ->pluck('modules.name');

        if ($missing->isNotEmpty()) {
            throw ModuleException::missingDependencies($missing->all());
        }
    }
}
