<?php

namespace Tests\Feature;

use App\Domain\Authentication\Models\User;
use App\Domain\Inventory\Services\ProductService;
use App\Domain\RBAC\Models\Role;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_search_products_by_name_sku_or_barcode(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        app(ProductService::class)->create($business->id, [
            'name' => 'Bottled Water 500ml',
            'sku' => 'BW-500',
            'product_type' => 'simple',
            'cost_price' => 50,
            'selling_price' => 80,
        ]);

        $response = $this->actingAs($owner)->getJson('/search?q=Water');

        $response->assertOk();
        $response->assertJsonCount(1, 'results');
        $response->assertJsonPath('results.0.title', 'Bottled Water 500ml');
        $response->assertJsonPath('results.0.subtitle', 'BW-500');
    }

    public function test_search_returns_no_results_for_a_blank_query(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $response = $this->actingAs($owner)->getJson('/search?q=');

        $response->assertOk();
        $response->assertJsonCount(0, 'results');
    }

    public function test_user_without_products_view_permission_gets_no_results(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        app(ProductService::class)->create($business->id, [
            'name' => 'Bottled Water 500ml',
            'sku' => 'BW-500',
            'product_type' => 'simple',
            'cost_price' => 50,
            'selling_price' => 80,
        ]);

        $noAccessRole = Role::query()->create([
            'business_id' => $business->getKey(),
            'name' => 'No Access',
            'slug' => 'no-access',
            'is_system' => false,
        ]);
        $employee = User::factory()->create([
            'business_id' => $business->id,
            'role_id' => $noAccessRole->id,
        ]);

        $response = $this->actingAs($employee)->getJson('/search?q=Water');

        $response->assertOk();
        $response->assertJsonCount(0, 'results');
    }

    public function test_search_does_not_return_products_from_another_business(): void
    {
        [$owner] = $this->createOwnerWithBusiness();
        [, $otherBusiness] = $this->createOwnerWithBusiness();

        app(ProductService::class)->create($otherBusiness->id, [
            'name' => 'Bottled Water 500ml',
            'sku' => 'BW-500',
            'product_type' => 'simple',
            'cost_price' => 50,
            'selling_price' => 80,
        ]);

        $response = $this->actingAs($owner)->getJson('/search?q=Water');

        $response->assertOk();
        $response->assertJsonCount(0, 'results');
    }
}
