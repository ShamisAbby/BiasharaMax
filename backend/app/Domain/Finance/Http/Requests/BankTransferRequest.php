<?php

namespace App\Domain\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('finance.bank-accounts.manage');
    }

    public function rules(): array
    {
        return [
            'from_bank_account_id' => ['required', 'uuid'],
            'to_bank_account_id' => ['required', 'uuid', 'different:from_bank_account_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
