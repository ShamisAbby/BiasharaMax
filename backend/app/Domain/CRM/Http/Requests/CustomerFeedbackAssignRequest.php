<?php

namespace App\Domain\CRM\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerFeedbackAssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->route('feedback'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->user()->business_id;

        return [
            'assigned_to' => ['nullable', 'uuid', Rule::exists(\App\Domain\Authentication\Models\User::class, 'id')->where('business_id', $businessId)],
        ];
    }
}
