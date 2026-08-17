<?php

namespace App\Domain\Subscription\Notifications;

use App\Domain\Subscription\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionRenewedNotification extends Notification implements ShouldQueue
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
        $periodEnd = $this->subscription->current_period_end?->toFormattedDateString() ?? 'your next billing date';

        return (new MailMessage)
            ->subject('Your BiasharaMax subscription has been renewed')
            ->line("Your subscription has been renewed through {$periodEnd}.")
            ->action('View subscription', route('settings.subscription.show'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $periodEnd = $this->subscription->current_period_end?->toFormattedDateString() ?? 'your next billing date';

        return [
            'title' => 'Subscription renewed',
            'message' => "Your subscription has been renewed through {$periodEnd}.",
            'url' => route('settings.subscription.show'),
            'icon' => 'check-circle',
        ];
    }
}
