<?php

namespace App\Domain\Accounting\Http\Requests;

use App\Domain\Accounting\Models\ExpenseCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseCategoryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ExpenseCategory::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
