<?php

namespace App\Domain\Platform\Http\Requests;

use App\Domain\Authentication\Rules\EmailNotUsedByAnotherAccount;
use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\RBAC\Models\PlatformRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformAdminInviteRequest extends FormRequest
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.PlatformUser::class.',email', new EmailNotUsedByAnotherAccount],
            'platform_role_id' => ['nullable', 'uuid', Rule::exists(PlatformRole::class, 'id')],
        ];
    }
}
