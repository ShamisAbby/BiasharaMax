<?php

namespace App\Domain\Notifications\Drivers;

use App\Domain\Notifications\Contracts\NotificationChannelDriver;
use App\Domain\Notifications\Exceptions\ChannelNotConfiguredException;
use App\Domain\Notifications\Models\NotificationChannel;

abstract class AbstractChannelDriver implements NotificationChannelDriver
{
    public function __construct(protected readonly NotificationChannel $channel) {}

    protected function ensureConfigured(): void
    {
        if (! $this->channel->isConfigured()) {
            throw ChannelNotConfiguredException::forChannel($this->channel);
        }
    }

    protected function credential(string $key): ?string
    {
        return $this->channel->credentials[$key] ?? null;
    }
}
