<?php

namespace Tests\Feature\Crm;

use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\CRM\Services\LoyaltyTierService;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Purchasing\Models\SupplierDebtTransaction;
use App\Domain\Purchasing\Services\SupplierPaymentService;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\CustomerDebtTransaction;
use App\Domain\Sales\Models\SaleReturn;
use App\Domain\Sales\Services\SalePaymentService;
use App\Domain\Sales\Services\SaleReturnService;
use App\Domain\Sales\Services\SaleService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * Proves docs/ADR/0002-money-format-migration.md's dual-write invariant —
 * every `_minor` integer column always agrees with its legacy decimal
 * sibling (decimal x100 = minor) — for the CRM balances context: Customer,
 * Supplier, their debt ledgers, PurchaseOrder's two ported payment fields,
 * and LoyaltyTier. Mirrors PayrollTest's
 * test_minor_unit_columns_agree_with_legacy_decimal_columns().
 *
 * Several cases below deliberately create/update a model passing only the
 * legacy decimal field — the way an un-migrated controller (or an older
 * test fixture written before this cutover) would — to prove
 * App\Domain\Shared\Concerns\SyncsMoneyMinorColumns derives the `_minor`
 * sibling correctly instead of silently leaving it at 0.
 */
class MoneyMinorSyncTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function assertDecimalMinorAgree(string $decimal, int $minor, string $label): void
    {
        $this->assertSame(
            $decimal,
            bcdiv((string) $minor, '100', 2),
            "{$label} decimal/_minor mismatch: {$decimal} vs {$minor}"
        );
    }

    public function test_customer_created_with_only_decimal_credit_limit_derives_minor(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $customer = Customer::create([
            'business_id' => $business->id,
            'name' => 'Jane',
            'customer_type' => Customer::TYPE_CREDIT,
            'credit_limit' => '5000.00',
        ]);

        $this->assertSame(500000, $customer->credit_limit_minor);
        $this->assertDecimalMinorAgree($customer->credit_limit, $customer->credit_limit_minor, 'credit_limit');
    }

    public function test_credit_sale_payment_and_void_keep_customer_and_debt_ledger_in_sync(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $business->id)->firstOrFail();
        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'cost_price' => 600,
        ]);
        Inventory::create(['business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 20]);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'customer_type' => Customer::TYPE_CREDIT, 'credit_limit' => '10000.00']);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'sold_by' => $owner->id,
        ]);

        $customer->refresh();
        $this->assertSame('3000.00', $customer->current_balance);
        $this->assertDecimalMinorAgree($customer->current_balance, $customer->current_balance_minor, 'customer.current_balance after charge');

        $charge = CustomerDebtTransaction::query()->where('sale_id', $sale->id)->where('type', CustomerDebtTransaction::TYPE_CHARGE)->firstOrFail();
        $this->assertDecimalMinorAgree($charge->amount, $charge->amount_minor, 'charge.amount');
        $this->assertDecimalMinorAgree($charge->balance_before, $charge->balance_before_minor, 'charge.balance_before');
        $this->assertDecimalMinorAgree($charge->balance_after, $charge->balance_after_minor, 'charge.balance_after');

        app(SalePaymentService::class)->record($sale->fresh(), ['amount' => '1200.00']);

        $customer->refresh();
        $this->assertSame('1800.00', $customer->current_balance);
        $this->assertDecimalMinorAgree($customer->current_balance, $customer->current_balance_minor, 'customer.current_balance after payment');

        $payment = CustomerDebtTransaction::query()->where('sale_id', $sale->id)->where('type', CustomerDebtTransaction::TYPE_PAYMENT)->firstOrFail();
        $this->assertDecimalMinorAgree($payment->amount, $payment->amount_minor, 'payment.amount');

        app(SaleService::class)->void($sale->fresh(), 'customer changed mind', $owner->id);

        $customer->refresh();
        // Net remaining impact of the sale (3000 charged - 1200 paid =
        // 1800 still owed) is reversed on void, returning the customer to
        // their pre-sale balance.
        $this->assertSame('0.00', $customer->current_balance);
        $this->assertDecimalMinorAgree($customer->current_balance, $customer->current_balance_minor, 'customer.current_balance after void');
    }

    public function test_store_credit_return_keeps_customer_balance_in_sync(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $business->id)->firstOrFail();
        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'cost_price' => 600,
        ]);
        Inventory::create(['business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 20]);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane']);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['amount' => 2000, 'payment_method' => 'cash']],
            'sold_by' => $owner->id,
        ]);

        $saleReturn = app(SaleReturnService::class)->create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'reason' => SaleReturn::REASON_CHANGED_MIND,
            'refund_method' => SaleReturn::REFUND_STORE_CREDIT,
            'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity_returned' => 1]],
        ]);

        app(SaleReturnService::class)->approve($saleReturn, $owner->id);

        $customer->refresh();
        $this->assertSame('-1000.00', $customer->current_balance);
        $this->assertDecimalMinorAgree($customer->current_balance, $customer->current_balance_minor, 'customer.current_balance after store-credit return');
    }

    public function test_supplier_payment_keeps_supplier_debt_ledger_and_purchase_order_in_sync(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $business->id)->firstOrFail();

        $supplier = Supplier::create(['business_id' => $business->id, 'name' => 'Acme', 'status' => Supplier::STATUS_ACTIVE, 'current_balance' => '5000.00']);
        $this->assertDecimalMinorAgree($supplier->current_balance, $supplier->current_balance_minor, 'supplier.current_balance seed');

        $po = PurchaseOrder::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id, 'po_number' => 'PO-'.uniqid(), 'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_SENT, 'total_amount' => '5000.00', 'balance_due' => '5000.00',
        ]);
        $this->assertDecimalMinorAgree($po->balance_due, $po->balance_due_minor, 'po.balance_due seed');

        app(SupplierPaymentService::class)->record($po, ['amount' => '2000.00', 'paid_by' => $owner->id]);

        $po->refresh();
        $this->assertSame('3000.00', $po->balance_due);
        $this->assertDecimalMinorAgree($po->paid_amount, $po->paid_amount_minor, 'po.paid_amount after payment');
        $this->assertDecimalMinorAgree($po->balance_due, $po->balance_due_minor, 'po.balance_due after payment');

        $supplier->refresh();
        $this->assertSame('3000.00', $supplier->current_balance);
        $this->assertDecimalMinorAgree($supplier->current_balance, $supplier->current_balance_minor, 'supplier.current_balance after payment');

        $debt = SupplierDebtTransaction::query()->where('purchase_order_id', $po->id)->firstOrFail();
        $this->assertDecimalMinorAgree($debt->amount, $debt->amount_minor, 'debt.amount');
        $this->assertDecimalMinorAgree($debt->balance_before, $debt->balance_before_minor, 'debt.balance_before');
        $this->assertDecimalMinorAgree($debt->balance_after, $debt->balance_after_minor, 'debt.balance_after');
    }

    public function test_loyalty_tier_created_with_only_decimal_minimum_spend_derives_minor_and_drives_recalculation(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $business->id)->firstOrFail();
        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 60000, 'cost_price' => 30000,
        ]);
        Inventory::create(['business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 10]);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane']);

        // Created the same way LoyaltyTierController does — the store
        // request only ever collects the legacy decimal field.
        $gold = app(LoyaltyTierService::class)->create([
            'business_id' => $business->id,
            'name' => 'Gold',
            'minimum_spend' => '50000.00',
        ]);

        $this->assertSame(5000000, $gold->minimum_spend_minor);
        $this->assertDecimalMinorAgree((string) $gold->minimum_spend, $gold->minimum_spend_minor, 'loyalty_tier.minimum_spend');

        app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['amount' => 60000, 'payment_method' => 'cash']],
            'sold_by' => $owner->id,
        ]);

        $this->assertSame($gold->id, $customer->refresh()->loyalty_tier_id);
    }
}
