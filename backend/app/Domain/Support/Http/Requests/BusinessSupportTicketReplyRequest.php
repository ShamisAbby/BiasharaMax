<?php

namespace App\Domain\Support\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BusinessSupportTicketReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is enforced by the controller resolving the ticket
        // through a business-scoped query, not here — a rule in this
        // class would have to re-fetch the ticket to check it, and two
        // places deciding the same thing is how they end up disagreeing.
        return $this->user() !== null && $this->user()->business_id !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:2', 'max:5000'],
        ];
    }
}
