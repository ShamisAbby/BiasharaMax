<?php

namespace App\Domain\Finance\Exceptions;

use RuntimeException;

class JournalEntryException extends RuntimeException
{
    public static function invalidTransition(string $from, string $action): self
    {
        return new self("A journal entry in \"{$from}\" status cannot be {$action}.");
    }

    public static function noLines(): self
    {
        return new self('A journal entry needs at least two line items.');
    }
}
