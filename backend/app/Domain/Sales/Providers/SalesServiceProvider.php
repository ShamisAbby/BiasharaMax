<?php

namespace App\Domain\Sales\Providers;

use App\Domain\Sales\Events\SaleCompleted;
use App\Domain\Sales\Events\SaleReturnApproved;
use App\Domain\Sales\Events\SaleReturnRequested;
use App\Domain\Sales\Events\SaleVoided;
use App\Domain\Sales\Listeners\DeductInventoryOnSaleCompletion;
use App\Domain\Sales\Listeners\NotifyOwnerOfSaleReturnApproval;
use App\Domain\Sales\Listeners\NotifyOwnerOfSaleReturnRequested;
use App\Domain\Sales\Listeners\RestoreInventoryOnSaleVoided;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleReturn;
use App\Domain\Sales\Policies\CustomerPolicy;
use App\Domain\Sales\Policies\SalePolicy;
use App\Domain\Sales\Policies\SaleReturnPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class SalesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(SaleReturn::class, SaleReturnPolicy::class);

        Event::listen(SaleCompleted::class, DeductInventoryOnSaleCompletion::class);
        Event::listen(SaleVoided::class, RestoreInventoryOnSaleVoided::class);
        Event::listen(SaleReturnRequested::class, NotifyOwnerOfSaleReturnRequested::class);
        Event::listen(SaleReturnApproved::class, NotifyOwnerOfSaleReturnApproval::class);
    }
}
