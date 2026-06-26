<?php

namespace App\Modules\Business\Http\Requests;

use App\Modules\Authentication\Models\User;
use App\Modules\Business\Models\Branch;
use App\Modules\RBAC\Models\Role;
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
            'role_id' => [
                'required',
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
