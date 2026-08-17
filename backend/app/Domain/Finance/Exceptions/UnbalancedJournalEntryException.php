<?php

namespace App\Domain\Finance\Exceptions;

use RuntimeException;

class UnbalancedJournalEntryException extends RuntimeException
{
    public static function make(string $totalDebits, string $totalCredits): self
    {
        return new self("Journal entry is not balanced: total debits ({$totalDebits}) do not equal total credits ({$totalCredits}).");
    }
}
