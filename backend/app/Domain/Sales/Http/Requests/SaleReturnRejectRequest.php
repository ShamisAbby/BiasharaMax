<?php

namespace App\Domain\Sales\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaleReturnRejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve', $this->route('return'));
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
