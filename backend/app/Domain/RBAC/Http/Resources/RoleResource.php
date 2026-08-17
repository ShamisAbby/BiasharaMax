<?php

namespace App\Domain\RBAC\Http\Resources;

use App\Domain\RBAC\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Role
 */
class RoleResource extends JsonResource
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
            'is_system' => $this->is_system,
            'users_count' => $this->whenCounted('users'),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'created_at' => $this->created_at,
        ];
    }
}
