<?php

namespace App\Domain\Accounting\Events;

use App\Domain\Accounting\Models\Income;
use Illuminate\Foundation\Events\Dispatchable;

class IncomeRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly Income $income,
    ) {}
}
