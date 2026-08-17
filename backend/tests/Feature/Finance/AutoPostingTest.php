<?php

namespace Tests\Feature\Finance;

use App\Domain\Accounting\Models\Expense;
use App\Domain\Accounting\Models\Income;
use App\Domain\Accounting\Services\ExpenseService;
use App\Domain\Accounting\Services\IncomeService;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Finance\Models\JournalEntry;
use App\Domain\Finance\Models\JournalLine;
use App\Domain\Finance\Services\ChartOfAccountsService;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\Purchasing\Models\GoodsReceivedNote;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Purchasing\Services\SupplierPaymentService;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SalePayment;
use App\Domain\Sales\Services\SalePaymentService;
use App\Domain\Sales\Services\SaleService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class AutoPostingTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    /**
     * @return array{0: Branch, 1: Warehouse, 2: Product}
     */
    private function makeStockedProduct(string $businessId, float $stock = 20, float $price = 1000, float $cost = 600): array
    {
        $branch = Branch::query()->where('business_id', $businessId)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $businessId)->firstOrFail();
        $product = Product::create([
            'business_id' => $businessId, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => $price, 'cost_price' => $cost,
        ]);
        Inventory::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => $stock]);

        return [$branch, $warehouse, $product];
    }

    private function entryFor(string $businessId, string $sourceType, string $sourceId): JournalEntry
    {
        return JournalEntry::query()
            ->where('business_id', $businessId)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->firstOrFail();
    }

    private function assertLinesBalanced(JournalEntry $entry): void
    {
        $totalDebit = $entry->lines->reduce(fn (string $c, JournalLine $l) => bcadd($c, (string) $l->debit, 2), '0.00');
        $totalCredit = $entry->lines->reduce(fn (string $c, JournalLine $l) => bcadd($c, (string) $l->credit, 2), '0.00');
        $this->assertSame(0, bccomp($totalDebit, $totalCredit, 2), "Entry {$entry->entry_number} is not balanced: debit {$totalDebit} vs credit {$totalCredit}");
    }

    public function test_cash_sale_posts_a_balanced_entry_to_cash_and_revenue(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id);
        $accounts = app(ChartOfAccountsService::class);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payments' => [['amount' => 5000, 'payment_method' => 'cash']],
            'sold_by' => $owner->id,
        ]);

        $entry = $this->entryFor($business->id, Sale::class, $sale->id);
        $this->assertSame(JournalEntry::STATUS_POSTED, $entry->status);
        $this->assertLinesBalanced($entry);

        $cash = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $revenue = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_SALES_REVENUE);
        $cogs = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_COST_OF_GOODS_SOLD);
        $inventory = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_INVENTORY);

        $this->assertSame('5000.00', (string) $entry->lines->firstWhere('account_id', $cash->id)->debit);
        $this->assertSame('5000.00', (string) $entry->lines->firstWhere('account_id', $revenue->id)->credit);
        $this->assertSame('3000.00', (string) $entry->lines->firstWhere('account_id', $cogs->id)->debit);
        $this->assertSame('3000.00', (string) $entry->lines->firstWhere('account_id', $inventory->id)->credit);

        // Existing side effects unchanged: paid in full, no AR impact.
        $this->assertSame('5000.00', $sale->fresh()->paid_amount);
        $this->assertSame('0.00', $sale->fresh()->balance_due);
        $this->assertSame('15.000', Inventory::where('product_id', $product->id)->first()->quantity);
    }

    public function test_credit_sale_posts_to_accounts_receivable_instead_of_cash(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id);
        $accounts = app(ChartOfAccountsService::class);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'customer_type' => Customer::TYPE_CREDIT, 'credit_limit' => 10000]);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'sold_by' => $owner->id,
        ]);

        $entry = $this->entryFor($business->id, Sale::class, $sale->id);
        $this->assertLinesBalanced($entry);

        $ar = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_ACCOUNTS_RECEIVABLE);
        $cash = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $arLine = $entry->lines->firstWhere('account_id', $ar->id);

        $this->assertNotNull($arLine);
        $this->assertSame('5000.00', (string) $arLine->debit);
        $this->assertSame($customer->id, $arLine->customer_id);
        $this->assertNull($entry->lines->firstWhere('account_id', $cash->id));

        // Existing side effect unchanged: customer charged.
        $this->assertSame('5000.00', $customer->fresh()->current_balance);
    }

    public function test_payment_against_a_credit_sale_posts_ar_to_cash(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id);
        $accounts = app(ChartOfAccountsService::class);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'customer_type' => Customer::TYPE_CREDIT, 'credit_limit' => 10000]);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'sold_by' => $owner->id,
        ]);

        $payment = app(SalePaymentService::class)->record($sale, ['amount' => 2000, 'payment_method' => 'cash', 'received_by' => $owner->id]);

        $entry = $this->entryFor($business->id, SalePayment::class, $payment->id);
        $this->assertLinesBalanced($entry);

        $cash = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $ar = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_ACCOUNTS_RECEIVABLE);

        $this->assertSame('2000.00', (string) $entry->lines->firstWhere('account_id', $cash->id)->debit);
        $this->assertSame('2000.00', (string) $entry->lines->firstWhere('account_id', $ar->id)->credit);

        // Existing side effects unchanged.
        $this->assertSame('2000.00', $sale->fresh()->paid_amount);
        $this->assertSame('3000.00', $sale->fresh()->balance_due);
        $this->assertSame('3000.00', $customer->fresh()->current_balance);
    }

    public function test_marking_an_expense_paid_posts_expense_to_cash(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $accounts = app(ChartOfAccountsService::class);

        $expense = app(ExpenseService::class)->create([
            'business_id' => $business->id, 'title' => 'Office Rent', 'amount' => 1500,
            'expense_date' => now()->toDateString(), 'payment_method' => 'cash', 'status' => Expense::STATUS_APPROVED,
        ]);
        $expense = app(ExpenseService::class)->markPaid($expense);

        $entry = $this->entryFor($business->id, Expense::class, $expense->id);
        $this->assertLinesBalanced($entry);

        $cash = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $generalExpense = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_GENERAL_EXPENSE);

        $this->assertSame('1500.00', (string) $entry->lines->firstWhere('account_id', $generalExpense->id)->debit);
        $this->assertSame('1500.00', (string) $entry->lines->firstWhere('account_id', $cash->id)->credit);

        // Existing side effect unchanged.
        $this->assertSame(Expense::STATUS_PAID, $expense->fresh()->status);
    }

    public function test_recording_income_posts_cash_to_other_income(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $accounts = app(ChartOfAccountsService::class);

        $income = app(IncomeService::class)->create([
            'business_id' => $business->id, 'title' => 'Consulting Fee', 'amount' => 800,
            'income_date' => now()->toDateString(), 'payment_method' => 'cash', 'category' => Income::CATEGORY_SERVICE,
        ]);

        $entry = $this->entryFor($business->id, Income::class, $income->id);
        $this->assertLinesBalanced($entry);

        $cash = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_CASH);
        $otherIncome = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_OTHER_INCOME);

        $this->assertSame('800.00', (string) $entry->lines->firstWhere('account_id', $cash->id)->debit);
        $this->assertSame('800.00', (string) $entry->lines->firstWhere('account_id', $otherIncome->id)->credit);

        // Existing side effect unchanged.
        $this->assertSame('800.00', $income->fresh()->amount);
    }

    public function test_goods_received_posts_inventory_to_accounts_payable_and_charges_supplier(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $business->id)->firstOrFail();
        $supplier = Supplier::create(['business_id' => $business->id, 'name' => 'Acme Supplies', 'status' => Supplier::STATUS_ACTIVE]);
        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'cost_price' => 600,
        ]);
        $accounts = app(ChartOfAccountsService::class);

        $po = PurchaseOrder::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id, 'po_number' => 'PO-FIN-1', 'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_SENT, 'sent_at' => now(),
        ]);
        $item = $po->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'quantity_ordered' => 10, 'unit_cost' => 500, 'line_total' => 5000,
        ]);

        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/receive", [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['purchase_order_item_id' => $item->id, 'quantity_received' => 10],
            ],
        ])->assertSessionHasNoErrors();

        $grn = GoodsReceivedNote::query()->where('purchase_order_id', $po->id)->firstOrFail();
        $entry = $this->entryFor($business->id, GoodsReceivedNote::class, $grn->id);
        $this->assertLinesBalanced($entry);

        $inventoryAccount = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_INVENTORY);
        $ap = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_ACCOUNTS_PAYABLE);

        $this->assertSame('5000.00', (string) $entry->lines->firstWhere('account_id', $inventoryAccount->id)->debit);
        $this->assertSame('5000.00', (string) $entry->lines->firstWhere('account_id', $ap->id)->credit);

        // New AP subsidiary-ledger behavior (gap closed by this feature).
        $this->assertSame('5000.00', $po->fresh()->balance_due);
        $this->assertSame(PurchaseOrder::PAYMENT_STATUS_UNPAID, $po->fresh()->payment_status);
        $this->assertSame('5000.00', $supplier->fresh()->current_balance);

        // Existing side effects unchanged: inventory updated, expense still recorded.
        $this->assertSame('10.000', Inventory::where('product_id', $product->id)->where('warehouse_id', $warehouse->id)->first()->quantity);
        $this->assertNotNull(Expense::query()->where('business_id', $business->id)->where('supplier_id', $supplier->id)->first());
    }

    public function test_supplier_payment_posts_accounts_payable_to_cash(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $business->id)->firstOrFail();
        $supplier = Supplier::create(['business_id' => $business->id, 'name' => 'Acme Supplies', 'status' => Supplier::STATUS_ACTIVE]);
        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'cost_price' => 600,
        ]);
        $accounts = app(ChartOfAccountsService::class);

        $po = PurchaseOrder::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id, 'po_number' => 'PO-FIN-2', 'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_SENT, 'sent_at' => now(),
        ]);
        $item = $po->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name,
            'quantity_ordered' => 10, 'unit_cost' => 500, 'line_total' => 5000,
        ]);
        $this->actingAs($owner)->post("/purchasing/orders/{$po->id}/receive", [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'items' => [['purchase_order_item_id' => $item->id, 'quantity_received' => 10]],
        ])->assertSessionHasNoErrors();
        $po->refresh();

        $payment = app(SupplierPaymentService::class)->record($po, ['amount' => 2000, 'payment_method' => 'bank_transfer', 'paid_by' => $owner->id]);

        $entry = $this->entryFor($business->id, \App\Domain\Purchasing\Models\SupplierPayment::class, $payment->id);
        $this->assertLinesBalanced($entry);

        $ap = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_ACCOUNTS_PAYABLE);
        $bank = $accounts->resolveSystemAccount($business->id, ChartOfAccountsService::KEY_BANK);

        $this->assertSame('2000.00', (string) $entry->lines->firstWhere('account_id', $ap->id)->debit);
        $this->assertSame('2000.00', (string) $entry->lines->firstWhere('account_id', $bank->id)->credit);

        // Existing side effects unchanged.
        $this->assertSame('3000.00', $po->fresh()->balance_due);
        $this->assertSame('3000.00', $supplier->fresh()->current_balance);
    }

    public function test_voiding_a_sale_posts_a_reversing_journal_entry(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payments' => [['amount' => 5000, 'payment_method' => 'cash']],
            'sold_by' => $owner->id,
        ]);

        $original = $this->entryFor($business->id, Sale::class, $sale->id);
        $originalLines = $original->lines->map(fn (JournalLine $l) => ['account_id' => $l->account_id, 'debit' => (string) $l->debit, 'credit' => (string) $l->credit])->all();

        app(SaleService::class)->void($sale, 'Customer changed mind', $owner->id);

        $original = $original->fresh('lines');
        $this->assertSame(JournalEntry::STATUS_REVERSED, $original->status);
        $this->assertNotNull($original->reversed_journal_entry_id);

        $reversal = JournalEntry::query()->findOrFail($original->reversed_journal_entry_id);
        $this->assertSame($original->id, $reversal->reversal_of_id);
        $this->assertSame(JournalEntry::STATUS_POSTED, $reversal->status);
        $this->assertLinesBalanced($reversal);

        foreach ($originalLines as $line) {
            $reversalLine = $reversal->lines->firstWhere('account_id', $line['account_id']);
            $this->assertNotNull($reversalLine);
            $this->assertSame($line['credit'], (string) $reversalLine->debit);
            $this->assertSame($line['debit'], (string) $reversalLine->credit);
        }

        // Existing side effect unchanged.
        $this->assertSame(Sale::STATUS_VOIDED, $sale->fresh()->status);
    }
}
