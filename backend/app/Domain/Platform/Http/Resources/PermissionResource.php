<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\RBAC\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Permission
 */
class PermissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'module' => $this->module,
            'scope' => $this->scope,
            'action' => $this->action,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }
}
