<?php

namespace App\Domain\Business\Http\Requests;

use App\Domain\Business\Models\Branch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('warehouse'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'branch_id' => [
                'required',
                'uuid',
                Rule::exists(Branch::class, 'id')->where('business_id', $this->user()->business_id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:20',
                'alpha_dash',
                Rule::unique('warehouses')
                    ->where('business_id', $this->user()->business_id)
                    ->ignore($this->route('warehouse')),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }
}
