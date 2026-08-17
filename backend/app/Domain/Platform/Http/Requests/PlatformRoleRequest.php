<?php

namespace App\Domain\Platform\Http\Requests;

use App\Domain\RBAC\Models\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformRoleRequest extends FormRequest
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
        $roleId = $this->route('platform_role')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('platform_roles', 'slug')->ignore($roleId)],
            'description' => ['nullable', 'string'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => [
                'uuid',
                Rule::exists(Permission::class, 'id')->where('scope', Permission::SCOPE_PLATFORM),
            ],
        ];
    }
}
