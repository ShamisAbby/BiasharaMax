<?php

namespace App\Domain\Purchasing\Providers;

use App\Domain\Purchasing\Events\GoodsReceived;
use App\Domain\Purchasing\Events\PurchaseOrderApproved;
use App\Domain\Purchasing\Listeners\NotifyOwnerOfGoodsReceived;
use App\Domain\Purchasing\Listeners\NotifyOwnerOfPurchaseOrderApproval;
use App\Domain\Purchasing\Models\GoodsReceivedNote;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Policies\GoodsReceivedNotePolicy;
use App\Domain\Purchasing\Policies\PurchaseOrderPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class PurchasingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(GoodsReceivedNote::class, GoodsReceivedNotePolicy::class);

        Event::listen(PurchaseOrderApproved::class, NotifyOwnerOfPurchaseOrderApproval::class);
        Event::listen(GoodsReceived::class, NotifyOwnerOfGoodsReceived::class);
    }
}
