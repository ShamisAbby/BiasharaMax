<?php

namespace App\Domain\Sales\Services;

use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Services\StockMovementService;
use App\Domain\Sales\Events\SaleReturnApproved;
use App\Domain\Sales\Events\SaleReturnRequested;
use App\Domain\Sales\Exceptions\SaleReturnException;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\CustomerDebtTransaction;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleItem;
use App\Domain\Sales\Models\SaleReturn;
use App\Domain\Sales\Models\SaleReturnItem;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The single entry point for the return lifecycle: pending -> approved
 * (which restocks real inventory for good-condition items via
 * StockMovementService and, for store-credit refunds, adjusts the real
 * customer debt ledger) or pending -> rejected (no side effects at all).
 * Accounting's reversal of revenue/cash is handled by FinancialReportService
 * reading approved returns live, not by this service writing a journal
 * entry — there's no separate "refund expense" to fabricate.
 */
class SaleReturnService
{
    public function __construct(
        private readonly StockMovementService $stockMovements,
    ) {}

    /**
     * @param  array{
     *     business_id: string,
     *     sale_id: string,
     *     customer_id?: ?string,
     *     reason: string,
     *     refund_method?: ?string,
     *     notes?: ?string,
     *     created_by?: ?string,
     *     items: array<int, array{sale_item_id: string, quantity_returned: float|string, condition?: ?string, restock?: ?bool, notes?: ?string}>,
     * }  $data
     *
     * @throws SaleReturnException
     */
    public function create(array $data): SaleReturn
    {
        return DB::transaction(function () use ($data) {
            $sale = Sale::query()->where('id', $data['sale_id'])->lockForUpdate()->firstOrFail();

            if ($sale->status !== Sale::STATUS_COMPLETED) {
                throw SaleReturnException::saleNotEligible($sale->status);
            }

            $saleReturn = SaleReturn::create([
                'business_id' => $data['business_id'],
                'sale_id' => $sale->id,
                'customer_id' => $data['customer_id'] ?? $sale->customer_id,
                'branch_id' => $sale->branch_id,
                'warehouse_id' => $sale->warehouse_id,
                'return_number' => $this->generateReturnNumber($data['business_id']),
                'reason' => $data['reason'],
                'refund_method' => $data['refund_method'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            $refundTotal = '0.00';

            foreach ($data['items'] as $itemData) {
                $refundTotal = bcadd($refundTotal, $this->buildReturnItem($saleReturn, $sale, $itemData), 2);
            }

            $saleReturn->update(['refund_amount' => $refundTotal]);

            SaleReturnRequested::dispatch($saleReturn->refresh()->load('items'));

            return $saleReturn;
        });
    }

    /**
     * @throws SaleReturnException
     */
    public function approve(SaleReturn $saleReturn, string $approvedBy): SaleReturn
    {
        if ($saleReturn->status !== SaleReturn::STATUS_PENDING) {
            throw SaleReturnException::invalidTransition($saleReturn->status, 'approved');
        }

        return DB::transaction(function () use ($saleReturn, $approvedBy) {
            $saleReturn->load('items.saleItem');

            foreach ($saleReturn->items as $item) {
                if ($item->restock && $item->condition === SaleReturnItem::CONDITION_GOOD) {
                    $this->stockMovements->record([
                        'business_id' => $saleReturn->business_id,
                        'branch_id' => $saleReturn->branch_id,
                        'warehouse_id' => $saleReturn->warehouse_id,
                        'product_id' => $item->product_id,
                        'product_variant_id' => $item->product_variant_id,
                        'product_batch_id' => $item->product_batch_id,
                        'type' => StockMovement::TYPE_RETURN_IN,
                        'direction' => StockMovement::DIRECTION_IN,
                        'quantity' => $item->quantity_returned,
                        'unit_cost' => $item->saleItem?->unit_cost,
                        'reference' => $saleReturn,
                        'notes' => "Return {$saleReturn->return_number}",
                        'created_by' => $approvedBy,
                    ]);
                }
            }

            $saleReturn->update([
                'status' => SaleReturn::STATUS_APPROVED,
                'approved_by' => $approvedBy,
                'approved_at' => now(),
                'rejection_reason' => null,
            ]);

            if ($saleReturn->customer_id && $saleReturn->refund_method === SaleReturn::REFUND_STORE_CREDIT) {
                $this->creditCustomer($saleReturn->refresh());
            }

            SaleReturnApproved::dispatch($saleReturn->refresh());

            return $saleReturn;
        });
    }

    /**
     * @throws SaleReturnException
     */
    public function reject(SaleReturn $saleReturn, string $rejectedBy, string $reason): SaleReturn
    {
        if ($saleReturn->status !== SaleReturn::STATUS_PENDING) {
            throw SaleReturnException::invalidTransition($saleReturn->status, 'rejected');
        }

        $saleReturn->update([
            'status' => SaleReturn::STATUS_REJECTED,
            'approved_by' => $rejectedBy,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $saleReturn->refresh();
    }

    /**
     * @param  array<string, mixed>  $itemData
     *
     * @throws SaleReturnException
     */
    private function buildReturnItem(SaleReturn $saleReturn, Sale $sale, array $itemData): string
    {
        $saleItem = SaleItem::query()
            ->where('id', $itemData['sale_item_id'])
            ->where('sale_id', $sale->id)
            ->first();

        if (! $saleItem) {
            throw SaleReturnException::itemNotOnSale();
        }

        $alreadyReturned = SaleReturnItem::query()
            ->where('sale_item_id', $saleItem->id)
            ->whereHas('saleReturn', fn ($query) => $query->where('status', '!=', SaleReturn::STATUS_REJECTED))
            ->sum('quantity_returned');

        $available = bcsub((string) $saleItem->quantity, (string) $alreadyReturned, 3);
        $quantity = (string) $itemData['quantity_returned'];

        if (bccomp($quantity, $available, 3) > 0) {
            throw SaleReturnException::overReturn($saleItem->product_name, $available, $quantity);
        }

        $lineRefund = bcmul($quantity, (string) $saleItem->unit_price, 2);

        $saleReturn->items()->create([
            'sale_item_id' => $saleItem->id,
            'product_id' => $saleItem->product_id,
            'product_variant_id' => $saleItem->product_variant_id,
            'product_batch_id' => $saleItem->product_batch_id,
            'quantity_returned' => $quantity,
            'condition' => $itemData['condition'] ?? SaleReturnItem::CONDITION_GOOD,
            'restock' => $itemData['restock'] ?? true,
            'unit_price' => $saleItem->unit_price,
            'line_refund_amount' => $lineRefund,
            'notes' => $itemData['notes'] ?? null,
        ]);

        return $lineRefund;
    }

    private function creditCustomer(SaleReturn $saleReturn): void
    {
        $customer = Customer::query()->find($saleReturn->customer_id);

        if (! $customer) {
            return;
        }

        $currency = $customer->business?->currency ?? 'TZS';
        $refundAmount = Money::fromDecimal($saleReturn->refund_amount, $currency);
        $balanceBefore = $customer->currentBalanceMoney($currency);
        $balanceAfter = $balanceBefore->subtract($refundAmount);
        $reversalAmount = Money::fromMinorUnits(-$refundAmount->minorUnits(), $currency);

        CustomerDebtTransaction::create([
            'business_id' => $saleReturn->business_id,
            'customer_id' => $customer->id,
            'sale_id' => $saleReturn->sale_id,
            'type' => CustomerDebtTransaction::TYPE_ADJUSTMENT,
            'amount' => $reversalAmount->toDecimalString(),
            'amount_minor' => $reversalAmount->minorUnits(),
            'balance_before' => $balanceBefore->toDecimalString(),
            'balance_before_minor' => $balanceBefore->minorUnits(),
            'balance_after' => $balanceAfter->toDecimalString(),
            'balance_after_minor' => $balanceAfter->minorUnits(),
            'notes' => "Store credit for return {$saleReturn->return_number}",
            'created_by' => $saleReturn->approved_by,
        ]);

        $customer->update([
            'current_balance' => $balanceAfter->toDecimalString(),
            'current_balance_minor' => $balanceAfter->minorUnits(),
        ]);
    }

    private function generateReturnNumber(string $businessId): string
    {
        do {
            $candidate = 'RET-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
        } while (SaleReturn::query()->withTrashed()->where('business_id', $businessId)->where('return_number', $candidate)->exists());

        return $candidate;
    }
}
