<?php

namespace App\Modules\Business\Http\Requests;

use App\Modules\Business\Models\Branch;
use App\Modules\Business\Models\Warehouse;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Warehouse::class);
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
                Rule::unique('warehouses')->where('business_id', $this->user()->business_id),
            ],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
