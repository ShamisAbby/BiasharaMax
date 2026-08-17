<?php

namespace App\Domain\CRM\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MarketingCampaignUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('campaign'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],

            'segment_filters' => ['nullable', 'array'],
            'segment_filters.tag_ids' => ['nullable', 'array'],
            'segment_filters.tag_ids.*' => ['uuid'],
            'segment_filters.loyalty_tier_id' => ['nullable', 'uuid'],
            'segment_filters.debt_status' => ['nullable', 'string', 'in:with_debt,no_debt'],
            'segment_filters.inactive_days' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
