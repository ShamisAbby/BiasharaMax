<?php

namespace App\Domain\Inventory\Listeners;

use App\Domain\Inventory\Events\LowStockDetected;
use App\Domain\Inventory\Notifications\LowStockAlert;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyOwnerOfLowStock implements ShouldQueue
{
    public function handle(LowStockDetected $event): void
    {
        $owner = $event->inventory->business?->owner;

        $owner?->notify(new LowStockAlert(collect([$event->inventory])));
    }
}
