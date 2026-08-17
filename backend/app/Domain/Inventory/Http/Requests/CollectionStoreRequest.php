<?php

namespace App\Domain\Inventory\Http\Requests;

use App\Domain\Inventory\Models\Collection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CollectionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Collection::class);
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
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
