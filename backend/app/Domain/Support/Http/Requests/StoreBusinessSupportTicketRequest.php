<?php

namespace App\Domain\Support\Http\Requests;

use App\Domain\Support\Http\Controllers\BusinessSupportTicketController;
use App\Domain\Support\Models\SupportTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusinessSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any signed-in employee of a business may ask the platform for
        // help. Gating this behind a permission would mean a cashier who
        // cannot sell has no way to report that they cannot sell.
        return $this->user() !== null && $this->user()->business_id !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:5', 'max:150'],
            // A floor as well as a ceiling: "it's broken" costs a
            // round trip to ask what is broken, and the person who
            // knows is the one typing right now.
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'category' => ['required', Rule::in(array_keys(BusinessSupportTicketController::CATEGORIES))],
            'priority' => ['required', Rule::in([
                SupportTicket::PRIORITY_LOW,
                SupportTicket::PRIORITY_MEDIUM,
                SupportTicket::PRIORITY_HIGH,
                SupportTicket::PRIORITY_URGENT,
            ])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'description.min' => 'Please describe what happened in a bit more detail — what you were doing, and what you expected instead.',
            'subject.min' => 'A few more words in the subject will help us route this to the right person.',
        ];
    }
}
