<?php

namespace App\Domain\Website\Listeners;

use App\Domain\Business\Models\Business;
use App\Domain\Website\Events\ProductEnquiryReceived;
use App\Domain\Website\Notifications\ProductEnquiryReceivedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyOwnerOfProductEnquiry implements ShouldQueue
{
    public function handle(ProductEnquiryReceived $event): void
    {
        $owner = Business::query()->find($event->enquiry->business_id)?->owner;

        $owner?->notify(new ProductEnquiryReceivedNotification($event->enquiry));
    }
}
