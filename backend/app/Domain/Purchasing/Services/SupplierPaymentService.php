<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Purchasing\Events\SupplierPaymentRecorded;
use App\Domain\Purchasing\Exceptions\PurchaseOrderException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\SupplierDebtTransaction;
use App\Domain\Purchasing\Models\SupplierPayment;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Support\Facades\DB;

/**
 * Records a payment against an existing purchase order's outstanding
 * balance — the Accounts Payable mirror of SalePaymentService.
 */
class SupplierPaymentService
{
    /**
     * @param  array{amount: float|string, payment_method?: string, reference_number?: ?string, notes?: ?string, paid_by?: ?string}  $data
     *
     * @throws PurchaseOrderException
     */
    public function record(PurchaseOrder $purchaseOrder, array $data): SupplierPayment
    {
        return DB::transaction(function () use ($purchaseOrder, $data) {
            if ($purchaseOrder->status === PurchaseOrder::STATUS_CANCELLED) {
                throw PurchaseOrderException::cancelled();
            }

            $currency = $purchaseOrder->supplier?->business?->currency ?? 'TZS';
            $amount = (string) $data['amount'];
            $amountMoney = Money::fromDecimal($amount, $currency);

            if (bccomp($amount, (string) $purchaseOrder->balance_due, 2) > 0) {
                throw PurchaseOrderException::paymentExceedsBalance((string) $purchaseOrder->balance_due);
            }

            $payment = $purchaseOrder->payments()->create([
                'business_id' => $purchaseOrder->business_id,
                'supplier_id' => $purchaseOrder->supplier_id,
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? 'cash',
                'reference_number' => $data['reference_number'] ?? null,
                'paid_at' => now(),
                'notes' => $data['notes'] ?? null,
                'paid_by' => $data['paid_by'] ?? null,
            ]);

            // PurchaseOrder.paid_amount/balance_due are read from the
            // decimal columns (not _minor) here — the Purchasing context
            // (later in docs/ADR/0002-money-format-migration.md's rollout
            // order) hasn't cut over the order-creation path that sets
            // these from total_amount yet, so decimal remains the trusted
            // source for "current state" until it does. Both columns are
            // still written below, so _minor stays correct going forward.
            $newPaidAmountMoney = Money::fromDecimal($purchaseOrder->paid_amount, $currency)->add($amountMoney);
            $newBalanceDueMoney = Money::fromDecimal($purchaseOrder->balance_due, $currency)->subtract($amountMoney);

            $purchaseOrder->update([
                'paid_amount' => $newPaidAmountMoney->toDecimalString(),
                'paid_amount_minor' => $newPaidAmountMoney->minorUnits(),
                'balance_due' => $newBalanceDueMoney->toDecimalString(),
                'balance_due_minor' => $newBalanceDueMoney->minorUnits(),
                'payment_status' => $newBalanceDueMoney->minorUnits() <= 0
                    ? PurchaseOrder::PAYMENT_STATUS_PAID
                    : PurchaseOrder::PAYMENT_STATUS_PARTIAL,
            ]);

            if ($purchaseOrder->supplier_id) {
                $supplier = $purchaseOrder->supplier;
                $balanceBefore = $supplier->currentBalanceMoney($currency);
                $balanceAfter = $balanceBefore->subtract($amountMoney);

                SupplierDebtTransaction::create([
                    'business_id' => $purchaseOrder->business_id,
                    'supplier_id' => $supplier->id,
                    'purchase_order_id' => $purchaseOrder->id,
                    'supplier_payment_id' => $payment->id,
                    'type' => SupplierDebtTransaction::TYPE_PAYMENT,
                    'amount' => $amountMoney->toDecimalString(),
                    'amount_minor' => $amountMoney->minorUnits(),
                    'balance_before' => $balanceBefore->toDecimalString(),
                    'balance_before_minor' => $balanceBefore->minorUnits(),
                    'balance_after' => $balanceAfter->toDecimalString(),
                    'balance_after_minor' => $balanceAfter->minorUnits(),
                    'notes' => "Payment against purchase order {$purchaseOrder->po_number}",
                    'created_by' => $data['paid_by'] ?? null,
                ]);

                $supplier->update([
                    'current_balance' => $balanceAfter->toDecimalString(),
                    'current_balance_minor' => $balanceAfter->minorUnits(),
                ]);
            }

            SupplierPaymentRecorded::dispatch($payment);

            return $payment;
        });
    }
}
