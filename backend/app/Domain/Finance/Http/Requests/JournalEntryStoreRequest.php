<?php

namespace App\Domain\Finance\Http\Requests;

use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class JournalEntryStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', JournalEntry::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->user()->business_id;

        return [
            'entry_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'memo' => ['nullable', 'string'],

            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'uuid', Rule::exists(Account::class, 'id')->where('business_id', $businessId)],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
