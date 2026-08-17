<?php

namespace App\Domain\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankAccountStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('finance.bank-accounts.manage');
    }

    public function rules(): array
    {
        return [
            'account_id' => ['required', 'uuid'],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_holder_name' => ['required', 'string', 'max:150'],
            'currency_id' => ['nullable', 'uuid'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_date' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ];
    }
}
