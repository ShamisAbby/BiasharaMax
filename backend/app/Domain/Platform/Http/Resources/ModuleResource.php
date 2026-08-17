<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\ModuleManagement\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Module
 */
class ModuleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'version' => $this->version,
            'icon' => $this->icon,
            'category' => $this->category,
            'is_premium' => $this->is_premium,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'is_desktop_supported' => $this->is_desktop_supported,
            'is_cloud_supported' => $this->is_cloud_supported,
            'is_hybrid_supported' => $this->is_hybrid_supported,
            'sort_order' => $this->sort_order,
            'businesses_count' => $this->whenCounted('businesses'),
            'dependencies' => $this->whenLoaded('dependencies', fn () => $this->dependencies->map(fn ($dep) => [
                'id' => $dep->id,
                'name' => $dep->name,
            ])),
            'subscription_plans' => $this->whenLoaded('subscriptionPlans', fn () => $this->subscriptionPlans->map(fn ($plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
            ])),
            'business_types' => $this->whenLoaded('businessTypes', fn () => $this->businessTypes->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
