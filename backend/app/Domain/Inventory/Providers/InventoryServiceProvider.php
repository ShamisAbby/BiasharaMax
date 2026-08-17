<?php

namespace App\Domain\Inventory\Providers;

use App\Domain\Inventory\Events\BulkImportCompleted;
use App\Domain\Inventory\Events\LowStockDetected;
use App\Domain\Inventory\Events\StockTransferCompleted;
use App\Domain\Inventory\Listeners\NotifyOwnerOfLowStock;
use App\Domain\Inventory\Listeners\NotifyOwnerOfTransferCompletion;
use App\Domain\Inventory\Listeners\NotifyUserOfImportCompletion;
use App\Domain\Inventory\Models\Attribute;
use App\Domain\Inventory\Models\Brand;
use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Models\Collection as ProductCollection;
use App\Domain\Inventory\Models\InventoryCount;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Models\StockTransfer;
use App\Domain\Inventory\Models\Tag;
use App\Domain\Inventory\Models\Unit;
use App\Domain\Inventory\Policies\AttributePolicy;
use App\Domain\Inventory\Policies\BrandPolicy;
use App\Domain\Inventory\Policies\CategoryPolicy;
use App\Domain\Inventory\Policies\CollectionPolicy;
use App\Domain\Inventory\Policies\InventoryCountPolicy;
use App\Domain\Inventory\Policies\ProductPolicy;
use App\Domain\Inventory\Policies\StockAdjustmentPolicy;
use App\Domain\Inventory\Policies\StockTransferPolicy;
use App\Domain\Inventory\Policies\TagPolicy;
use App\Domain\Inventory\Policies\UnitPolicy;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Purchasing\Policies\SupplierPolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Tag::class, TagPolicy::class);
        Gate::policy(ProductCollection::class, CollectionPolicy::class);
        Gate::policy(Attribute::class, AttributePolicy::class);
        Gate::policy(StockAdjustment::class, StockAdjustmentPolicy::class);
        Gate::policy(StockTransfer::class, StockTransferPolicy::class);
        Gate::policy(InventoryCount::class, InventoryCountPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);

        Event::listen(LowStockDetected::class, NotifyOwnerOfLowStock::class);
        Event::listen(StockTransferCompleted::class, NotifyOwnerOfTransferCompletion::class);
        Event::listen(BulkImportCompleted::class, NotifyUserOfImportCompletion::class);
    }
}
