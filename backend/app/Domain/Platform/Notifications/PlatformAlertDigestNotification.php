<?php

namespace App\Domain\Platform\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The overflow case: too many new alerts at once to send individually.
 *
 * Not a feature so much as a circuit breaker. "One email per alert" is
 * the right behaviour at normal volumes and a denial-of-service on your
 * own inbox when something misfires — a backup retry loop, a security
 * rule tripping on every request. Past the configured threshold this
 * sends one message instead, and says plainly that it did, so the
 * silence is never mistaken for nothing having happened.
 */
class PlatformAlertDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(private readonly array $items) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = count($this->items);

        $mail = (new MailMessage)
            ->subject("[ALERT] {$count} new platform alerts")
            ->greeting("{$count} new platform alerts")
            ->line('Too many arrived at once to send individually, so here they are together. This usually means something is failing repeatedly rather than many separate problems.');

        foreach ($this->items as $item) {
            $mail->line('• ['.strtoupper((string) ($item['severity'] ?? 'info')).'] '
                .($item['title'] ?? 'Alert').' — '.($item['description'] ?? ''));
        }

        return $mail->salutation('BiasharaMax platform monitoring');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['items' => $this->items];
    }
}
