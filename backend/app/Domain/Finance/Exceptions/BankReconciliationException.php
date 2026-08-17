<?php

namespace App\Domain\Finance\Exceptions;

use RuntimeException;

class BankReconciliationException extends RuntimeException
{
    public static function notBalanced(string $difference): self
    {
        return new self("Reconciliation cannot be completed: difference of {$difference} remains unresolved.");
    }
}
