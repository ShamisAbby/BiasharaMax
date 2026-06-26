<?php

namespace App\Modules\Business\Http\Requests;

use App\Modules\Business\Models\Branch;
use App\Modules\RBAC\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('employees.update');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
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
            'status' => ['required', 'string', 'in:active,suspended'],
        ];
    }
}
