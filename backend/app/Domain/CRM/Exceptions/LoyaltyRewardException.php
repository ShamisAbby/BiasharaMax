<?php

namespace App\Domain\CRM\Exceptions;

use RuntimeException;

class LoyaltyRewardException extends RuntimeException
{
    public static function outOfStock(string $rewardName): self
    {
        return new self("\"{$rewardName}\" is out of stock.");
    }

    public static function inactive(string $rewardName): self
    {
        return new self("\"{$rewardName}\" is no longer available for redemption.");
    }
}
