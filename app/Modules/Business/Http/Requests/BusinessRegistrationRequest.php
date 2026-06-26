<?php

namespace App\Modules\Business\Http\Requests;

use App\Modules\Authentication\Models\User;
use App\Modules\Subscription\Models\SubscriptionPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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
            'owner_phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Password::defaults()],

            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', 'string', 'in:retail,supermarket,restaurant,pharmacy,hardware,electronics,fashion,beauty,wholesale,service,other'],
            'business_phone' => ['nullable', 'string', 'max:32'],
            'country' => ['required', 'string', 'size:2'],
            'currency' => ['required', 'string', 'size:3'],

            'subscription_plan_id' => ['required', 'uuid', 'exists:'.SubscriptionPlan::class.',id'],
        ];
    }
}
