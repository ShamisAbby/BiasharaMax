<?php

namespace App\Domain\Licensing\Exceptions;

use RuntimeException;

class LicenseException extends RuntimeException
{
    public static function notUsable(): self
    {
        return new self('This license is not active or has expired.');
    }

    public static function deviceLimitReached(int $maxDevices): self
    {
        return new self("This license already has the maximum of {$maxDevices} device(s) activated.");
    }

    public static function deviceNotFound(): self
    {
        return new self('This device is not registered to the license.');
    }

    public static function offlineActivationNotAllowed(): self
    {
        return new self('Offline activation is not enabled for this license.');
    }
}
