<?php

namespace App\Domain\Finance\Exceptions;

use App\Domain\Finance\Models\FinancialPeriod;
use RuntimeException;

class LockedPeriodException extends RuntimeException
{
    public static function forPeriod(FinancialPeriod $period): self
    {
        return new self(
            "Cannot post to period '{$period->period_name}' — it is {$period->status}. Choose an open period or reopen this one."
        );
    }
}
