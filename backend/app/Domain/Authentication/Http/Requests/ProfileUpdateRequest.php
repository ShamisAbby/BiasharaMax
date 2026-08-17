<?php

namespace App\Domain\Authentication\Http\Requests;

use App\Domain\Authentication\Models\User;
use App\Domain\Authentication\Support\UserIdentityRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // Both are unique in the database as of the 2026_08_06
            // migration, so they need the matching rule here — without
            // it a collision surfaces as an unhandled QueryException
            // instead of a field error on the form.
            'username' => [
                'nullable',
                'string',
                'max:'.UserIdentityRules::USERNAME_MAX_LENGTH,
                'regex:'.UserIdentityRules::USERNAME_REGEX,
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:'.UserIdentityRules::PHONE_MAX_LENGTH,
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['username.regex' => UserIdentityRules::USERNAME_MESSAGE];
    }
}
