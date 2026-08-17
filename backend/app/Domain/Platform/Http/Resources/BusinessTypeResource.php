<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Business\Models\BusinessType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessType
 */
class BusinessTypeResource extends JsonResource
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
            'icon' => $this->icon,
            'color' => $this->color,
            'description' => $this->description,
            'default_currency' => $this->default_currency,
            'default_tax_rate' => $this->default_tax_rate,
            'website_template' => $this->website_template,
            'inventory_enabled' => $this->inventory_enabled,
            'pos_enabled' => $this->pos_enabled,
            'accounting_enabled' => $this->accounting_enabled,
            'crm_enabled' => $this->crm_enabled,
            'website_enabled' => $this->website_enabled,
            'online_ordering_enabled' => $this->online_ordering_enabled,
            'offline_mode_enabled' => $this->offline_mode_enabled,
            'desktop_edition_enabled' => $this->desktop_edition_enabled,
            'default_employee_limit' => $this->default_employee_limit,
            'default_branch_limit' => $this->default_branch_limit,
            'default_warehouse_limit' => $this->default_warehouse_limit,
            'default_storage_limit_mb' => $this->default_storage_limit_mb,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'businesses_count' => $this->whenCounted('businesses'),
            'modules' => $this->whenLoaded('modules', fn () => $this->modules->map(fn ($module) => [
                'id' => $module->id,
                'name' => $module->name,
            ])),
            'subscription_plans' => $this->whenLoaded('subscriptionPlans', fn () => $this->subscriptionPlans->map(fn ($plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
