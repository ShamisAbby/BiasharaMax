<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\RBAC\Models\PlatformRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlatformRole
 */
class PlatformRoleResource extends JsonResource
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
            'is_system' => $this->is_system,
            'description' => $this->description,
            'permissions_count' => $this->whenCounted('permissions'),
            'platform_users_count' => $this->whenCounted('platformUsers'),
            'permission_ids' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('id')),
            'created_at' => $this->created_at,
        ];
    }
}
