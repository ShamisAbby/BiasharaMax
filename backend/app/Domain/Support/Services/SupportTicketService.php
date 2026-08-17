<?php

namespace App\Domain\Support\Services;

use App\Domain\Authentication\Models\User;
use App\Domain\Support\Models\SupportAgent;
use App\Domain\Support\Models\SupportTicket;
use App\Domain\Support\Models\SupportTicketMessage;
use App\Domain\Support\Notifications\SupportTicketRepliedNotification;
use Illuminate\Support\Str;

class SupportTicketService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function open(array $data): SupportTicket
    {
        return SupportTicket::query()->create([
            ...$data,
            'ticket_number' => $this->generateTicketNumber(),
        ]);
    }

    public function reply(SupportTicket $ticket, string $authorType, string $authorId, string $body, bool $isInternalNote = false): SupportTicketMessage
    {
        $message = $ticket->messages()->create([
            'author_type' => $authorType,
            'author_id' => $authorId,
            'body' => $body,
            'is_internal_note' => $isInternalNote,
        ]);

        if (! $isInternalNote && $ticket->first_response_at === null && $authorType === 'platform_user') {
            $ticket->update(['first_response_at' => now()]);
        }

        $this->notifyBusinessOfReply($ticket, $authorType, $isInternalNote);

        return $message;
    }

    /**
     * Tells the customer when support replies.
     *
     * Three conditions, and each one is a way to get this wrong:
     *
     *  - Only replies from `platform_user`. Notifying a business about
     *    its own message is noise.
     *  - Never for internal notes. Those are agents talking to each
     *    other about the customer, and a notification would announce the
     *    existence of a conversation the customer is not meant to see.
     *  - Only to the person who opened it, not everyone at the business.
     *    A support thread can contain account details, and it was one
     *    employee's conversation.
     */
    private function notifyBusinessOfReply(SupportTicket $ticket, string $authorType, bool $isInternalNote): void
    {
        if ($isInternalNote || $authorType !== 'platform_user') {
            return;
        }

        if ($ticket->opened_by_type !== 'user') {
            return;
        }

        $recipient = User::find($ticket->opened_by_id);

        // Employees leave. A ticket outliving its author must not throw
        // on reply — support answering is more important than the
        // notification reaching a deleted account.
        $recipient?->notify(new SupportTicketRepliedNotification($ticket));
    }

    public function assign(SupportTicket $ticket, SupportAgent $agent): SupportTicket
    {
        $ticket->update(['assigned_agent_id' => $agent->id, 'status' => SupportTicket::STATUS_IN_PROGRESS]);

        return $ticket->refresh();
    }

    public function resolve(SupportTicket $ticket): SupportTicket
    {
        $ticket->update(['status' => SupportTicket::STATUS_RESOLVED, 'resolved_at' => now()]);

        return $ticket->refresh();
    }

    public function close(SupportTicket $ticket): SupportTicket
    {
        $ticket->update(['status' => SupportTicket::STATUS_CLOSED, 'closed_at' => now()]);

        return $ticket->refresh();
    }

    public function reopen(SupportTicket $ticket): SupportTicket
    {
        $ticket->update(['status' => SupportTicket::STATUS_REOPENED, 'resolved_at' => null, 'closed_at' => null]);

        return $ticket->refresh();
    }

    public function rate(SupportTicket $ticket, int $rating, ?string $comment = null): SupportTicket
    {
        $ticket->update(['satisfaction_rating' => $rating, 'satisfaction_comment' => $comment]);

        return $ticket->refresh();
    }

    private function generateTicketNumber(): string
    {
        return 'TKT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
