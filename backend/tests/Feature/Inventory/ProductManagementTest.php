<?php

namespace Tests\Feature\Inventory;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Models\Product;
use App\Domain\Inventory\Services\ProductService;
use App\Domain\RBAC\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_create_a_product(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $response = $this->actingAs($owner)->post('/inventory/products', [
            'name' => 'Bottled Water 500ml',
            'sku' => null,
            'product_type' => 'simple',
            'cost_price' => 50,
            'selling_price' => 80,
            'status' => 'active',
            'visibility' => 'visible',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['name' => 'Bottled Water 500ml']);

        $product = Product::query()->where('name', 'Bottled Water 500ml')->first();
        $this->assertNotNull($product->sku);
        $this->assertNotNull($product->slug);
    }

    public function test_creating_a_product_with_duplicate_sku_fails_validation(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $existing = app(ProductService::class)->create($business->id, [
            'name' => 'Existing Product', 'sku' => 'DUP-1', 'product_type' => 'simple',
            'cost_price' => 10, 'selling_price' => 20,
        ]);

        $this->actingAs($owner)->post('/inventory/products', [
            'name' => 'Another Product',
            'sku' => $existing->sku,
            'product_type' => 'simple',
            'cost_price' => 10,
            'selling_price' => 20,
            'status' => 'active',
            'visibility' => 'visible',
        ])->assertSessionHasErrors('sku');
    }

    public function test_employee_without_products_create_permission_cannot_create_products(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $cashierRole = Role::query()->where('business_id', $business->id)->where('slug', Role::CASHIER)->first();
        $cashier = User::factory()->create(['business_id' => $business->id, 'role_id' => $cashierRole->id]);

        $this->actingAs($cashier)->post('/inventory/products', [
            'name' => 'Unauthorized Product',
            'product_type' => 'simple',
            'cost_price' => 10,
            'selling_price' => 20,
            'status' => 'active',
            'visibility' => 'visible',
        ])->assertForbidden();
    }

    /**
     * Product::class applies the tenant global scope, so a cross-tenant
     * route binding never resolves: it 404s before the ProductPolicy is
     * even consulted. That is stronger isolation than a 403 would be,
     * since the response never confirms the product exists.
     */
    public function test_user_cannot_view_a_product_belonging_to_another_business(): void
    {
        [$ownerA] = $this->createOwnerWithBusiness();
        [, $businessB] = $this->createOwnerWithBusiness();

        $productB = app(ProductService::class)->create($businessB->id, [
            'name' => 'Business B Product', 'sku' => null, 'product_type' => 'simple',
            'cost_price' => 10, 'selling_price' => 20,
        ]);

        $this->actingAs($ownerA)->get("/inventory/products/{$productB->id}")->assertNotFound();
    }

    public function test_owner_can_duplicate_a_product_with_a_new_sku(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $original = app(ProductService::class)->create($business->id, [
            'name' => 'Original Product', 'sku' => null, 'product_type' => 'simple',
            'cost_price' => 10, 'selling_price' => 20,
        ]);

        $this->actingAs($owner)->post("/inventory/products/{$original->id}/duplicate")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['name' => 'Original Product (Copy)']);
        $duplicate = Product::query()->where('name', 'Original Product (Copy)')->first();
        $this->assertNotSame($original->sku, $duplicate->sku);
    }

    public function test_archiving_a_product_sets_status_without_deleting_it(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $product = app(ProductService::class)->create($business->id, [
            'name' => 'Seasonal Item', 'sku' => null, 'product_type' => 'simple',
            'cost_price' => 10, 'selling_price' => 20,
        ]);

        $this->actingAs($owner)->post("/inventory/products/{$product->id}/archive")
            ->assertSessionHasNoErrors();

        $this->assertSame(Product::STATUS_ARCHIVED, $product->fresh()->status);
        $this->assertNotNull($product->fresh());
    }

    public function test_product_search_filters_by_name_sku_or_barcode(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $service = app(ProductService::class);

        $service->create($business->id, ['name' => 'Maize Flour 2kg', 'sku' => 'MF-2KG', 'product_type' => 'simple', 'cost_price' => 10, 'selling_price' => 20]);
        $service->create($business->id, ['name' => 'Cooking Oil 1L', 'sku' => 'CO-1L', 'product_type' => 'simple', 'cost_price' => 10, 'selling_price' => 20]);

        $response = $this->actingAs($owner)->get('/inventory/products?search=Maize');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Maize Flour 2kg')
        );
    }
}
