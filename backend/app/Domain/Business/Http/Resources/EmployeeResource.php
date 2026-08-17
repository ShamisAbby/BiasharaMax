<?php

namespace App\Domain\Business\Http\Resources;

use App\Domain\Authentication\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class EmployeeResource extends JsonResource
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
            'phone' => $this->phone,
            'status' => $this->status,
            // `role` (singular) is the first assigned role, kept for
            // screens that only show one. `roles` is the real set — an
            // employee may hold several and gets the union of their
            // permissions.
            'role' => $this->whenLoaded('roles', fn () => $this->roles->first() ? [
                'id' => $this->roles->first()->id,
                'name' => $this->roles->first()->name,
            ] : null),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])->values()),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
            ] : null),
            'is_owner' => $this->business && $this->isOwnerOf($this->business),
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
        ];
    }
}
