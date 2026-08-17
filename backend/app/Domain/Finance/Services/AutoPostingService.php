<?php

namespace App\Domain\Finance\Services;

use App\Domain\Accounting\Models\Expense;
use App\Domain\Accounting\Models\Income;
use App\Domain\Finance\Models\Account;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Purchasing\Models\GoodsReceivedNote;
use App\Domain\Purchasing\Models\SupplierDebtTransaction;
use App\Domain\Purchasing\Models\SupplierPayment;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleItem;
use App\Domain\Sales\Models\SalePayment;
use App\Domain\Sales\Models\SaleReturn;
use App\Domain\Sales\Models\SaleReturnItem;
use App\Domain\Shared\ValueObjects\Money;

/**
 * Converts business-domain events into balanced, posted GL journal entries.
 * Called synchronously from Finance listeners so posting is atomic with the
 * source transaction — a failed post rolls back the whole operation rather
 * than leaving the GL silently out of sync.
 *
 * All methods return null (and silently skip) when the business has not yet
 * had its Chart of Accounts seeded, so existing businesses are not broken
 * mid-transaction before the backfill command runs.
 */
class AutoPostingService
{
    public function __construct(
        private readonly ChartOfAccountsService $accounts,
        private readonly JournalPostingService $journal,
    ) {}

    public function postSaleCompleted(Sale $sale): ?JournalEntry
    {
        if (! $this->businessHasAccounts($sale->business_id)) {
            return null;
        }

        $sale->loadMissing(['items', 'payments']);

        if (bccomp((string) $sale->total_amount, '0', 2) <= 0) {
            return null;
        }

        $cash = $this->accounts->resolveSystemAccount($sale->business_id, ChartOfAccountsService::KEY_CASH);
        $bank = $this->accounts->resolveSystemAccount($sale->business_id, ChartOfAccountsService::KEY_BANK);
        $ar = $this->accounts->resolveSystemAccount($sale->business_id, ChartOfAccountsService::KEY_ACCOUNTS_RECEIVABLE);
        $revenue = $this->accounts->resolveSystemAccount($sale->business_id, ChartOfAccountsService::KEY_SALES_REVENUE);
        $taxPayable = $this->accounts->resolveSystemAccount($sale->business_id, ChartOfAccountsService::KEY_SALES_TAX_PAYABLE);
        $cogs = $this->accounts->resolveSystemAccount($sale->business_id, ChartOfAccountsService::KEY_COST_OF_GOODS_SOLD);
        $inventory = $this->accounts->resolveSystemAccount($sale->business_id, ChartOfAccountsService::KEY_INVENTORY);

        $lines = [];

        foreach ($sale->payments as $payment) {
            $account = $payment->payment_method === 'bank_transfer' ? $bank : $cash;
            $lines[] = ['account_id' => $account->id, 'debit' => (string) $payment->amount, 'description' => "Sale {$sale->sale_number}"];
        }

        if (bccomp((string) $sale->balance_due, '0', 2) > 0) {
            $lines[] = [
                'account_id' => $ar->id,
                'debit' => (string) $sale->balance_due,
                'customer_id' => $sale->customer_id,
                'description' => "Credit sale {$sale->sale_number}",
            ];
        }

        $netRevenue = bcsub((string) $sale->subtotal, (string) $sale->discount_amount, 2);
        if (bccomp($netRevenue, '0', 2) > 0) {
            $lines[] = ['account_id' => $revenue->id, 'credit' => $netRevenue, 'description' => "Revenue — {$sale->sale_number}"];
        }

        if (bccomp((string) $sale->tax_amount, '0', 2) > 0) {
            $lines[] = ['account_id' => $taxPayable->id, 'credit' => (string) $sale->tax_amount, 'description' => "Tax — {$sale->sale_number}"];
        }

        $cogsAmount = $sale->items->reduce(fn (string $carry, SaleItem $item) => bcadd(
            $carry,
            bcmul((string) $item->quantity, (string) ($item->unit_cost ?? '0'), 2),
            2,
        ), '0.00');

        if (bccomp($cogsAmount, '0', 2) > 0) {
            $lines[] = ['account_id' => $cogs->id, 'debit' => $cogsAmount, 'description' => "COGS — {$sale->sale_number}"];
            $lines[] = ['account_id' => $inventory->id, 'credit' => $cogsAmount, 'description' => "COGS — {$sale->sale_number}"];
        }

        if (count($lines) < 2) {
            return null;
        }

        return $this->journal->postImmediately($sale->business_id, [
            'entry_date' => now()->toDateString(),
            'type' => JournalEntry::TYPE_AUTO,
            'description' => "Sale {$sale->sale_number}",
            'source_id' => $sale->id,
            'source_type' => Sale::class,
        ], $lines);
    }

