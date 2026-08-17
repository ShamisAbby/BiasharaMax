<?php

namespace App\Domain\Notifications\Notifications;

use Illuminate\Notifications\Notification;

/**
 * A platform campaign delivered into a tenant's notification bell.
 *
 * Database only. The campaign already chose its channel — routing an
 * in-app campaign to email as well would deliver it twice through a
 * channel the administrator did not select, and would bypass whatever
 * enabled/disabled state that email channel is in.
 *
 * The payload keys match what NotificationController::index reads
 * (`title`, `message`, `url`, `icon`), so these appear in the existing
 * bell with no changes to it.
 */
class PlatformBroadcastNotification extends Notification
{
    public function __construct(
        private readonly ?string $subject,
        private readonly string $body,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            // Campaigns are not required to have a subject — SMS ones
            // typically do not — so the bell needs a sensible heading
            // either way rather than an empty line.
            'title' => $this->subject ?: 'Message from BiasharaMax',
            'message' => $this->body,
            'url' => null,
            'icon' => 'megaphone',
        ];
    }
}
