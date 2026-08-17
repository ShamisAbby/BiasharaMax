<?php

namespace App\Domain\Finance\Http\Requests;

use App\Domain\Finance\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Account::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->user()->business_id;

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique(Account::class, 'code')->where('business_id', $businessId)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in([
                Account::TYPE_ASSET,
                Account::TYPE_LIABILITY,
                Account::TYPE_EQUITY,
                Account::TYPE_INCOME,
                Account::TYPE_EXPENSE,
            ])],
            'normal_balance' => ['required', Rule::in([Account::BALANCE_DEBIT, Account::BALANCE_CREDIT])],
            'parent_account_id' => ['nullable', 'uuid', Rule::exists(Account::class, 'id')->where('business_id', $businessId)],
        ];
    }
}
