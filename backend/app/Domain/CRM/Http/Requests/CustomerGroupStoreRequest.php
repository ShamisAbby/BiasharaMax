<?php

namespace App\Domain\CRM\Http\Requests;

use App\Domain\CRM\Models\CustomerGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CustomerGroupStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CustomerGroup::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_vip' => ['nullable', 'boolean'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
