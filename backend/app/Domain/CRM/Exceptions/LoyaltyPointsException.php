<?php

namespace App\Domain\CRM\Exceptions;

use RuntimeException;

class LoyaltyPointsException extends RuntimeException
{
    public static function insufficientBalance(string $customerName, int $balance, int $requested): self
    {
        return new self("\"{$customerName}\" only has {$balance} loyalty point(s); cannot redeem {$requested}.");
    }
}
