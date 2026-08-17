<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\CustomerGroup;
use Illuminate\Support\Str;

class CustomerGroupService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CustomerGroup
    {
        $data['slug'] = $this->uniqueSlug($data['business_id'], $data['name']);

        return CustomerGroup::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CustomerGroup $group, array $data): CustomerGroup
    {
        if (isset($data['name']) && $data['name'] !== $group->name) {
            $data['slug'] = $this->uniqueSlug($group->business_id, $data['name'], $group->id);
        }

        $group->update($data);

        return $group->refresh();
    }

    public function delete(CustomerGroup $group): void
    {
        $group->delete();
    }

    private function uniqueSlug(string $businessId, string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (
            CustomerGroup::query()
                ->where('business_id', $businessId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->withTrashed()
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
