<?php

namespace App\Domain\Platform\Http\Requests;

use App\Domain\Business\Models\Business;
use App\Domain\Finance\Models\PaymentGateway;
use App\Domain\Finance\Models\PaymentTransaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentTransactionManualRequest extends FormRequest
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
            'business_id' => ['required', 'uuid', Rule::exists(Business::class, 'id')],
            'payment_gateway_id' => ['nullable', 'uuid', Rule::exists(PaymentGateway::class, 'id')],
            'type' => ['required', Rule::in([
                PaymentTransaction::TYPE_SUBSCRIPTION_PAYMENT,
                PaymentTransaction::TYPE_LICENSE_PAYMENT,
                PaymentTransaction::TYPE_RENEWAL,
                PaymentTransaction::TYPE_UPGRADE,
                PaymentTransaction::TYPE_MANUAL,
            ])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'fee_amount' => ['nullable', 'numeric', 'min:0'],
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['required', 'string', 'max:30'],
            'invoice_number' => ['nullable', 'string', 'max:255', 'unique:payment_transactions,invoice_number'],
            'notes' => ['nullable', 'string'],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
