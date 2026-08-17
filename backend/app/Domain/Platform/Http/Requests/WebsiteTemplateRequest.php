<?php

namespace App\Domain\Platform\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebsiteTemplateRequest extends FormRequest
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
        $templateId = $this->route('website_template')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('website_templates', 'slug')->ignore($templateId)],
            'business_type_id' => ['nullable', 'uuid', 'exists:business_types,id'],
            'description' => ['nullable', 'string'],
            'thumbnail_path' => ['nullable', 'string', 'max:255'],
            'preview_url' => ['nullable', 'url', 'max:255'],
            'is_default' => ['boolean'],
            'theme_colors' => ['nullable', 'array'],
            'typography' => ['nullable', 'array'],
            'custom_css' => ['nullable', 'string'],
            'header_config' => ['nullable', 'array'],
            'footer_config' => ['nullable', 'array'],
            'navigation_config' => ['nullable', 'array'],
            'seo_settings' => ['nullable', 'array'],
            'social_media' => ['nullable', 'array'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'],
            'google_maps_embed' => ['nullable', 'string'],
            'analytics_code' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
