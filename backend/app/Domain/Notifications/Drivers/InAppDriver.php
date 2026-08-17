<?php

namespace App\Domain\Notifications\Drivers;

use App\Domain\Authentication\Models\User;
use App\Domain\Notifications\Contracts\NotificationChannelDriver;
use App\Domain\Notifications\Notifications\PlatformBroadcastNotification;

/**
 * Delivers a campaign to the recipient's notification bell.
 *
 * The only channel with no external provider, no credentials and no
 * network call — which makes it the one an administrator can turn on
 * immediately, and the reason the empty state on the Notification Centre
 * suggests starting here.
 *
 * Without this, `in_app` fell through to GenericHttpChannelDriver and
 * tried to POST the message to a webhook URL that in-app channels do not
 * have. The advice to start with in-app was true about the configuration
 * and false about the outcome.
 *
 * `$recipient` is the tenant user's id here rather than an address —
 * see NotificationDispatchService::dispatchToUser, which resolves a
 * contact value per channel type and falls back to the id for channels
 * that deliver in-product.
 */
class InAppDriver implements NotificationChannelDriver
{
    /**
     * @return array{successful: bool, provider_message_id: ?string, error: ?string}
     */
    public function send(string $recipient, ?string $subject, string $body): array
    {
        $user = User::find($recipient);

        if (! $user) {
            return [
                'successful' => false,
                'provider_message_id' => null,
                // Named rather than generic: a deleted recipient is a
                // different problem from a provider outage, and the two
                // want different responses from whoever reads this.
                'error' => 'Recipient no longer exists.',
            ];
        }

        $user->notify(new PlatformBroadcastNotification($subject, $body));

        return [
            'successful' => true,
            // The notification id would be more useful, but `notify()`
            // does not return it. The delivery row already records who
            // and when, which is what this column is read for.
            'provider_message_id' => null,
            'error' => null,
        ];
    }
}
