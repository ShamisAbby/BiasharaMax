<?php

namespace App\Domain\Business\Http\Requests;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Branch;
use App\Domain\RBAC\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('employees.create');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email'],
            // Multiple roles per employee; permissions are the union of
            // all of them. `role_ids.*` is scoped to the caller's own
            // business so a role from another tenant can't be attached.
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => [
                'uuid',
                Rule::exists(Role::class, 'id')->where('business_id', $this->user()->business_id),
            ],
            'branch_id' => [
                'nullable',
                'uuid',
                Rule::exists(Branch::class, 'id')->where('business_id', $this->user()->business_id),
            ],
        ];
    }
}
