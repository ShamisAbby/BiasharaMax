<?php

namespace App\Domain\Business\Http\Requests;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\BusinessType;
use App\Domain\Subscription\Models\RegistrationCode;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class BusinessRegistrationRequest extends FormRequest
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
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email'],
            // `users.phone` became unique in the 2026_08_06 migration, so
            // this needs the matching rule — otherwise a duplicate number
            // aborts the registration transaction with a raw
            // QueryException instead of a form error.
            'owner_phone' => ['nullable', 'string', 'max:32', 'unique:'.User::class.',phone'],
            'password' => ['required', 'confirmed', Password::defaults()],

            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => [
                'required',
                'string',
                Rule::exists(BusinessType::class, 'slug')->where('status', BusinessType::STATUS_ACTIVE),
            ],
            'business_phone' => ['nullable', 'string', 'max:32'],
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],

            'registration_code' => [
                'nullable',
                'string',
                Rule::exists(RegistrationCode::class, 'code')->where('status', RegistrationCode::STATUS_AVAILABLE),
            ],
            'subscription_plan_id' => [
                $this->filled('registration_code') ? 'nullable' : 'required',
                'uuid',
                'exists:'.SubscriptionPlan::class.',id',
            ],
        ];
    }
}
