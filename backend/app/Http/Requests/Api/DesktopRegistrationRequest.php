<?php

namespace App\Http\Requests\Api;

use App\Domain\Authentication\Rules\EmailNotUsedByAnotherAccount;
use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\BusinessType;
use App\Domain\Localization\Models\Country;
use App\Domain\Localization\Models\Currency;
use App\Domain\Subscription\Models\RegistrationCode;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Sign-up as the desktop app collects it.
 *
 * Deliberately not BusinessRegistrationRequest. That one requires
 * `subscription_plan_id`, because the web sign-up shows a pricing table
 * first. The desktop flow asks the opposite way round — create the
 * account, *then* choose between a product key and a free trial — so the
 * plan is not known when the form is filled in and is resolved here
 * instead of being demanded from a shopkeeper standing at a till.
 *
 * The validation rules that do apply are copied from the web request on
 * purpose rather than inherited: the two forms are allowed to diverge,
 * and silently inheriting a rule change made for the web would break
 * sign-up on a client that cannot be updated as quickly.
 */
class DesktopRegistrationRequest extends FormRequest
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
            'owner_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email', new EmailNotUsedByAnotherAccount],
            'owner_phone' => ['nullable', 'string', 'max:32', 'unique:'.User::class.',phone'],
            'password' => ['required', 'confirmed', Password::defaults()],

            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => [
                'required',
                'string',
                Rule::exists(BusinessType::class, 'slug')->where('status', BusinessType::STATUS_ACTIVE),
            ],
            'business_phone' => ['nullable', 'string', 'max:32'],
            // Checked against the tables, not just length. `size:2` alone
            // accepts "XX", and a business created with a currency that
            // does not exist stamps it on every price, sale and ledger
            // entry from then on — a mistake nobody can correct later
            // without the books disagreeing with themselves.
            'country' => [
                'required',
                'string',
                'size:2',
                Rule::exists(Country::class, 'code')->where('is_active', true),
            ],
            'currency' => [
                'required',
                'string',
                'size:3',
                Rule::exists(Currency::class, 'code')->where('is_active', true),
            ],

            // Present when the vendor chose "I have a product key", absent
            // when they chose the free trial. Both routes end up here so
            // the business is created once, atomically, with the right
            // subscription — rather than created first and upgraded after,
            // which leaves a half-provisioned business behind whenever the
            // second step fails.
            'registration_code' => [
                'nullable',
                'string',
                Rule::exists(RegistrationCode::class, 'code')->where('status', RegistrationCode::STATUS_AVAILABLE),
            ],

            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'registration_code.exists' => 'That product key is not valid, or it has already been used.',
            'owner_email.unique' => 'An account already exists with that email. Sign in instead.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function registrationPayload(): array
    {
        $validated = $this->validated();

        // Only consulted when no product key was given; a key carries its
        // own plan (see BusinessRegistrationService).
        $validated['subscription_plan_id'] = $this->filled('registration_code')
            ? null
            : $this->defaultTrialPlan()->getKey();

        return $validated;
    }

    /**
     * The plan a free trial starts on.
     *
     * Lowest `sort_order` among active plans that actually offer trial
     * days — a plan with `trial_days = 0` would produce a subscription
     * that expires the instant it is created, and the vendor would watch
     * "Start 30-day trial" hand them an expired account.
     */
    private function defaultTrialPlan(): SubscriptionPlan
    {
        $plan = SubscriptionPlan::query()
            ->where('is_active', true)
            ->where('trial_days', '>', 0)
            ->orderBy('sort_order')
            ->first();

        if ($plan === null) {
            abort(503, 'No trial plan is available. Contact support for a product key.');
        }

        return $plan;
    }
}
