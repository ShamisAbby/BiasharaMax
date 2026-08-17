<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\RBAC\Models\RoleTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RoleTemplate
 */
class RoleTemplateResource extends JsonResource
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
            'scope' => $this->scope,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'permissions_count' => $this->whenCounted('permissions'),
            'permission_ids' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('id')),
            'created_at' => $this->created_at,
        ];
    }
}
