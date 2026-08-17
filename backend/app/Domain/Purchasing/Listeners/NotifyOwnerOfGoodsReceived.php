<?php

namespace App\Domain\Purchasing\Listeners;

use App\Domain\Purchasing\Events\GoodsReceived;
use App\Domain\Purchasing\Notifications\GoodsReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyOwnerOfGoodsReceived implements ShouldQueue
{
    public function handle(GoodsReceived $event): void
    {
        $owner = $event->goodsReceivedNote->business?->owner;

        $owner?->notify(new GoodsReceivedNotification($event->goodsReceivedNote));
    }
}
