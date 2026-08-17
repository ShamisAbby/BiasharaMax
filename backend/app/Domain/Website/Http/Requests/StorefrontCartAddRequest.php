<?php

namespace App\Domain\Website\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorefrontCartAddRequest extends FormRequest
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
            'product_id' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }
}
