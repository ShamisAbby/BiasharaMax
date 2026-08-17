<?php

namespace App\Domain\Accounting\Events;

use App\Domain\Accounting\Models\Expense;
use Illuminate\Foundation\Events\Dispatchable;

class ExpenseMarkedPaid
{
    use Dispatchable;

    public function __construct(
        public readonly Expense $expense,
    ) {}
}
