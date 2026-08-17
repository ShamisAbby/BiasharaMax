<?php

namespace App\Domain\Notifications\Contracts;

interface NotificationChannelDriver
{
    /**
     * @return array{successful: bool, provider_message_id: ?string, error: ?string}
     */
    public function send(string $recipient, ?string $subject, string $body): array;
}
