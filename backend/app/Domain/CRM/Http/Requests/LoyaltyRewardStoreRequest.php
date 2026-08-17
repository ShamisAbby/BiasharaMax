<?php

namespace App\Domain\CRM\Http\Requests;

use App\Domain\CRM\Models\LoyaltyReward;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoyaltyRewardStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', LoyaltyReward::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'points_cost' => ['required', 'integer', 'min:1'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
