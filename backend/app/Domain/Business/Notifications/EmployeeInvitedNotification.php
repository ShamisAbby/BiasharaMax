<?php

namespace App\Domain\Business\Notifications;

use App\Domain\Authentication\Models\User;
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
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $acceptUrl = $this->acceptUrl($notifiable);

        return (new MailMessage)
            ->subject("You've been invited to join {$this->businessName} on BiasharaMax")
            ->line("{$this->inviterName} has invited you to join {$this->businessName} on BiasharaMax.")
            ->action('Accept invitation', $acceptUrl)
            ->line('This invitation link expires in 7 days.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        /** @var User $notifiable */
        return [
            'title' => 'Employee invitation',
            'message' => "{$this->inviterName} invited you to join {$this->businessName}.",
            'url' => $this->acceptUrl($notifiable),
            'icon' => 'envelope',
        ];
    }

    private function acceptUrl(User $notifiable): string
    {
        return URL::temporarySignedRoute(
            'employee-invitations.accept',
            Carbon::now()->addDays(7),
            ['user' => $notifiable->getKey()],
        );
    }
}
