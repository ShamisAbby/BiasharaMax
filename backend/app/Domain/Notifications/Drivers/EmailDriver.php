<?php

namespace App\Domain\Notifications\Drivers;

use App\Domain\Notifications\Contracts\NotificationChannelDriver;
use Illuminate\Support\Facades\Mail;

/**
 * Uses Laravel's already-configured mail transport (config/mail.php) —
 * no separate provider credentials needed beyond standard SMTP setup.
 */
class EmailDriver implements NotificationChannelDriver
{
    public function send(string $recipient, ?string $subject, string $body): array
    {
        try {
            Mail::raw($body, function ($message) use ($recipient, $subject) {
                $message->to($recipient)->subject($subject ?? 'Notification from BiasharaMax');
            });

            return ['successful' => true, 'provider_message_id' => null, 'error' => null];
        } catch (\Throwable $e) {
            return ['successful' => false, 'provider_message_id' => null, 'error' => $e->getMessage()];
        }
    }
}
