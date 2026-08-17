<?php

namespace App\Domain\Platform\Notifications;

use App\Domain\Authentication\Models\PlatformUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class PlatformAdminInvitedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
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
        /** @var PlatformUser $notifiable */
        $acceptUrl = $this->acceptUrl($notifiable);

        return (new MailMessage)
            ->subject('You\'ve been invited to BiasharaMax Platform Admin')
            ->line("{$this->inviterName} has invited you to join the BiasharaMax SuperAdmin console.")
            ->action('Accept invitation', $acceptUrl)
            ->line('This invitation link expires in 7 days.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        /** @var PlatformUser $notifiable */
        return [
            'title' => 'Platform admin invitation',
            'message' => "{$this->inviterName} invited you to the SuperAdmin console.",
            'url' => $this->acceptUrl($notifiable),
            'icon' => 'envelope',
        ];
    }

    private function acceptUrl(PlatformUser $notifiable): string
    {
        return URL::temporarySignedRoute(
            'platform.staff-invitations.accept',
            Carbon::now()->addDays(7),
            ['platform_user' => $notifiable->getKey()],
        );
    }
}
