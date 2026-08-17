<?php

namespace App\Domain\Support\Notifications;

use App\Domain\Support\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Support has replied to a business's ticket.
 *
 * Both database and mail: the bell is where they will see it if they are
 * working, and email is what reaches them if they are not — a support
 * reply that waits until the customer next signs in defeats the point of
 * replying quickly.
 */
class SupportTicketRepliedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly SupportTicket $ticket) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Support replied to '.$this->ticket->ticket_number)
            ->greeting('We have replied to your ticket')
            ->line($this->ticket->subject)
            ->action('Read the reply', route('support.show', $this->ticket->id))
            // The reply body is deliberately not included. A support
            // thread can carry account details, and email is the least
            // controlled place any of this ends up — the link requires a
            // signed-in session, the email does not.
            ->line('Sign in to read the full reply and respond.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Support replied to your ticket',
            'message' => $this->ticket->subject,
            'url' => route('support.show', $this->ticket->id),
            'icon' => 'lifebuoy',
        ];
    }
}
