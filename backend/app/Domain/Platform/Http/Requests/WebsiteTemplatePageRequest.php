<?php

namespace App\Domain\Platform\Http\Requests;

use App\Domain\WebsiteTemplates\Models\WebsiteTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebsiteTemplatePageRequest extends FormRequest
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
            'type' => ['required', Rule::in(WebsiteTemplate::PAGE_TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'array'],
            'is_enabled' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
