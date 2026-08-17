<?php

namespace App\Domain\CRM\Http\Requests;

use App\Domain\CRM\Models\CustomerTag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CustomerTagStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CustomerTag::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
