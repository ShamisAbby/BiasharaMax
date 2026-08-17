<?php

namespace App\Domain\Purchasing\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancel', $this->route('order'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'max:500'],
        ];
    }
}
