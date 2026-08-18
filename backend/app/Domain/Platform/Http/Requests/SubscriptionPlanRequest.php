<?php

namespace App\Domain\Platform\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionPlanRequest extends FormRequest
{
    /**
     * Gated by the auth:platform route middleware, same convention as the
     * rest of the Platform module's admin-only actions.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('subscription_plans', 'slug')->ignore($planId)],
            'type' => ['required', 'in:standard,enterprise'],
            'description' => ['nullable', 'string'],
            // The two fields the product is actually sold on. They were
            // added to the table by migration and never to this form, so
            // the only writer was the seeder: an operator could rename a
            // plan and edit its monthly price all day while the customer's
            // renewal page — which reads `price` and `duration_months` —
            // showed the seeded values unchanged. Renaming "3 Months" to
            // "6 Months" left it three months long at the three-month
            // price, under a label promising six.
            'duration_months' => ['required', 'integer', 'min:1', 'max:60'],
            'price' => ['required', 'numeric', 'min:0'],

            // Legacy, and no longer set by hand — derived from the two
            // above in the controller. Kept nullable rather than dropped
            // because existing subscriptions still read them.
            'price_monthly' => ['nullable', 'numeric', 'min:0'],
            'price_quarterly' => ['nullable', 'numeric', 'min:0'],
            'price_yearly' => ['nullable', 'numeric', 'min:0'],
            'price_lifetime' => ['nullable', 'numeric', 'min:0'],
            'trial_days' => ['required', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
            'max_users' => ['nullable', 'integer', 'min:0'],
            'max_branches' => ['nullable', 'integer', 'min:0'],
            'max_warehouses' => ['nullable', 'integer', 'min:0'],
            'max_products' => ['nullable', 'integer', 'min:0'],
            'max_employees' => ['nullable', 'integer', 'min:0'],
            'max_storage_mb' => ['nullable', 'integer', 'min:0'],
            'max_api_requests_per_day' => ['nullable', 'integer', 'min:0'],
            'max_notifications_per_month' => ['nullable', 'integer', 'min:0'],
            'includes_website' => ['boolean'],
            'includes_ai' => ['boolean'],
            'includes_offline_sync' => ['boolean'],
            'includes_desktop_edition' => ['boolean'],
            'includes_reports' => ['boolean'],
        ];
    }
}