    public function postSalePayment(SalePayment $payment): ?JournalEntry
    {
        if (! $this->businessHasAccounts($payment->business_id)) {
            return null;
        }

        $sale = $payment->sale;
        $ar = $this->accounts->resolveSystemAccount($payment->business_id, ChartOfAccountsService::KEY_ACCOUNTS_RECEIVABLE);
        $cashOrBank = $this->resolveCashOrBank($payment->business_id, $payment->payment_method);

        return $this->journal->postImmediately($payment->business_id, [
            'entry_date' => now()->toDateString(),
            'type' => JournalEntry::TYPE_AUTO,
            'description' => "Payment against sale {$sale?->sale_number}",
            'source_id' => $payment->id,
            'source_type' => SalePayment::class,
        ], [
            ['account_id' => $cashOrBank->id, 'debit' => (string) $payment->amount, 'description' => "Cash receipt — sale {$sale?->sale_number}"],
            ['account_id' => $ar->id, 'credit' => (string) $payment->amount, 'customer_id' => $payment->customer_id, 'description' => "AR settled — sale {$sale?->sale_number}"],
        ]);
    }

    public function postSaleVoided(Sale $sale): ?JournalEntry
    {
        if (! $this->businessHasAccounts($sale->business_id)) {
            return null;
        }

        $original = JournalEntry::query()
            ->where('business_id', $sale->business_id)
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->where('status', JournalEntry::STATUS_POSTED)
            ->first();

        if (! $original) {
            return null;
        }

        return $this->journal->reverse($original, $sale->voided_by, "Sale voided: {$sale->void_reason}");
    }

    public function postSaleReturnApproved(SaleReturn $saleReturn): ?JournalEntry
    {
        if (! $this->businessHasAccounts($saleReturn->business_id)) {
            return null;
        }

        $saleReturn->loadMissing(['items.saleItem']);

        $lines = [];

        $restockValue = $saleReturn->items->reduce(function (string $carry, SaleReturnItem $item): string {
            if ($item->restock && $item->condition === SaleReturnItem::CONDITION_GOOD) {
                return bcadd(
                    $carry,
                    bcmul((string) $item->quantity_returned, (string) ($item->saleItem?->unit_cost ?? '0'), 2),
                    2,
                );
            }

            return $carry;
        }, '0.00');

        if (bccomp($restockValue, '0', 2) > 0) {
            $inventory = $this->accounts->resolveSystemAccount($saleReturn->business_id, ChartOfAccountsService::KEY_INVENTORY);
            $cogs = $this->accounts->resolveSystemAccount($saleReturn->business_id, ChartOfAccountsService::KEY_COST_OF_GOODS_SOLD);
            $lines[] = ['account_id' => $inventory->id, 'debit' => $restockValue, 'description' => "Inventory restock — {$saleReturn->return_number}"];
            $lines[] = ['account_id' => $cogs->id, 'credit' => $restockValue, 'description' => "COGS reversal — {$saleReturn->return_number}"];
        }

        if ($saleReturn->refund_method && bccomp((string) $saleReturn->refund_amount, '0', 2) > 0) {
            $salesReturns = $this->accounts->resolveSystemAccount($saleReturn->business_id, ChartOfAccountsService::KEY_SALES_RETURNS);

            if ($saleReturn->refund_method === SaleReturn::REFUND_STORE_CREDIT) {
                $ar = $this->accounts->resolveSystemAccount($saleReturn->business_id, ChartOfAccountsService::KEY_ACCOUNTS_RECEIVABLE);
                $lines[] = ['account_id' => $salesReturns->id, 'debit' => (string) $saleReturn->refund_amount, 'description' => "Return {$saleReturn->return_number}"];
                $lines[] = ['account_id' => $ar->id, 'credit' => (string) $saleReturn->refund_amount, 'customer_id' => $saleReturn->customer_id, 'description' => "Store credit — {$saleReturn->return_number}"];
            } else {
                $cashOrBank = $this->resolveCashOrBank($saleReturn->business_id, $saleReturn->refund_method);
                $lines[] = ['account_id' => $salesReturns->id, 'debit' => (string) $saleReturn->refund_amount, 'description' => "Return {$saleReturn->return_number}"];
                $lines[] = ['account_id' => $cashOrBank->id, 'credit' => (string) $saleReturn->refund_amount, 'description' => "Refund — {$saleReturn->return_number}"];
            }
        }

        if (count($lines) < 2) {
            return null;
        }

        return $this->journal->postImmediately($saleReturn->business_id, [
            'entry_date' => now()->toDateString(),
            'type' => JournalEntry::TYPE_AUTO,
            'description' => "Sale return {$saleReturn->return_number}",
            'source_id' => $saleReturn->id,
            'source_type' => SaleReturn::class,
        ], $lines);
    }

