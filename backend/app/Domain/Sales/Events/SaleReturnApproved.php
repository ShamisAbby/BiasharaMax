<?php

namespace App\Domain\Sales\Events;

use App\Domain\Sales\Models\SaleReturn;
use Illuminate\Foundation\Events\Dispatchable;

class SaleReturnApproved
{
    use Dispatchable;

    public function __construct(
        public readonly SaleReturn $saleReturn,
    ) {}
}
