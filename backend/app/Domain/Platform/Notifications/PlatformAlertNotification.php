<?php

namespace App\Domain\Platform\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One platform alert, emailed to the operator.
 *
 * Queued: the dispatch command runs on the scheduler every minute, and a
 * slow or unreachable SMTP host must not hold it up or lose the run. The
 * state row is marked emailed when the job is queued rather than when
 * the mail lands, which is the right trade here — a failed send retries
 * through the queue, whereas marking on delivery would re-send the alert
 * on every subsequent run until SMTP recovered.
 */
class PlatformAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $item
     */
    public function __construct(private readonly array $item) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $severity = strtoupper((string) ($this->item['severity'] ?? 'info'));

        $mail = (new MailMessage)
            // Severity in the subject so a rule can filter on it and a
            // phone lock screen conveys urgency without being unlocked.
            ->subject("[{$severity}] ".($this->item['title'] ?? 'Platform alert'))
            ->greeting($this->item['title'] ?? 'Platform alert')
            ->line($this->item['description'] ?? '');

        if (! empty($this->item['href'])) {
            $mail->action('Open in the admin', $this->item['href']);
        }

        return $mail
            ->line('You are receiving this because your address is set in PLATFORM_ALERT_RECIPIENTS.')
            // Named so nobody has to guess why the same alert did not
            // arrive twice.
            ->salutation('BiasharaMax platform monitoring');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->item;
    }
}
