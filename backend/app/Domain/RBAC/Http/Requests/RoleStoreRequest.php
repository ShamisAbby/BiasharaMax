<?php

namespace App\Domain\RBAC\Http\Requests;

use App\Domain\RBAC\Models\Permission;
use App\Domain\RBAC\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Role::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles')->where('business_id', $this->user()->business_id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['array'],
            'permissions.*' => [
                'uuid',
                Rule::exists(Permission::class, 'id')->where('scope', Permission::SCOPE_TENANT),
            ],
        ];
    }
}
