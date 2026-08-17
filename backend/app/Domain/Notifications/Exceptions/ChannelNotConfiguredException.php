<?php

namespace App\Domain\Notifications\Exceptions;

use App\Domain\Notifications\Models\NotificationChannel;
use RuntimeException;

class ChannelNotConfiguredException extends RuntimeException
{
    public static function forChannel(NotificationChannel $channel): self
    {
        return new self("Channel \"{$channel->name}\" is not enabled or has no credentials configured.");
    }
}
