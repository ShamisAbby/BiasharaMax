<?php

namespace App\Domain\CRM\Http\Requests;

use App\Domain\CRM\Models\CustomerGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerGroupAssignRequest extends FormRequest
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
        $businessId = $this->user()->business_id;

        return [
            'customer_group_id' => ['nullable', 'uuid', Rule::exists(CustomerGroup::class, 'id')->where('business_id', $businessId)],
        ];
    }
}
