<?php

namespace App\Domain\Inventory\Http\Requests;

use App\Domain\Inventory\Models\Tag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TagStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Tag::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
