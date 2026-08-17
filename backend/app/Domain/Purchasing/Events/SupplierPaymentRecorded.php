<?php

namespace App\Domain\Purchasing\Events;

use App\Domain\Purchasing\Models\SupplierPayment;
use Illuminate\Foundation\Events\Dispatchable;

class SupplierPaymentRecorded
{
    use Dispatchable;

    public function __construct(
        public readonly SupplierPayment $supplierPayment,
    ) {}
}
