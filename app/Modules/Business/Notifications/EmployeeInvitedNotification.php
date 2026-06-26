<?php

namespace App\Modules\Business\Notifications;

use App\Modules\Authentication\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class EmployeeInvitedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $businessName,
        private readonly string $inviterName,
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
        /** @var User $notifiable */
        $acceptUrl = URL::temporarySignedRoute(
            'employee-invitations.accept',
            Carbon::now()->addDays(7),
            ['user' => $notifiable->getKey()],
        );

        return (new MailMessage)
            ->subject("You've been invited to join {$this->businessName} on BiasharaOS")
            ->line("{$this->inviterName} has invited you to join {$this->businessName} on BiasharaOS.")
            ->action('Accept invitation', $acceptUrl)
            ->line('This invitation link expires in 7 days.');
    }
}
