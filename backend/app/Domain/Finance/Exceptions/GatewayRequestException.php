<?php

namespace App\Domain\Finance\Exceptions;

use RuntimeException;

class GatewayRequestException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(string $message, public readonly array $raw = [])
    {
        parent::__construct($message);
    }
}
