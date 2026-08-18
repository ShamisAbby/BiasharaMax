<?php

namespace App\Domain\Subscription\Notifications;

use App\Domain\Subscription\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Your plan ends in N days."
 *
 * Sent once a day inside the final 30 days rather than as a single warning
 * on day 30 — a lone email a month out is easily missed, and the first the
 * owner would otherwise know is a locked till on a trading morning.
 *
 * Repetition is bounded by the command, which only sends when the
 * remaining days hits one of a few thresholds. A daily nag for a month
 * trains people to ignore the sender, which costs more than the reminder
 * is worth.
 */
class SubscriptionExpiringSoonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Subscription $subscription,
        private readonly int $daysRemaining,
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
        $businessName = $this->subscription->business?->name ?? 'your business';
        $ends = $this->subscription->current_period_end?->format('j F Y');

        return (new MailMessage)
            ->subject("Your BiasharaMax plan ends in {$this->daysRemaining} days")
            ->greeting('Habari,')
            ->line("The subscription for {$businessName} ends".($ends ? " on {$ends}" : '')." — {$this->daysRemaining} days from now.")
            // Stated plainly, because the alternative is an owner
            // discovering it at the till with customers waiting.
            ->line('When it ends, staff will not be able to sign in or record sales until the plan is renewed. Nothing is deleted.')
            ->action('Renew now', route('plan.expired'))
            ->line('Renewing early does not shorten your plan — the new term is added on.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'subscription_expiring',
            'icon' => 'clock',
            'title' => "Plan ends in {$this->daysRemaining} days",
            'message' => 'Renew to avoid losing access to the dashboard and till.',
            'url' => route('plan.expired'),
            'days_remaining' => $this->daysRemaining,
        ];
    }
}
