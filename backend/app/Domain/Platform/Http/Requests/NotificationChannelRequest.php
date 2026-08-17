<?php

namespace App\Domain\Platform\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NotificationChannelRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', 'max:20'],
            'provider' => ['required', 'string', 'max:40'],
            'credentials' => ['nullable', 'array'],
            'sender_id' => ['nullable', 'string', 'max:255'],
            'webhook_url' => ['nullable', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}
