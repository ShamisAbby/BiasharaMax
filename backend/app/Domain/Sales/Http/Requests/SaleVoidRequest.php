<?php

namespace App\Domain\Sales\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaleVoidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('void', $this->route('sale'));
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
