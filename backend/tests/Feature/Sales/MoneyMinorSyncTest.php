<?php

namespace Tests\Feature\Sales;

use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\CRM\Services\LoyaltyTierService;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\SaleReturn;
use App\Domain\Sales\Services\SalePaymentService;
use App\Domain\Sales\Services\SaleReturnService;
use App\Domain\Sales\Services\SaleService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

/**
 * Proves docs/ADR/0002-money-format-migration.md's dual-write invariant for
 * the Sales context (fifth of six): Sale, SaleItem, SalePayment, SaleReturn,
 * SaleReturnItem. Like Purchasing/Inventory, SaleService/SaleReturnService's
 * own bcmath arithmetic was left untouched — listing every column in
 * moneyMinorColumns() is enough for SyncsMoneyMinorColumns to derive
 * `_minor` correctly from what the service already computes.
 *
 * Also proves LoyaltyTierService::recalculateTier() now compares purely in
 * minor units (Sale.total_amount_minor / SaleReturn.refund_amount_minor)
 * instead of bridging through the legacy decimal columns via Money —
 * possible now that this context reliably populates those columns.
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

    /**
     * @return array{0: Branch, 1: Warehouse, 2: Product}
     */
    private function makeStockedProduct(string $businessId, float $stock = 20, float $price = 1000): array
    {
        $branch = Branch::query()->where('business_id', $businessId)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $businessId)->firstOrFail();
        $product = Product::create([
            'business_id' => $businessId, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => $price, 'cost_price' => $price * 0.6, 'tax_rate' => 16,
        ]);
        Inventory::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => $stock]);

        return [$branch, $warehouse, $product];
    }

    public function test_cash_sale_derives_minor_on_sale_items_and_payment(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id, price: 1000);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payments' => [['amount' => 3480, 'payment_method' => 'cash']],
            'sold_by' => $owner->id,
        ]);

        foreach (['subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'paid_amount', 'balance_due'] as $field) {
            $this->assertDecimalMinorAgree((string) $sale->{$field}, $sale->{"{$field}_minor"}, "sale.{$field}");
        }

        $item = $sale->items->first();
        foreach (['unit_price', 'unit_cost', 'discount_amount', 'tax_amount', 'line_total'] as $field) {
            $this->assertDecimalMinorAgree((string) $item->{$field}, $item->{"{$field}_minor"}, "sale_item.{$field}");
        }

        $payment = $sale->payments->first();
        $this->assertDecimalMinorAgree((string) $payment->amount, $payment->amount_minor, 'sale_payment.amount');
    }

    public function test_recording_a_later_payment_derives_amount_minor(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id, price: 1000);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'customer_type' => 'credit', 'credit_limit' => 5000]);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'sold_by' => $owner->id,
        ]);

        app(SalePaymentService::class)->record($sale->fresh(), ['amount' => '1000.00']);

        $sale->refresh();
        $this->assertDecimalMinorAgree((string) $sale->paid_amount, $sale->paid_amount_minor, 'sale.paid_amount after payment');
        $this->assertDecimalMinorAgree((string) $sale->balance_due, $sale->balance_due_minor, 'sale.balance_due after payment');
    }

    public function test_store_credit_return_derives_minor_on_return_and_items(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id, price: 1000);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane']);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['amount' => 2320, 'payment_method' => 'cash']],
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

        $saleReturn->refresh();
        $this->assertDecimalMinorAgree((string) $saleReturn->refund_amount, $saleReturn->refund_amount_minor, 'sale_return.refund_amount');

        $item = $saleReturn->items->first();
        $this->assertDecimalMinorAgree((string) $item->unit_price, $item->unit_price_minor, 'sale_return_item.unit_price');
        $this->assertDecimalMinorAgree((string) $item->line_refund_amount, $item->line_refund_amount_minor, 'sale_return_item.line_refund_amount');
    }

    public function test_loyalty_tier_recalculation_compares_purely_in_minor_units(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $business->id)->firstOrFail();
        // tax_rate 0 (unlike makeStockedProduct's helper default of 16)
        // so the sale total is exactly the price, with no bcmath rounding
        // to account for when computing the matching payment amount.
        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => 60000, 'cost_price' => 30000,
        ]);
        Inventory::create(['business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => 10]);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane']);

        $gold = app(LoyaltyTierService::class)->create(['business_id' => $business->id, 'name' => 'Gold', 'minimum_spend' => '50000.00']);

        $sale = app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['amount' => 60000, 'payment_method' => 'cash']],
            'sold_by' => $owner->id,
        ]);

        $this->assertSame($gold->id, $customer->refresh()->loyalty_tier_id);

        // Returning the item drops net spend back under the tier's
        // threshold — recalculation must downgrade, proving the minor-unit
        // comparison reacts correctly to both sides changing.
        $saleReturn = app(SaleReturnService::class)->create([
            'business_id' => $business->id,
            'sale_id' => $sale->id,
            'customer_id' => $customer->id,
            'reason' => SaleReturn::REASON_CHANGED_MIND,
            'refund_method' => SaleReturn::REFUND_CASH,
            'items' => [['sale_item_id' => $sale->items->first()->id, 'quantity_returned' => 1]],
        ]);
        app(SaleReturnService::class)->approve($saleReturn, $owner->id);

        $this->assertNotSame($gold->id, $customer->refresh()->loyalty_tier_id);
    }
}
