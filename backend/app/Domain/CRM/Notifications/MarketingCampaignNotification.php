<?php

namespace App\Domain\CRM\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MarketingCampaignNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $emailSubject,
        private readonly string $emailBody,
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
        $message = (new MailMessage)->subject($this->emailSubject);

        foreach (explode("\n", $this->emailBody) as $line) {
            $message->line($line);
        }

        return $message;
    }
}
