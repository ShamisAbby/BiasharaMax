<?php

namespace App\Domain\Inventory\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public static function forProduct(string $productName, string $available, string $requested): self
    {
        return new self(
            "Insufficient stock for \"{$productName}\": {$available} available, {$requested} requested."
        );
    }
}
