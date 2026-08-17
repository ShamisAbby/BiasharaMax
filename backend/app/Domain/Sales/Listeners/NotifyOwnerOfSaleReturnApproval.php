<?php

namespace App\Domain\Sales\Listeners;

use App\Domain\Sales\Events\SaleReturnApproved;
use App\Domain\Sales\Notifications\SaleReturnApprovedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyOwnerOfSaleReturnApproval implements ShouldQueue
{
    public function handle(SaleReturnApproved $event): void
    {
        $owner = $event->saleReturn->business?->owner;

        $owner?->notify(new SaleReturnApprovedNotification($event->saleReturn));
    }
}
