<?php

namespace App\Domain\Purchasing\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderRejectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('approve', $this->route('order'));
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
