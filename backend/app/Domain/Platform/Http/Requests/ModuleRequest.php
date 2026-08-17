<?php

namespace App\Domain\Platform\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModuleRequest extends FormRequest
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
        $moduleId = $this->route('module')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('modules', 'slug')->ignore($moduleId)],
            'description' => ['nullable', 'string'],
            'version' => ['required', 'string', 'max:20'],
            'icon' => ['nullable', 'string', 'max:60'],
            'category' => ['nullable', 'string', 'max:60'],
            'is_premium' => ['boolean'],
            'visibility' => ['required', 'in:public,hidden,beta'],
            'is_desktop_supported' => ['boolean'],
            'is_cloud_supported' => ['boolean'],
            'is_hybrid_supported' => ['boolean'],
            'sort_order' => ['integer'],
            'dependency_ids' => ['nullable', 'array'],
            'dependency_ids.*' => ['uuid', 'exists:modules,id'],
        ];
    }
}
