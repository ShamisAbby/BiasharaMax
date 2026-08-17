<?php

namespace App\Domain\Purchasing\Exceptions;

use RuntimeException;

class GoodsReceivedException extends RuntimeException
{
    public static function purchaseOrderNotReceivable(string $status): self
    {
        return new self("A purchase order in \"{$status}\" status cannot have goods received against it.");
    }

    public static function overDelivery(string $productName, string $remaining, string $attempted): self
    {
        return new self("\"{$productName}\" has {$remaining} unit(s) remaining on this order; cannot receive {$attempted}.");
    }

    public static function itemNotOnOrder(): self
    {
        return new self('One of the line items submitted does not belong to this purchase order.');
    }
}
