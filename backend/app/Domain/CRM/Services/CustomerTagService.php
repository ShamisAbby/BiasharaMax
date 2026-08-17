<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\CustomerTag;
use Illuminate\Support\Str;

class CustomerTagService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CustomerTag
    {
        $data['slug'] = $this->uniqueSlug($data['business_id'], $data['name']);

        return CustomerTag::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CustomerTag $tag, array $data): CustomerTag
    {
        if (isset($data['name']) && $data['name'] !== $tag->name) {
            $data['slug'] = $this->uniqueSlug($tag->business_id, $data['name'], $tag->id);
        }

        $tag->update($data);

        return $tag->refresh();
    }

    public function delete(CustomerTag $tag): void
    {
        $tag->delete();
    }

    private function uniqueSlug(string $businessId, string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (
            CustomerTag::query()
                ->where('business_id', $businessId)
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
