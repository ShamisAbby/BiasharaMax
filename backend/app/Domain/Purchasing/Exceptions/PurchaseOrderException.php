<?php

namespace App\Domain\Purchasing\Exceptions;

use RuntimeException;

class PurchaseOrderException extends RuntimeException
{
    public static function invalidTransition(string $from, string $action): self
    {
        return new self("A purchase order in \"{$from}\" status cannot be {$action}.");
    }

    public static function noItems(): self
    {
        return new self('A purchase order needs at least one line item.');
    }

    public static function cancelled(): self
    {
        return new self('This purchase order has been cancelled.');
    }

    public static function paymentExceedsBalance(string $balanceDue): self
    {
        return new self("Payment amount exceeds the outstanding balance of {$balanceDue}.");
    }
}
