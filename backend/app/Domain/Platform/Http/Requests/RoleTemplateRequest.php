<?php

namespace App\Domain\Platform\Http\Requests;

use App\Domain\RBAC\Models\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleTemplateRequest extends FormRequest
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
        $templateId = $this->route('role_template')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('role_templates', 'slug')->ignore($templateId)],
            'scope' => ['required', 'in:tenant,platform'],
            'description' => ['nullable', 'string'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => [
                'uuid',
                Rule::exists(Permission::class, 'id')->where('scope', $this->input('scope')),
            ],
        ];
    }
}
