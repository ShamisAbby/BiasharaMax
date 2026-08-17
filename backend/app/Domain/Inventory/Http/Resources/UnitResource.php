<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Unit
 */
class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'base_unit_id' => $this->base_unit_id,
            'base_unit_name' => $this->whenLoaded('baseUnit', fn () => $this->baseUnit?->name),
            'name' => $this->name,
            'symbol' => $this->symbol,
            'conversion_factor' => $this->conversion_factor,
            'status' => $this->status,
        ];
    }
}
