<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\CustomerFeedback;
use App\Domain\CRM\Models\CustomerFeedbackReply;
use Illuminate\Support\Carbon;

class CustomerFeedbackService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CustomerFeedback
    {
        return CustomerFeedback::create($data);
    }

    public function reply(CustomerFeedback $feedback, string $body, string $authorId): CustomerFeedbackReply
    {
        $reply = $feedback->replies()->create([
            'author_id' => $authorId,
            'body' => $body,
        ]);

        if ($feedback->status === CustomerFeedback::STATUS_OPEN) {
            $feedback->update(['status' => CustomerFeedback::STATUS_PENDING]);
        }

        return $reply;
    }

    public function assign(CustomerFeedback $feedback, ?string $userId): void
    {
        $feedback->update(['assigned_to' => $userId]);
    }

    public function updateStatus(CustomerFeedback $feedback, string $status): void
    {
        $feedback->update([
            'status' => $status,
            'resolved_at' => $status === CustomerFeedback::STATUS_RESOLVED ? Carbon::now() : $feedback->resolved_at,
        ]);
    }
}
