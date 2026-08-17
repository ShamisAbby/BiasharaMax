<?php

namespace App\Domain\Inventory\Http\Requests;

use App\Domain\Inventory\Models\Brand;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BrandStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Brand::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }
}