    public function postExpensePaid(Expense $expense): ?JournalEntry
    {
        if (! $this->businessHasAccounts($expense->business_id)) {
            return null;
        }

        if (bccomp((string) $expense->amount, '0', 2) <= 0) {
            return null;
        }

        $expenseAccount = $expense->expense_category_id
            ? $this->accounts->resolveExpenseCategoryAccount($expense->business_id, $expense->expense_category_id)
            : $this->accounts->resolveSystemAccount($expense->business_id, ChartOfAccountsService::KEY_GENERAL_EXPENSE);

        $cashOrBank = $this->resolveCashOrBank($expense->business_id, $expense->payment_method ?? 'cash');

        return $this->journal->postImmediately($expense->business_id, [
            'entry_date' => now()->toDateString(),
            'type' => JournalEntry::TYPE_AUTO,
            'description' => $expense->title,
            'source_id' => $expense->id,
            'source_type' => Expense::class,
        ], [
            ['account_id' => $expenseAccount->id, 'debit' => (string) $expense->amount, 'description' => $expense->title],
            ['account_id' => $cashOrBank->id, 'credit' => (string) $expense->amount, 'description' => $expense->title],
        ]);
    }

    public function postIncomeRecorded(Income $income): ?JournalEntry
    {
        if (! $this->businessHasAccounts($income->business_id)) {
            return null;
        }

        if (bccomp((string) $income->amount, '0', 2) <= 0) {
            return null;
        }

        $cashOrBank = $this->resolveCashOrBank($income->business_id, $income->payment_method ?? 'cash');
        $otherIncome = $this->accounts->resolveSystemAccount($income->business_id, ChartOfAccountsService::KEY_OTHER_INCOME);

        return $this->journal->postImmediately($income->business_id, [
            'entry_date' => now()->toDateString(),
            'type' => JournalEntry::TYPE_AUTO,
            'description' => $income->title,
            'source_id' => $income->id,
            'source_type' => Income::class,
        ], [
            ['account_id' => $cashOrBank->id, 'debit' => (string) $income->amount, 'description' => $income->title],
            ['account_id' => $otherIncome->id, 'credit' => (string) $income->amount, 'description' => $income->title],
        ]);
    }

    /**
     * Handles the goods-received event: recognizes the AP liability (Debit
     * Inventory, Credit Accounts Payable) and updates the AP subsidiary
     * ledger (PurchaseOrder.balance_due + Supplier.current_balance +
     * SupplierDebtTransaction) to match the SaleService "chargeCustomer"
     * pattern on the AR side.
     *
     * The bill amount mirrors GoodsReceivedService::recordExpense(): all
     * processed units (received + damaged + rejected) at unit_cost, since
     * that is what is owed to the supplier regardless of condition.
     */
    public function postGoodsReceived(GoodsReceivedNote $grn): ?JournalEntry
    {
        if (! $this->businessHasAccounts($grn->business_id)) {
            return null;
        }

        $grn->loadMissing(['items.purchaseOrderItem', 'purchaseOrder.supplier']);
        $purchaseOrder = $grn->purchaseOrder;

        if (! $purchaseOrder) {
            return null;
        }

        $billAmount = $grn->items->reduce(function (string $carry, $item): string {
            $unitCost = (string) ($item->purchaseOrderItem?->unit_cost ?? '0');

            return bcadd(
                $carry,
                bcmul($item->totalProcessedQuantity(), $unitCost, 2),
                2,
            );
        }, '0.00');

        if (bccomp($billAmount, '0', 2) <= 0) {
            return null;
        }

        $this->chargeSupplierBill($purchaseOrder, $grn, $billAmount);

        $inventoryAccount = $this->accounts->resolveSystemAccount($grn->business_id, ChartOfAccountsService::KEY_INVENTORY);
        $apAccount = $this->accounts->resolveSystemAccount($grn->business_id, ChartOfAccountsService::KEY_ACCOUNTS_PAYABLE);

        return $this->journal->postImmediately($grn->business_id, [
            'entry_date' => now()->toDateString(),
            'type' => JournalEntry::TYPE_AUTO,
            'description' => "Goods received — {$purchaseOrder->po_number} ({$grn->grn_number})",
            'source_id' => $grn->id,
            'source_type' => GoodsReceivedNote::class,
        ], [
            ['account_id' => $inventoryAccount->id, 'debit' => $billAmount, 'supplier_id' => $purchaseOrder->supplier_id, 'description' => "Inventory in — {$grn->grn_number}"],
            ['account_id' => $apAccount->id, 'credit' => $billAmount, 'supplier_id' => $purchaseOrder->supplier_id, 'description' => "AP — {$purchaseOrder->po_number}"],
        ]);
    }

