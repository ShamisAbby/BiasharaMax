<?php

namespace App\Domain\Platform\Http\Requests;

use App\Domain\RBAC\Models\PlatformRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformAdminUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'platform_role_id' => ['nullable', 'uuid', Rule::exists(PlatformRole::class, 'id')],
        ];
    }
}
