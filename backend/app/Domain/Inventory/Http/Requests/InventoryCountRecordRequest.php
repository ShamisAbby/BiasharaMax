<?php

namespace App\Domain\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InventoryCountRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('complete', $this->route('item')->inventoryCount);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'counted_quantity' => ['required', 'numeric', 'min:0'],
        ];
    }
}
