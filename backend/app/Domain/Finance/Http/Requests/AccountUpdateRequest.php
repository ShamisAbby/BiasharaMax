<?php

namespace App\Domain\Finance\Http\Requests;

use App\Domain\Finance\Models\Account;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('account'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->user()->business_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'parent_account_id' => [
                'nullable',
                'uuid',
                Rule::exists(Account::class, 'id')->where('business_id', $businessId),
                Rule::notIn([$this->route('account')->id]),
            ],
        ];
    }
}
