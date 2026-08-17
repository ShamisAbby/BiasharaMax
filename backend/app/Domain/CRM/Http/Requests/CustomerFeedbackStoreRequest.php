<?php

namespace App\Domain\CRM\Http\Requests;

use App\Domain\CRM\Models\CustomerFeedback;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerFeedbackStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CustomerFeedback::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $businessId = $this->user()->business_id;

        return [
            'customer_id' => ['nullable', 'uuid', Rule::exists(\App\Domain\Sales\Models\Customer::class, 'id')->where('business_id', $businessId)],
            'branch_id' => ['nullable', 'uuid', Rule::exists(\App\Domain\Business\Models\Branch::class, 'id')->where('business_id', $businessId)],
            'type' => ['required', 'string', 'in:rating,review,complaint'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
        ];
    }
}
