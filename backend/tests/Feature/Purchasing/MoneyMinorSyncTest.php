<?php

namespace Tests\Feature\Purchasing;

use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\Product;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Supplier;
use App\Domain\Purchasing\Models\SupplierPayment;
use App\Domain\Purchasing\Services\PurchaseOrderService;
use App\Domain\Purchasing\Services\SupplierPaymentService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * Proves docs/ADR/0002-money-format-migration.md's dual-write invariant for
 * the Purchasing context (third of six): PurchaseOrder's remaining money
 * fields (subtotal, discount_amount, tax_amount, shipping_cost,
 * other_charges, total_amount), PurchaseOrderItem, and SupplierPayment.
 *
 * Unlike Payroll/CRM, PurchaseOrderService's own arithmetic
 * (buildLineItems(), create(), update()) was deliberately left untouched —
 * it already computes correct decimal(x,2) values via bcmath, and since
 * PurchaseOrder/PurchaseOrderItem now list every money column in
 * moneyMinorColumns(), App\Domain\Shared\Concerns\SyncsMoneyMinorColumns
 * derives `_minor` from the decimal value it writes automatically. That
 * derivation is exact here (round(decimal*100) on an already-2-decimal-
 * place string is lossless), so there's no truncation-vs-rounding risk
 * like Payroll's statutory calculations had. This test proves that
 * derivation actually happens end-to-end through the real service, not
 * just that the trait works in isolation.
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

    public function test_purchase_order_created_through_the_service_derives_minor_on_every_field(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $business->id)->firstOrFail();
        $supplier = Supplier::create(['business_id' => $business->id, 'name' => 'Acme Supplies', 'status' => Supplier::STATUS_ACTIVE]);
        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 1000, 'cost_price' => 600,
        ]);

        $po = app(PurchaseOrderService::class)->create([
            'business_id' => $business->id,
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'items' => [
                ['product_id' => $product->id, 'quantity_ordered' => 10, 'unit_cost' => 500, 'tax_amount' => 50],
            ],
            'shipping_cost' => 20,
            'created_by' => $owner->id,
        ]);

        $this->assertSame('5000.00', $po->subtotal);
        $this->assertSame('5070.00', $po->total_amount);

        foreach (['subtotal', 'discount_amount', 'tax_amount', 'shipping_cost', 'other_charges', 'total_amount'] as $field) {
            $this->assertDecimalMinorAgree((string) $po->{$field}, $po->{"{$field}_minor"}, "purchase_order.{$field}");
        }

        $item = $po->items->first();
        foreach (['unit_cost', 'discount_amount', 'tax_amount', 'line_total'] as $field) {
            $this->assertDecimalMinorAgree((string) $item->{$field}, $item->{"{$field}_minor"}, "purchase_order_item.{$field}");
        }
    }

    public function test_supplier_payment_derives_amount_minor(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $business->id)->firstOrFail();
        $supplier = Supplier::create(['business_id' => $business->id, 'name' => 'Acme Supplies', 'status' => Supplier::STATUS_ACTIVE]);

        $po = PurchaseOrder::create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id, 'po_number' => 'PO-'.uniqid(), 'order_date' => now()->toDateString(),
            'status' => PurchaseOrder::STATUS_SENT, 'total_amount' => '1000.00', 'balance_due' => '1000.00',
        ]);

        // SupplierPaymentService::record() only ever sets the legacy
        // `amount` field on SupplierPayment — amount_minor is expected to
        // come from SyncsMoneyMinorColumns, not the service.
        app(SupplierPaymentService::class)->record($po, ['amount' => '400.00', 'paid_by' => $owner->id]);

        $payment = SupplierPayment::query()->where('purchase_order_id', $po->id)->firstOrFail();
        $this->assertSame(40000, $payment->amount_minor);
        $this->assertDecimalMinorAgree($payment->amount, $payment->amount_minor, 'supplier_payment.amount');
    }
}
