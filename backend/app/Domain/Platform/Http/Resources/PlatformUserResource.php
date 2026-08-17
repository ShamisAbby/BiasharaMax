<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Authentication\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class PlatformUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'email'       => $this->email,
            'status'      => $this->status,
            'user_type'   => 'business',
            'is_owner'    => $this->business && $this->isOwnerOf($this->business),
            'business'    => $this->whenLoaded('business', fn () => $this->business ? [
                'id'   => $this->business->id,
                'name' => $this->business->name,
            ] : null),
            'role'        => $this->whenLoaded('role', fn () => $this->role ? [
                'id'   => $this->role->id,
                'name' => $this->role->name,
            ] : null),
            'last_login_at' => $this->last_login_at,
            'created_at'  => $this->created_at,
        ];
    }
}
