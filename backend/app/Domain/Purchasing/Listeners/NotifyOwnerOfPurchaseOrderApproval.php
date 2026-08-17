<?php

namespace App\Domain\Purchasing\Listeners;

use App\Domain\Purchasing\Events\PurchaseOrderApproved;
use App\Domain\Purchasing\Notifications\PurchaseOrderApprovedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyOwnerOfPurchaseOrderApproval implements ShouldQueue
{
    public function handle(PurchaseOrderApproved $event): void
    {
        $owner = $event->purchaseOrder->business?->owner;

        $owner?->notify(new PurchaseOrderApprovedNotification($event->purchaseOrder));
    }
}
