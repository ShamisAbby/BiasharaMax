<?php

namespace App\Domain\Purchasing\Events;

use App\Domain\Purchasing\Models\PurchaseOrder;
use Illuminate\Foundation\Events\Dispatchable;

class PurchaseOrderApproved
{
    use Dispatchable;

    public function __construct(
        public readonly PurchaseOrder $purchaseOrder,
    ) {}
}
