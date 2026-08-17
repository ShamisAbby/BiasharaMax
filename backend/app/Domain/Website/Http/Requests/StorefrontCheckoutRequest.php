<?php

namespace App\Domain\Website\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorefrontCheckoutRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'delivery_address' => ['required', 'string', 'max:500'],
            'payment_method' => ['required', 'string', 'in:pay_on_delivery,bank_transfer,mobile_money'],
            'payment_reference' => ['nullable', 'required_unless:payment_method,pay_on_delivery', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
