<?php

namespace App\Modules\Business\Http\Resources;

use App\Modules\Business\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Branch
 */
class BranchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'is_main' => $this->is_main,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'status' => $this->status,
            'warehouses_count' => $this->whenCounted('warehouses'),
            'employees_count' => $this->whenCounted('employees'),
        ];
    }
}
