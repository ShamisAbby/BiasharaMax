<?php

namespace App\Domain\Platform\Http\Resources;

use App\Domain\Support\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupportTicket
 */
class SupportTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'business' => $this->whenLoaded('business', fn () => $this->business ? ['id' => $this->business->id, 'name' => $this->business->name] : null),
            'department' => $this->whenLoaded('department', fn () => $this->department ? ['id' => $this->department->id, 'name' => $this->department->name] : null),
            'assigned_agent' => $this->whenLoaded('assignedAgent', fn () => $this->assignedAgent ? [
                'id' => $this->assignedAgent->id,
                'name' => $this->assignedAgent->platformUser?->name,
            ] : null),
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => $this->status,
            'subject' => $this->subject,
            'description' => $this->description,
            'satisfaction_rating' => $this->satisfaction_rating,
            'satisfaction_comment' => $this->satisfaction_comment,
            'response_time_minutes' => $this->responseTimeMinutes(),
            'first_response_at' => $this->first_response_at,
            'resolved_at' => $this->resolved_at,
            'closed_at' => $this->closed_at,
            'messages' => $this->whenLoaded('messages', fn () => $this->messages->map(fn ($m) => [
                'id' => $m->id,
                'author_type' => $m->author_type,
                'author_id' => $m->author_id,
                'body' => $m->body,
                'is_internal_note' => $m->is_internal_note,
                'created_at' => $m->created_at,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
