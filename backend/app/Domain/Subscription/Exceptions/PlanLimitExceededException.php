<?php

namespace App\Domain\Subscription\Exceptions;

use RuntimeException;

class PlanLimitExceededException extends RuntimeException
{
    public static function forResource(string $resource, int $limit): self
    {
        return new self(
            "Your subscription plan allows up to {$limit} {$resource}. Upgrade your plan to add more."
        );
    }
}
