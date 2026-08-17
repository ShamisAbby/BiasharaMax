<?php

namespace App\Domain\Finance\Http\Resources;

use App\Domain\Finance\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Account
 */
class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'subtype' => $this->subtype,
            'normal_balance' => $this->normal_balance,
            'is_system_default' => $this->is_system_default,
            'is_active' => $this->is_active,
            'parent_account_id' => $this->parent_account_id,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id' => $this->parent->id,
                'code' => $this->parent->code,
                'name' => $this->parent->name,
            ] : null),
        ];
    }
}