    public function postSupplierPayment(SupplierPayment $payment): ?JournalEntry
    {
        if (! $this->businessHasAccounts($payment->business_id)) {
            return null;
        }

        $ap = $this->accounts->resolveSystemAccount($payment->business_id, ChartOfAccountsService::KEY_ACCOUNTS_PAYABLE);
        $cashOrBank = $this->resolveCashOrBank($payment->business_id, $payment->payment_method);
        $po = $payment->purchaseOrder;

        return $this->journal->postImmediately($payment->business_id, [
            'entry_date' => now()->toDateString(),
            'type' => JournalEntry::TYPE_AUTO,
            'description' => "Supplier payment — {$po?->po_number}",
            'source_id' => $payment->id,
            'source_type' => SupplierPayment::class,
        ], [
            ['account_id' => $ap->id, 'debit' => (string) $payment->amount, 'supplier_id' => $payment->supplier_id, 'description' => "AP settled — {$po?->po_number}"],
            ['account_id' => $cashOrBank->id, 'credit' => (string) $payment->amount, 'description' => "Payment to supplier — {$po?->po_number}"],
        ]);
    }

    private function chargeSupplierBill($purchaseOrder, GoodsReceivedNote $grn, string $billAmount): void
    {
        $currency = $purchaseOrder->supplier?->business?->currency ?? 'TZS';
        $billAmountMoney = Money::fromDecimal($billAmount, $currency);
        $supplier = $purchaseOrder->supplier;

        if ($supplier) {
            $balanceBefore = $supplier->currentBalanceMoney($currency);
            $balanceAfter = $balanceBefore->add($billAmountMoney);

            SupplierDebtTransaction::create([
                'business_id' => $purchaseOrder->business_id,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $purchaseOrder->id,
                'type' => SupplierDebtTransaction::TYPE_BILL,
                'amount' => $billAmountMoney->toDecimalString(),
                'amount_minor' => $billAmountMoney->minorUnits(),
                'balance_before' => $balanceBefore->toDecimalString(),
                'balance_before_minor' => $balanceBefore->minorUnits(),
                'balance_after' => $balanceAfter->toDecimalString(),
                'balance_after_minor' => $balanceAfter->minorUnits(),
                'notes' => "Goods received — {$grn->grn_number}",
                'created_by' => $grn->received_by,
            ]);

            $supplier->update([
                'current_balance' => $balanceAfter->toDecimalString(),
                'current_balance_minor' => $balanceAfter->minorUnits(),
            ]);
        }

        // As in SupplierPaymentService, balance_due/paid_amount are read
        // from decimal here since the order-creation path isn't cut over
        // yet — see the comment there for why.
        $newBalanceDueMoney = Money::fromDecimal($purchaseOrder->balance_due, $currency)->add($billAmountMoney);
        $paidAmountMoney = Money::fromDecimal($purchaseOrder->paid_amount, $currency);
        $paymentStatus = $newBalanceDueMoney->minorUnits() <= 0
            ? $purchaseOrder::PAYMENT_STATUS_PAID
            : ($paidAmountMoney->isPositive()
                ? $purchaseOrder::PAYMENT_STATUS_PARTIAL
                : $purchaseOrder::PAYMENT_STATUS_UNPAID);

        $purchaseOrder->update([
            'balance_due' => $newBalanceDueMoney->toDecimalString(),
            'balance_due_minor' => $newBalanceDueMoney->minorUnits(),
            'payment_status' => $paymentStatus,
        ]);
    }

    private function resolveCashOrBank(string $businessId, string $paymentMethod): Account
    {
        $key = $paymentMethod === 'bank_transfer'
            ? ChartOfAccountsService::KEY_BANK
            : ChartOfAccountsService::KEY_CASH;

        return $this->accounts->resolveSystemAccount($businessId, $key);
    }

    private function businessHasAccounts(string $businessId): bool
    {
        return Account::query()
            ->where('business_id', $businessId)
            ->where('is_system_default', true)
            ->exists();
    }
}
