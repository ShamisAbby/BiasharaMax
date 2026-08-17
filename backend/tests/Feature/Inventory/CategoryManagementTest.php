<?php

namespace Tests\Feature\Inventory;

use App\Domain\Inventory\Models\Category;
use App\Domain\Inventory\Services\ProductService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_create_a_subcategory(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $parent = Category::query()->create(['business_id' => $business->id, 'name' => 'Drinks', 'slug' => 'drinks']);

        $this->actingAs($owner)->post('/inventory/categories', [
            'parent_id' => $parent->id,
            'name' => 'Soda',
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categories', ['name' => 'Soda', 'parent_id' => $parent->id]);
    }

    public function test_category_with_products_cannot_be_deleted(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $category = Category::query()->create(['business_id' => $business->id, 'name' => 'Snacks', 'slug' => 'snacks']);
        app(ProductService::class)->create($business->id, [
            'name' => 'Chips', 'sku' => null, 'category_id' => $category->id,
            'product_type' => 'simple', 'cost_price' => 10, 'selling_price' => 20,
        ]);

        $this->actingAs($owner)->delete("/inventory/categories/{$category->id}")
            ->assertSessionHasErrors('category');

        $this->assertNotNull($category->fresh());
    }

    public function test_category_cannot_be_its_own_parent(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $category = Category::query()->create(['business_id' => $business->id, 'name' => 'Beverages', 'slug' => 'beverages']);

        $this->actingAs($owner)->patch("/inventory/categories/{$category->id}", [
            'parent_id' => $category->id,
            'name' => 'Beverages',
            'status' => 'active',
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_duplicate_category_names_get_unique_slugs_per_business(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->post('/inventory/categories', ['name' => 'Dairy', 'status' => 'active']);
        $this->actingAs($owner)->post('/inventory/categories', ['name' => 'Dairy', 'status' => 'active']);

        $this->assertDatabaseHas('categories', ['slug' => 'dairy']);
        $this->assertDatabaseHas('categories', ['slug' => 'dairy-1']);
    }
}
