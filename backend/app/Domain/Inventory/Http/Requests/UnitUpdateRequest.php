<?php

namespace App\Domain\Inventory\Http\Requests;

use App\Domain\Inventory\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('unit'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'base_unit_id' => [
                'nullable',
                'uuid',
                Rule::notIn([$this->route('unit')->id]),
                Rule::exists(Unit::class, 'id')->where('business_id', $this->user()->business_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => [
                'required',
                'string',
                'max:10',
                Rule::unique('units')->where('business_id', $this->user()->business_id)->ignore($this->route('unit')),
            ],
            'conversion_factor' => ['required', 'numeric', 'min:0.0001'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }
}
