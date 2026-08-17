<?php

namespace App\Domain\Inventory\Events;

use App\Domain\Inventory\Models\Inventory;
use Illuminate\Foundation\Events\Dispatchable;

class LowStockDetected
{
    use Dispatchable;

    public function __construct(
        public readonly Inventory $inventory,
    ) {}
}
