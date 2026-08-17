<?php

namespace App\Domain\Inventory\Events;

use App\Domain\Inventory\Models\StockTransfer;
use Illuminate\Foundation\Events\Dispatchable;

class StockTransferCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly StockTransfer $stockTransfer,
    ) {}
}
