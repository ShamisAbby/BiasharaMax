<?php

namespace App\Domain\Finance\Exceptions;

use RuntimeException;

class AccountException extends RuntimeException
{
    public static function unknownSystemAccountKey(string $key): self
    {
        return new self("\"{$key}\" is not a recognized system account key.");
    }

    public static function systemAccountUndeletable(string $name): self
    {
        return new self("\"{$name}\" is a system-default account and cannot be deleted, only deactivated.");
    }

    public static function accountHasJournalActivity(string $name): self
    {
        return new self("\"{$name}\" has posted journal activity and cannot be deleted.");
    }
}
