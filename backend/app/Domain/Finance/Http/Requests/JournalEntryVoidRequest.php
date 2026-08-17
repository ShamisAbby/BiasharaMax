<?php

namespace App\Domain\Finance\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class JournalEntryVoidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('void', $this->route('entry'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
