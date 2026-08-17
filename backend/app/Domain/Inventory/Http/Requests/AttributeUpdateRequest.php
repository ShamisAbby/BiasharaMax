<?php

namespace App\Domain\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttributeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('attribute'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'input_type' => ['required', 'string', 'in:select,text,number'],
            'is_variant_attribute' => ['boolean'],
            'status' => ['required', 'string', 'in:active,inactive'],
            'values' => ['array'],
            'values.*' => ['string', 'max:255'],
        ];
    }
}
