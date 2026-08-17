<?php

namespace App\Domain\Platform\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NotificationCampaignRequest extends FormRequest
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
            'notification_template_id' => ['nullable', 'uuid', 'exists:notification_templates,id'],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', 'max:20'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'audience_type' => ['required', 'string', 'max:30'],
            'audience_filter' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'date'],
        ];
    }
}
