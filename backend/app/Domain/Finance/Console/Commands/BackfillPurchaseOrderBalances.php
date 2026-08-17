<?php

namespace App\Domain\Finance\Console\Commands;

use App\Domain\Purchasing\Models\GoodsReceivedNote;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Purchasing\Models\SupplierDebtTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off backfill that sets balance_due/payment_status on PurchaseOrders
 * that received goods BEFORE AutoPostingService::chargeSupplierBill() was
 * wired (i.e. before Phase 1 shipped). Orders created/received after Phase 1
 * already have correct balances and are skipped (balance_due > 0 guard).
 *
 * Re-runnable: skips any PO that already has a non-zero balance_due, and
 * recomputes Supplier.current_balance from scratch at the end so it is always
 * consistent with the PO set.
 */
class BackfillPurchaseOrderBalances extends Command
{
    protected $signature = 'finance:backfill-po-balances
                            {business? : A specific business ID; omit to process every business}
                            {--dry-run : Print what would change without writing to the database}';

    protected $description = 'Set balance_due/payment_status on historically-received POs and recompute Supplier.current_balance.';

    public function handle(): int
    {
        $businessId = $this->argument('business');
        $dryRun = $this->option('dry-run');

        $poQuery = PurchaseOrder::query()
            ->whereIn('status', [
                PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
                PurchaseOrder::STATUS_FULLY_RECEIVED,
                PurchaseOrder::STATUS_CLOSED,
            ])
            ->where('balance_due', 0)
            ->with(['goodsReceivedNotes.items.purchaseOrderItem']);

        if ($businessId) {
            $poQuery->where('business_id', $businessId);
        }

        $orders = $poQuery->get();

        if ($orders->isEmpty()) {
            $this->info('No purchase orders need backfilling.');

            return self::SUCCESS;
        }

        $this->info("Found {$orders->count()} purchase order(s) to backfill.".($dryRun ? ' [DRY RUN]' : ''));

        DB::transaction(function () use ($orders, $dryRun) {
            $orders->each(function (PurchaseOrder $po) use ($dryRun) {
                $billed = $po->goodsReceivedNotes->reduce(function (string $carry, GoodsReceivedNote $grn): string {
                    return $grn->items->reduce(function (string $carry, $item): string {
                        $unitCost = (string) ($item->purchaseOrderItem?->unit_cost ?? '0');

                        return bcadd($carry, bcmul($item->totalProcessedQuantity(), $unitCost, 2), 2);
                    }, $carry);
                }, '0.00');

                // Fallback: no GRN data, use the full PO total as a conservative estimate.
                if (bccomp($billed, '0', 2) <= 0) {
                    $billed = (string) $po->total_amount;
                }

                if (bccomp($billed, '0', 2) <= 0) {
                    return;
                }

                $newBalanceDue = $billed;
                $newPaymentStatus = PurchaseOrder::PAYMENT_STATUS_UNPAID;

                $this->line("  PO {$po->po_number}: balance_due → {$newBalanceDue}");

                if (! $dryRun) {
                    $po->update([
                        'balance_due' => $newBalanceDue,
                        'payment_status' => $newPaymentStatus,
                    ]);
                }
            });
        });

        // Recompute Supplier.current_balance = sum of all their PO balance_due values.
        $supplierQuery = Supplier::query()
            ->with(['purchaseOrders' => fn ($q) => $q->whereNotIn('status', [
                PurchaseOrder::STATUS_CANCELLED,
                PurchaseOrder::STATUS_REJECTED,
            ])]);

        if ($businessId) {
            $supplierQuery->where('business_id', $businessId);
        }

        $supplierQuery->get()->each(function (Supplier $supplier) use ($dryRun) {
            $newBalance = $supplier->purchaseOrders->reduce(
                fn (string $carry, PurchaseOrder $po) => bcadd($carry, (string) $po->balance_due, 2),
                '0.00',
            );

            if (bccomp($newBalance, (string) $supplier->current_balance, 2) === 0) {
                return;
            }

            $this->line("  Supplier \"{$supplier->name}\": current_balance → {$newBalance}");

            if (! $dryRun) {
                $supplier->update(['current_balance' => $newBalance]);
            }
        });

        $this->info($dryRun ? 'Dry run complete — no changes written.' : 'Backfill complete.');

        return self::SUCCESS;
    }
}
