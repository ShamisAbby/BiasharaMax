<?php

namespace App\Domain\Website\Listeners;

use App\Domain\Sales\Events\SaleCompleted;
use App\Domain\Sales\Models\Sale;
use App\Domain\Website\Notifications\OnlineOrderConfirmationNotification;
use App\Domain\Website\Notifications\OnlineOrderPlacedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class NotifyOnOnlineOrderPlaced implements ShouldQueue
{
    public function handle(SaleCompleted $event): void
    {
        if ($event->sale->source !== Sale::SOURCE_ONLINE) {
            return;
        }

        $event->sale->loadMissing(['business.owner', 'customer']);

        $event->sale->business?->owner?->notify(new OnlineOrderPlacedNotification($event->sale));

        if ($event->sale->customer?->email) {
            Notification::route('mail', $event->sale->customer->email)
                ->notify(new OnlineOrderConfirmationNotification($event->sale));
        }
    }
}
