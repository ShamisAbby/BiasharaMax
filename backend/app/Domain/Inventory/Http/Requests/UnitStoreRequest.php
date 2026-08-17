<?php

namespace App\Domain\Inventory\Http\Requests;

use App\Domain\Inventory\Models\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Unit::class);
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
                Rule::exists(Unit::class, 'id')->where('business_id', $this->user()->business_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'symbol' => [
                'required',
                'string',
                'max:10',
                Rule::unique('units')->where('business_id', $this->user()->business_id),
            ],
            'conversion_factor' => ['required', 'numeric', 'min:0.0001'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }
}
