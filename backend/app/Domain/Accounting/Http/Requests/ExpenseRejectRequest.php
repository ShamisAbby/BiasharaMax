<?php

namespace App\Domain\Accounting\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseRejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve', $this->route('expense'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:500'],
        ];
    }
}
