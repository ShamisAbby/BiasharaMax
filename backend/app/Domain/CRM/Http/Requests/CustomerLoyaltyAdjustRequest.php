<?php

namespace App\Domain\CRM\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CustomerLoyaltyAdjustRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageCrm', $this->route('customer'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:earn,redeem,adjustment'],
            'points' => ['required', 'integer', $this->input('type') === 'adjustment' ? 'min:-1000000' : 'min:1'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
