<?php

namespace App\Domain\Subscription\Notifications;

use App\Domain\Subscription\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Subscription $subscription,
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
        return (new MailMessage)
            ->subject('Your BiasharaMax subscription has expired')
            ->line('Your subscription has expired.')
            ->line('You have '.Subscription::GRACE_PERIOD_DAYS." days of grace access remaining before your account is locked.")
            ->action('Renew now', route('settings.subscription.show'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Subscription expired',
            'message' => 'Your subscription has expired. You have '.Subscription::GRACE_PERIOD_DAYS.' days of grace access left.',
            'url' => route('settings.subscription.show'),
            'icon' => 'exclamation-triangle',
        ];
    }
}
