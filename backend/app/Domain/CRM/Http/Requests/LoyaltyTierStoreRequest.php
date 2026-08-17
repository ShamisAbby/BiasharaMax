<?php

namespace App\Domain\CRM\Http\Requests;

use App\Domain\CRM\Models\LoyaltyTier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoyaltyTierStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', LoyaltyTier::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'minimum_spend' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer'],
            'benefits_description' => ['nullable', 'string'],
        ];
    }
}
