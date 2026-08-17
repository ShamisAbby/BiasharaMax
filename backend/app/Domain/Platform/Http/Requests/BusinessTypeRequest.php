<?php

namespace App\Domain\Platform\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $typeId = $this->route('business_type')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('business_types', 'slug')->ignore($typeId)],
            'icon' => ['nullable', 'string', 'max:60'],
            'color' => ['nullable', 'string', 'max:7'],
            'description' => ['nullable', 'string'],
            'default_currency' => ['nullable', 'string', 'size:3'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'website_template' => ['nullable', 'string', 'max:255'],
            'inventory_enabled' => ['boolean'],
            'pos_enabled' => ['boolean'],
            'accounting_enabled' => ['boolean'],
            'crm_enabled' => ['boolean'],
            'website_enabled' => ['boolean'],
            'online_ordering_enabled' => ['boolean'],
            'offline_mode_enabled' => ['boolean'],
            'desktop_edition_enabled' => ['boolean'],
            'default_employee_limit' => ['nullable', 'integer', 'min:0'],
            'default_branch_limit' => ['nullable', 'integer', 'min:0'],
            'default_warehouse_limit' => ['nullable', 'integer', 'min:0'],
            'default_storage_limit_mb' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['integer'],
        ];
    }
}
