<?php

namespace Tests\Feature\Crm;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Warehouse;
use App\Domain\CRM\Models\LoyaltyReward;
use App\Domain\CRM\Models\LoyaltyRewardRedemption;
use App\Domain\CRM\Models\LoyaltyTier;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Product;
use App\Domain\RBAC\Models\Role;
use App\Domain\Sales\Models\Customer;
use App\Domain\Sales\Services\SaleService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class LoyaltyTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_create_a_loyalty_tier_and_a_reward(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->post('/crm/loyalty-tiers', [
            'name' => 'Gold',
            'minimum_spend' => 50000,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('loyalty_tiers', [
            'business_id' => $business->id,
            'name' => 'Gold',
            'slug' => 'gold',
        ]);

        $this->actingAs($owner)->post('/crm/loyalty-rewards', [
            'name' => 'Free Coffee',
            'points_cost' => 100,
            'stock_quantity' => 5,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('loyalty_rewards', [
            'business_id' => $business->id,
            'name' => 'Free Coffee',
            'points_cost' => 100,
            'stock_quantity' => 5,
        ]);
    }

    public function test_redeeming_a_reward_deducts_points_decrements_stock_and_creates_a_redemption(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'loyalty_points' => 150]);
        $reward = LoyaltyReward::create([
            'business_id' => $business->id, 'name' => 'Free Coffee', 'slug' => 'free-coffee',
            'points_cost' => 100, 'stock_quantity' => 3,
        ]);

        $this->actingAs($owner)->post("/crm/customers/{$customer->id}/loyalty/redeem", [
            'loyalty_reward_id' => $reward->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(50, $customer->refresh()->loyalty_points);
        $this->assertSame(2, $reward->refresh()->stock_quantity);
        $this->assertSame(1, LoyaltyRewardRedemption::query()
            ->where('customer_id', $customer->id)->where('loyalty_reward_id', $reward->id)->count());
    }

    public function test_redeeming_a_reward_with_insufficient_points_fails_validation(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'loyalty_points' => 10]);
        $reward = LoyaltyReward::create([
            'business_id' => $business->id, 'name' => 'Free Coffee', 'slug' => 'free-coffee',
            'points_cost' => 100,
        ]);

        $this->actingAs($owner)->post("/crm/customers/{$customer->id}/loyalty/redeem", [
            'loyalty_reward_id' => $reward->id,
        ])->assertSessionHasErrors('loyalty_reward_id');

        $this->assertSame(10, $customer->refresh()->loyalty_points);
    }

    public function test_redeeming_an_out_of_stock_reward_fails_validation(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $customer = Customer::create(['business_id' => $business->id, 'name' => 'Jane', 'loyalty_points' => 500]);
        $reward = LoyaltyReward::create([
            'business_id' => $business->id, 'name' => 'Free Coffee', 'slug' => 'free-coffee',
            'points_cost' => 100, 'stock_quantity' => 0,
        ]);

        $this->actingAs($owner)->post("/crm/customers/{$customer->id}/loyalty/redeem", [
            'loyalty_reward_id' => $reward->id,
        ])->assertSessionHasErrors('loyalty_reward_id');

        $this->assertSame(500, $customer->refresh()->loyalty_points);
    }

    public function test_completing_a_sale_recalculates_the_customers_loyalty_tier(): void
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

        $silver = LoyaltyTier::create(['business_id' => $business->id, 'name' => 'Silver', 'slug' => 'silver', 'minimum_spend' => 0]);
        $gold = LoyaltyTier::create(['business_id' => $business->id, 'name' => 'Gold', 'slug' => 'gold', 'minimum_spend' => 50000]);

        app(SaleService::class)->create([
            'business_id' => $business->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['amount' => 60000, 'payment_method' => 'cash']],
            'sold_by' => $owner->id,
        ]);

        $this->assertSame($gold->id, $customer->refresh()->loyalty_tier_id);
        $this->assertNotSame($silver->id, $customer->loyalty_tier_id);
    }

    public function test_employee_without_crm_manage_permission_cannot_create_a_loyalty_tier(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $plainEmployeeRole = Role::query()->where('business_id', $business->id)->where('slug', Role::EMPLOYEE)->first();
        $employee = User::factory()->create([
            'business_id' => $business->id,
            'role_id' => $plainEmployeeRole->id,
        ]);

        $this->actingAs($employee)->post('/crm/loyalty-tiers', [
            'name' => 'Gold',
            'minimum_spend' => 50000,
        ])->assertForbidden();

        $this->assertSame(0, LoyaltyTier::query()->count());
    }
}
