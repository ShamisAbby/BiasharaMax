<?php

namespace App\Domain\Accounting\Http\Requests;

use App\Domain\Accounting\Models\Income;
use App\Domain\Business\Models\Branch;
use App\Domain\Sales\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Income::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->user()->business_id;

        return [
            'branch_id' => ['nullable', 'uuid', Rule::exists(Branch::class, 'id')->where('business_id', $businessId)],
            'customer_id' => ['nullable', 'uuid', Rule::exists(Customer::class, 'id')->where('business_id', $businessId)],

            'category' => ['required', 'string', 'in:service,other,manual'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'income_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'in:cash,bank_transfer,mobile_money,card,cheque,other'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
