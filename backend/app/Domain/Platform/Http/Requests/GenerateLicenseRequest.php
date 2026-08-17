<?php

namespace App\Domain\Platform\Http\Requests;

use App\Domain\Licensing\Models\License;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GenerateLicenseRequest extends FormRequest
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
            'business_id' => ['required', 'exists:businesses,id'],
            'type' => ['required', 'in:'.implode(',', [
                License::TYPE_STARTER,
                License::TYPE_PROFESSIONAL,
                License::TYPE_ENTERPRISE,
                License::TYPE_LIFETIME,
            ])],
            'max_devices' => ['required', 'integer', 'min:1', 'max:1000'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'maintenance_expires_at' => ['nullable', 'date'],
            'offline_activation_allowed' => ['boolean'],
            'cloud_sync_enabled' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
