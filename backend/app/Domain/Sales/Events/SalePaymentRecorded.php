<?php

namespace App\Domain\Sales\Events;

use App\Domain\Sales\Models\SalePayment;
use Illuminate\Foundation\Events\Dispatchable;

class SalePaymentRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly SalePayment $salePayment,
    ) {}
}
