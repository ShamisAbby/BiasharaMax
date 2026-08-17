<?php

namespace App\Domain\Inventory\Listeners;

use App\Domain\Inventory\Events\StockTransferCompleted;
use App\Domain\Inventory\Notifications\StockTransferCompletedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyOwnerOfTransferCompletion implements ShouldQueue
{
    public function handle(StockTransferCompleted $event): void
    {
        $owner = $event->stockTransfer->business?->owner;

        $owner?->notify(new StockTransferCompletedNotification($event->stockTransfer));
    }
}
