<?php

namespace App\Domain\Inventory\Console\Commands;

use App\Domain\Business\Models\Business;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\ProductBatch;
use App\Domain\Inventory\Notifications\ExpiredProductAlert;
use App\Domain\Inventory\Notifications\LowStockAlert;
use Illuminate\Console\Command;

/**
 * Runs daily (see routes/console.php). Two passes per run:
 *  1. Find all active product batches that have expired — notify each
 *     business owner with a grouped list of expired items.
 *  2. Find all inventory records with quantity < LOW_STOCK_THRESHOLD
 *     that have not been caught by the real-time event (e.g. items that
 *     were already low before the threshold changed) — notify each owner.
 */
class CheckInventoryAlerts extends Command
{
    protected $signature = 'inventory:check-alerts
                            {--dry-run : Log what would be sent without dispatching notifications}';

    protected $description = 'Send expired-stock and low-stock alert emails to business owners.';

    public function handle(): int
    {
        $this->checkExpiredBatches();
        $this->checkLowStock();

        return self::SUCCESS;
    }

    private function checkExpiredBatches(): void
    {
        $expiredByBusiness = ProductBatch::query()
            ->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now())
            ->where('quantity', '>', 0)
            ->with('product')
            ->get()
            ->groupBy('business_id');

        if ($expiredByBusiness->isEmpty()) {
            $this->info('No expired batches found.');
            return;
        }

        $notified = 0;

        foreach ($expiredByBusiness as $businessId => $batches) {
            $owner = Business::with('owner')->find($businessId)?->owner;

            if (! $owner) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("[dry-run] Would notify {$owner->email} of {$batches->count()} expired batch(es).");
                continue;
            }

            $owner->notify(new ExpiredProductAlert($batches));
            $notified++;
        }

        $this->info("Expired-stock alerts sent to {$notified} business owner(s).");
    }

    private function checkLowStock(): void
    {
        $lowStockByBusiness = Inventory::query()
            ->where('quantity', '>', 0)
            ->where('quantity', '<', Inventory::LOW_STOCK_THRESHOLD)
            ->with(['product', 'warehouse'])
            ->get()
            ->groupBy('business_id');

        if ($lowStockByBusiness->isEmpty()) {
            $this->info('No low-stock inventory found.');
            return;
        }

        $notified = 0;

        foreach ($lowStockByBusiness as $businessId => $items) {
            $owner = Business::with('owner')->find($businessId)?->owner;

            if (! $owner) {
                continue;
            }

            if ($this->option('dry-run')) {
                $names = $items->map(fn ($i) => $i->product?->name)->filter()->implode(', ');
                $this->line("[dry-run] Would notify {$owner->email} of {$items->count()} low-stock item(s): {$names}.");
                continue;
            }

            $owner->notify(new LowStockAlert($items));
            $notified++;
        }

        $this->info("Low-stock digest alerts sent to {$notified} business owner(s).");
    }
}
