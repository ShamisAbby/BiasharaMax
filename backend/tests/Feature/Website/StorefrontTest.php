<?php

namespace Tests\Feature\Website;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\RBAC\Models\Role;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Domain\Website\Models\ProductEnquiry;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class StorefrontTest extends TestCase
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
    private function makeStockedProduct(string $businessId, float $stock = 20, float $price = 1000, bool $visible = true): array
    {
        $branch = Branch::query()->where('business_id', $businessId)->firstOrFail();
        $warehouse = Warehouse::query()->where('business_id', $businessId)->firstOrFail();
        $product = Product::create([
            'business_id' => $businessId, 'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(), 'selling_price' => $price, 'cost_price' => $price * 0.6,
            'status' => Product::STATUS_ACTIVE,
            'visibility' => $visible ? Product::VISIBILITY_VISIBLE : Product::VISIBILITY_HIDDEN,
        ]);
        Inventory::create(['business_id' => $businessId, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity' => $stock]);

        return [$branch, $warehouse, $product];
    }

    public function test_storefront_only_lists_visible_active_products(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        [, , $visibleProduct] = $this->makeStockedProduct($business->id, visible: true);
        [, , $hiddenProduct] = $this->makeStockedProduct($business->id, visible: false);

        $response = $this->get("/site/{$business->slug}/products");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Storefront/Products')
            ->has('products.data', 1)
            ->where('products.data.0.id', $visibleProduct->id)
        );
        $this->assertNotEquals($hiddenProduct->id, $visibleProduct->id);
    }

    public function test_product_detail_page_shows_stock_availability(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        [, , $product] = $this->makeStockedProduct($business->id, stock: 5);

        $this->get("/site/{$business->slug}/products/{$product->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Storefront/ProductShow')
                ->where('product.in_stock', true)
                ->where('product.available_stock', 5)
            );
    }

    public function test_customer_can_add_to_cart_and_view_it(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        [, , $product] = $this->makeStockedProduct($business->id, price: 1500);

        $this->post("/site/{$business->slug}/cart", [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertSessionHasNoErrors();

        $this->get("/site/{$business->slug}/cart")->assertInertia(fn ($page) => $page
            ->component('Storefront/Cart')
            ->has('cart.lines', 1)
            ->where('cart.subtotal', '3000.00')
        );
    }

    public function test_checking_out_with_pay_on_delivery_creates_a_real_online_sale_and_deducts_stock(): void
    {
        Notification::fake();

        [, $business] = $this->createOwnerWithBusiness();
        [, , $product] = $this->makeStockedProduct($business->id, stock: 10, price: 2000);

        $this->post("/site/{$business->slug}/cart", ['product_id' => $product->id, 'quantity' => 3]);

        $this->post("/site/{$business->slug}/checkout", [
            'name' => 'Jane Buyer',
            'phone' => '0700000000',
            'email' => 'jane@example.com',
            'delivery_address' => '123 Market St',
            'payment_method' => 'pay_on_delivery',
        ])->assertSessionHasNoErrors();

        $sale = Sale::query()->where('business_id', $business->id)->where('source', Sale::SOURCE_ONLINE)->firstOrFail();
        $this->assertSame('6000.00', $sale->total_amount);
        $this->assertSame(Sale::PAYMENT_STATUS_UNPAID, $sale->payment_status);
        $this->assertSame('7.000', Inventory::where('product_id', $product->id)->first()->quantity);

        $customer = Customer::query()->where('business_id', $business->id)->where('phone', '0700000000')->firstOrFail();
        $this->assertSame(Customer::TYPE_CREDIT, $customer->customer_type);
        $this->assertSame($sale->id, $customer->sales()->first()->id);

        // cart cleared after checkout
        $this->get("/site/{$business->slug}/cart")->assertInertia(fn ($page) => $page->has('cart.lines', 0));
    }

    public function test_checking_out_with_a_prepaid_method_records_a_real_payment(): void
    {
        Notification::fake();

        [, $business] = $this->createOwnerWithBusiness();
        [, , $product] = $this->makeStockedProduct($business->id, stock: 10, price: 1000);

        $this->post("/site/{$business->slug}/cart", ['product_id' => $product->id, 'quantity' => 1]);
        $this->post("/site/{$business->slug}/checkout", [
            'name' => 'John Buyer',
            'phone' => '0711111111',
            'delivery_address' => '456 Side St',
            'payment_method' => 'mobile_money',
            'payment_reference' => 'MPESA123',
        ])->assertSessionHasNoErrors();

        $sale = Sale::query()->where('business_id', $business->id)->where('source', Sale::SOURCE_ONLINE)->firstOrFail();
        $this->assertSame(Sale::PAYMENT_STATUS_PAID, $sale->payment_status);
        $this->assertSame('0.00', $sale->balance_due);
    }

    public function test_checking_out_with_insufficient_stock_fails_gracefully_without_creating_a_sale(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        [, , $product] = $this->makeStockedProduct($business->id, stock: 1);

        $this->post("/site/{$business->slug}/cart", ['product_id' => $product->id, 'quantity' => 1]);

        // Stock disappears between adding to cart and checking out.
        Inventory::where('product_id', $product->id)->update(['quantity' => 0]);

        $this->post("/site/{$business->slug}/checkout", [
            'name' => 'Jane Buyer',
            'phone' => '0700000000',
            'delivery_address' => '123 Market St',
            'payment_method' => 'pay_on_delivery',
        ])->assertSessionHasErrors('checkout');

        $this->assertSame(0, Sale::query()->where('business_id', $business->id)->count());
    }

    public function test_checking_out_with_an_empty_cart_fails(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $this->post("/site/{$business->slug}/checkout", [
            'name' => 'Jane Buyer',
            'phone' => '0700000000',
            'delivery_address' => '123 Market St',
            'payment_method' => 'pay_on_delivery',
        ])->assertSessionHasErrors('checkout');
    }

    public function test_customer_can_submit_a_product_enquiry_and_owner_is_notified(): void
    {
        Notification::fake();

        [, $business] = $this->createOwnerWithBusiness();
        [, , $product] = $this->makeStockedProduct($business->id);

        $this->post("/site/{$business->slug}/products/{$product->slug}/enquiries", [
            'name' => 'Curious Customer',
            'email' => 'curious@example.com',
            'message' => 'Is this available in blue?',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_enquiries', [
            'business_id' => $business->id,
            'product_id' => $product->id,
            'name' => 'Curious Customer',
            'status' => 'new',
        ]);
    }

    public function test_owner_can_reply_to_an_enquiry(): void
    {
        Notification::fake();

        [$owner, $business] = $this->createOwnerWithBusiness();
        $enquiry = ProductEnquiry::create([
            'business_id' => $business->id, 'name' => 'Curious Customer', 'email' => 'curious@example.com',
            'message' => 'Is this available in blue?',
        ]);

        $this->actingAs($owner)->post("/website/enquiries/{$enquiry->id}/reply", [
            'reply' => 'Yes, blue is available!',
        ])->assertSessionHasNoErrors();

        $enquiry->refresh();
        $this->assertSame('Yes, blue is available!', $enquiry->reply);
        $this->assertSame('responded', $enquiry->status);
        $this->assertNotNull($enquiry->responded_at);
    }

    public function test_employee_without_website_manage_permission_cannot_reply_to_an_enquiry(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $enquiry = ProductEnquiry::create([
            'business_id' => $business->id, 'name' => 'Curious Customer', 'message' => 'Question?',
        ]);

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();
        $employee = User::factory()->create(['business_id' => $business->id, 'role_id' => $plainEmployeeRole->id]);

        $this->actingAs($employee)->post("/website/enquiries/{$enquiry->id}/reply", [
            'reply' => 'Should not be allowed.',
        ])->assertForbidden();
    }

    public function test_online_orders_can_be_filtered_in_the_sales_orders_index(): void
    {
        Notification::fake();

        [$owner, $business] = $this->createOwnerWithBusiness();
        [$branch, $warehouse, $product] = $this->makeStockedProduct($business->id);

        app(\App\Domain\Sales\Services\SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['amount' => 1000, 'payment_method' => 'cash']],
            'sold_by' => $owner->id,
            'source' => 'pos',
        ]);

        $this->post("/site/{$business->slug}/cart", ['product_id' => $product->id, 'quantity' => 1]);
        $this->post("/site/{$business->slug}/checkout", [
            'name' => 'Jane Buyer', 'phone' => '0700000000', 'delivery_address' => 'Addr',
            'payment_method' => 'pay_on_delivery',
        ]);

        $this->actingAs($owner)->get('/sales/orders?source=online')
            ->assertInertia(fn ($page) => $page->has('sales.data', 1)->where('sales.data.0.source', 'online'));
    }
}
