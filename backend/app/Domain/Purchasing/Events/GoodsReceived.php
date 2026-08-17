<?php

namespace App\Domain\Purchasing\Events;

use App\Domain\Purchasing\Models\GoodsReceivedNote;
use Illuminate\Foundation\Events\Dispatchable;

class GoodsReceived
{
    use Dispatchable;

    public function __construct(
        public readonly GoodsReceivedNote $goodsReceivedNote,
    ) {}
}
