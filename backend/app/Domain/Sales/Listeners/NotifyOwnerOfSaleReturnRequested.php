<?php

namespace App\Domain\Sales\Listeners;

use App\Domain\Sales\Events\SaleReturnRequested;
use App\Domain\Sales\Notifications\SaleReturnRequestedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyOwnerOfSaleReturnRequested implements ShouldQueue
{
    public function handle(SaleReturnRequested $event): void
    {
        $owner = $event->saleReturn->business?->owner;

        $owner?->notify(new SaleReturnRequestedNotification($event->saleReturn));
    }
}
