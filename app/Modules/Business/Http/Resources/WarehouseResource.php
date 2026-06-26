<?php

namespace App\Modules\Business\Http\Resources;

use App\Modules\Business\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Warehouse
 */
class WarehouseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->whenLoaded('branch', fn () => $this->branch->name),
            'name' => $this->name,
            'code' => $this->code,
            'is_default' => $this->is_default,
            'address' => $this->address,
            'status' => $this->status,
        ];
    }
}
