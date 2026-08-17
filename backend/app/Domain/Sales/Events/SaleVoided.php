<?php

namespace App\Domain\Sales\Events;

use App\Domain\Sales\Models\Sale;
use Illuminate\Foundation\Events\Dispatchable;

class SaleVoided
{
    use Dispatchable;

    public function __construct(
        public readonly Sale $sale,
    ) {}
}
