<?php

namespace App\Domain\Platform\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IntegrationRequest extends FormRequest
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
        $integrationId = $this->route('integration')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('integrations', 'slug')->ignore($integrationId)],
            'category' => ['required', 'string', 'max:30'],
            'provider' => ['required', 'string', 'max:40'],
            'mode' => ['required', Rule::in(['sandbox', 'production'])],
            'credentials' => ['nullable', 'array'],
            'webhook_url' => ['nullable', 'url', 'max:255'],
            'documentation_url' => ['nullable', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
