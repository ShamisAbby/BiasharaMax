<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Authentication\Models\PlatformUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PlatformUser
 */
class PlatformAdminResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'platform_role' => $this->whenLoaded('platformRole', fn () => $this->platformRole ? [
                'id' => $this->platformRole->id,
                'name' => $this->platformRole->name,
            ] : null),
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
        ];
    }
}
