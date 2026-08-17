<?php

namespace App\Domain\Subscription\Notifications;

use App\Domain\Subscription\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEndingSoonNotification extends Notification implements ShouldQueue
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
        $daysLeft = max(0, (int) ceil(now()->diffInHours($this->subscription->trial_ends_at) / 24));

        return (new MailMessage)
            ->subject('Your BiasharaMax trial is ending soon')
            ->line("Your free trial ends in {$daysLeft} day(s).")
            ->line('Choose a plan to keep your business running without interruption.')
            ->action('View plans', route('settings.subscription.show'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $daysLeft = max(0, (int) ceil(now()->diffInHours($this->subscription->trial_ends_at) / 24));

        return [
            'title' => 'Trial ending soon',
            'message' => "Your free trial ends in {$daysLeft} day(s). Choose a plan to continue.",
            'url' => route('settings.subscription.show'),
            'icon' => 'clock',
        ];
    }
}
