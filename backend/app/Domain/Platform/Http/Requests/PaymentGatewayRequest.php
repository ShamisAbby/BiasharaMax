<?php

namespace App\Domain\Platform\Http\Requests;

use App\Domain\Finance\Models\PaymentGateway;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentGatewayRequest extends FormRequest
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
        $gatewayId = $this->route('payment_gateway')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('payment_gateways', 'slug')->ignore($gatewayId)],
            'provider' => ['required', 'string', 'max:40'],
            'mode' => ['required', Rule::in([PaymentGateway::MODE_SANDBOX, PaymentGateway::MODE_PRODUCTION])],
            'credentials' => ['nullable', 'array'],
            'webhook_url' => ['nullable', 'url', 'max:255'],
            'webhook_secret' => ['nullable', 'string'],
            'supported_currencies' => ['nullable', 'array'],
            'supported_currencies.*' => ['string', 'size:3'],
            'supported_countries' => ['nullable', 'array'],
            'supported_countries.*' => ['string', 'max:2'],
            'fee_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'fee_fixed' => ['nullable', 'numeric', 'min:0'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'documentation_url' => ['nullable', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
