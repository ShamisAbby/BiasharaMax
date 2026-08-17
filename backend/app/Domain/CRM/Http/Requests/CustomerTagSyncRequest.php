<?php

namespace App\Domain\CRM\Http\Requests;

use App\Domain\CRM\Models\CustomerTag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerTagSyncRequest extends FormRequest
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
            'tag_ids' => ['array'],
            'tag_ids.*' => ['uuid', Rule::exists(CustomerTag::class, 'id')->where('business_id', $businessId)],
        ];
    }
}
