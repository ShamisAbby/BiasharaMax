<?php

namespace App\Domain\Purchasing\Http\Requests;

use App\Domain\Purchasing\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SupplierStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Supplier::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }
}
