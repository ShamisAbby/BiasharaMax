<?php

namespace Tests\Feature\Sales;

use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\RBAC\Models\Role;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class SalesAndPosTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    /**
     * Every business already has a "Main Branch" + "Main Warehouse"
     * provisioned atomically by BranchProvisioningService during
     * registration (see createOwnerWithBusiness()) — reuse them instead
     * of creating a second branch, which would collide on the unique
     * (business_id, code) constraint.
     *
     * @return array{0: Branch, 1: Warehouse, 2: Product}
     */
    private function makeStockedProduct(Business $business, float $stock = 20, float $price = 1000, float $taxRate = 0): array
    {
        $branch = Branch::query()->where('business_id', $business->id)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $business->id)->firstOrFail();
        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => $price, 'cost_price' => $price * 0.6, 'tax_rate' => $taxRate,
        ]);
        Inventory::create(['business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => $stock]);

        return [$branch, $warehouse, $product];
    }

    public function test_owner_can_complete_a_cash_sale_via_pos_and_stock_is_deducted(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business, stock: 20, price: 1000);

        $response = $this->actingAs($owner)->post('/pos', [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payments' => [['amount' => 3000, 'payment_method' => 'cash']],
        ]);

        $response->assertSessionHasNoErrors();

        $sale = Sale::query()->where('business_id', $business->id)->firstOrFail();
        $this->assertSame('3000.00', $sale->total_amount);
        $this->assertSame('paid', $sale->payment_status);

        $this->assertSame('17.000', Inventory::where('product_id', $product->id)->first()->quantity);
    }

    public function test_credit_sale_without_a_customer_fails_validation(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business);

        $response = $this->actingAs($owner)->post('/pos', [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertSessionHasErrors('items');
        $this->assertSame(0, Sale::query()->count());
    }

    public function test_credit_customer_can_buy_on_credit_within_their_limit(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business, price: 1000);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'customer_type' => 'credit', 'credit_limit' => 5000]);

        $this->actingAs($owner)->post('/pos', [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertSessionHasNoErrors();

        $sale = Sale::query()->firstOrFail();
        $this->assertSame('unpaid', $sale->payment_status);
        $this->assertSame('2000.00', $sale->balance_due);
        $this->assertSame('2000.00', $customer->refresh()->current_balance);
    }

    public function test_exceeding_credit_limit_fails_validation_and_does_not_create_a_sale(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business, price: 1000);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'customer_type' => 'credit', 'credit_limit' => 500]);

        $this->actingAs($owner)->post('/pos', [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame('0.00', $customer->refresh()->current_balance);
    }

    public function test_selling_more_than_available_stock_fails_and_creates_no_sale(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business, stock: 5);

        $this->actingAs($owner)->post('/pos', [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 999]],
            'payments' => [['amount' => 999000, 'payment_method' => 'cash']],
        ])->assertSessionHasErrors();

        $this->assertSame(0, Sale::query()->count());
        $this->assertSame('5.000', Inventory::where('product_id', $product->id)->first()->quantity);
    }

    public function test_voiding_a_sale_restores_stock_and_reverses_customer_balance(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business, stock: 20, price: 1000);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'customer_type' => 'credit', 'credit_limit' => 5000]);

        $this->actingAs($owner)->post('/pos', [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $sale = Sale::query()->firstOrFail();
        $this->assertSame('18.000', Inventory::where('product_id', $product->id)->first()->quantity);

        $this->actingAs($owner)
            ->post("/sales/orders/{$sale->id}/void", ['reason' => 'Customer changed mind'])
            ->assertSessionHasNoErrors();

        $this->assertSame('voided', $sale->refresh()->status);
        $this->assertSame('20.000', Inventory::where('product_id', $product->id)->first()->quantity);
        $this->assertSame('0.00', $customer->refresh()->current_balance);
    }

    public function test_recording_a_payment_reduces_balance_and_updates_customer(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business, price: 1000);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'customer_type' => 'credit', 'credit_limit' => 5000]);

        $this->actingAs($owner)->post('/pos', [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
        ]);

        $sale = Sale::query()->firstOrFail();
        $this->assertSame('5000.00', $sale->balance_due);

        $this->actingAs($owner)
            ->post("/sales/orders/{$sale->id}/payments", ['amount' => 2000, 'payment_method' => 'cash'])
            ->assertSessionHasNoErrors();

        $sale->refresh();
        $this->assertSame('3000.00', $sale->balance_due);
        $this->assertSame('partial', $sale->payment_status);
        $this->assertSame('3000.00', $customer->refresh()->current_balance);
    }

    public function test_overpaying_a_sale_balance_fails_validation(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business, price: 1000);
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'customer_type' => 'credit', 'credit_limit' => 5000]);

        $this->actingAs($owner)->post('/pos', [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $sale = Sale::query()->firstOrFail();

        $this->actingAs($owner)
            ->post("/sales/orders/{$sale->id}/payments", ['amount' => 999999, 'payment_method' => 'cash'])
            ->assertSessionHasErrors('amount');
    }

    public function test_employee_without_sales_create_permission_cannot_use_pos(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business);

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();

        $employee = \App\Domain\Authentication\Models\User::factory()->create([
            'business_id' => $business->id,
            'role_id' => $plainEmployeeRole->id,
        ]);

        $this->actingAs($employee)->post('/pos', [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['amount' => 1000, 'payment_method' => 'cash']],
        ])->assertForbidden();
    }

    public function test_owner_can_create_a_customer(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->post('/sales/customers', [
            'name' => 'Acme Corp',
            'customer_type' => 'credit',
            'credit_limit' => 10000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customers', ['business_id' => $business->id, 'name' => 'Acme Corp']);
    }
}
