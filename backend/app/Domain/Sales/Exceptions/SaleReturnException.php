<?php

namespace App\Domain\Sales\Exceptions;

use RuntimeException;

class SaleReturnException extends RuntimeException
{
    public static function saleNotEligible(string $status): self
    {
        return new self("A sale with status \"{$status}\" cannot have items returned against it.");
    }

    public static function overReturn(string $productName, string $available, string $attempted): self
    {
        return new self("\"{$productName}\" only has {$available} unit(s) eligible for return; cannot return {$attempted}.");
    }

    public static function itemNotOnSale(): self
    {
        return new self('One of the line items submitted does not belong to this sale.');
    }

    public static function invalidTransition(string $status, string $action): self
    {
        return new self("A return in \"{$status}\" status cannot be {$action}.");
    }
}
