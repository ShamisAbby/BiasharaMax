<?php

namespace App\Domain\Website\Notifications;

use App\Domain\Website\Models\ProductEnquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductEnquiryRepliedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ProductEnquiry $enquiry,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reply to your enquiry')
            ->greeting("Hi {$this->enquiry->name},")
            ->line('You recently asked:')
            ->line($this->enquiry->message)
            ->line('Our reply:')
            ->line((string) $this->enquiry->reply);
    }
}
