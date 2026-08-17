<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Contracts\NotificationChannelDriver;
use App\Domain\Notifications\Drivers\AfricasTalkingSmsDriver;
use App\Domain\Notifications\Drivers\EmailDriver;
use App\Domain\Notifications\Drivers\FcmPushDriver;
use App\Domain\Notifications\Drivers\GenericHttpChannelDriver;
use App\Domain\Notifications\Drivers\InAppDriver;
use App\Domain\Notifications\Drivers\WhatsAppDriver;
use App\Domain\Notifications\Models\NotificationChannel;

class ChannelDriverResolver
{
    /**
     * @var array<string, class-string<NotificationChannelDriver>>
     */
    private const DRIVER_MAP = [
        'africastalking' => AfricasTalkingSmsDriver::class,
        'whatsapp_business' => WhatsAppDriver::class,
        'fcm' => FcmPushDriver::class,
    ];

    public function resolve(NotificationChannel $channel): NotificationChannelDriver
    {
        if ($channel->channel === NotificationChannel::CHANNEL_EMAIL) {
            return new EmailDriver();
        }

        /*
         * Matched on channel type, not provider, because in-app has no
         * provider — it writes a database notification into the
         * recipient's bell.
         *
         * Previously it fell through to GenericHttpChannelDriver and
         * tried to POST the message to a webhook URL that in-app channels
         * never have, so the one channel needing no external service was
         * the one that could not deliver.
         */
        if ($channel->channel === NotificationChannel::CHANNEL_IN_APP) {
            return new InAppDriver();
        }

        $driverClass = self::DRIVER_MAP[$channel->provider] ?? GenericHttpChannelDriver::class;

        return new $driverClass($channel);
    }
}
