<?php

namespace App\Domain\Inventory\Http\Requests;

use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\InventoryCount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryCountStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', InventoryCount::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required',
                'uuid',
                Rule::exists(Warehouse::class, 'id')->where('business_id', $this->user()->business_id),
            ],
        ];
    }
}
