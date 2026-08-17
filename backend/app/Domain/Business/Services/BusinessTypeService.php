<?php

namespace App\Domain\Business\Services;

use App\Domain\Business\Exceptions\BusinessTypeInUseException;
use App\Domain\Business\Models\BusinessType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BusinessTypeService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BusinessType
    {
        return BusinessType::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BusinessType $type, array $data): BusinessType
    {
        $type->update($data);

        return $type->refresh();
    }

    public function archive(BusinessType $type): BusinessType
    {
        $type->update(['status' => BusinessType::STATUS_ARCHIVED]);

        return $type->refresh();
    }

    public function activate(BusinessType $type): BusinessType
    {
        $type->update(['status' => BusinessType::STATUS_ACTIVE]);

        return $type->refresh();
    }

    public function deactivate(BusinessType $type): BusinessType
    {
        $type->update(['status' => BusinessType::STATUS_INACTIVE]);

        return $type->refresh();
    }

    public function clone(BusinessType $type, string $newName): BusinessType
    {
        return DB::transaction(function () use ($type, $newName) {
            $clone = $type->replicate(['slug']);
            $clone->name = $newName;
            $clone->slug = Str::slug($newName).'-'.Str::lower(Str::random(4));
            $clone->status = BusinessType::STATUS_ACTIVE;
            $clone->save();

            $clone->modules()->sync($type->modules()->pluck('modules.id'));
            $clone->subscriptionPlans()->sync($type->subscriptionPlans()->pluck('subscription_plans.id'));

            return $clone;
        });
    }

    /**
     * @throws BusinessTypeInUseException
     */
    public function delete(BusinessType $type): void
    {
        if ($type->businesses()->exists()) {
            throw BusinessTypeInUseException::forType($type->name);
        }

        $type->delete();
    }
}
