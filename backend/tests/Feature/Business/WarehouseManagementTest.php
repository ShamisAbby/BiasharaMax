<?php

namespace Tests\Feature\Business;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Business\Services\BusinessRegistrationService;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_create_a_warehouse_on_an_existing_branch(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $mainBranch = Branch::query()->where('business_id', $business->getKey())->first();

        $response = $this->actingAs($owner)->post('/settings/warehouses', [
            'branch_id' => $mainBranch->getKey(),
            'name' => 'Overflow Store',
            'code' => 'OVF',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('warehouses', ['code' => 'OVF', 'branch_id' => $mainBranch->getKey()]);
    }

    public function test_default_warehouse_cannot_be_deleted(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $defaultWarehouse = Warehouse::query()->where('business_id', $business->getKey())->first();

        $this->actingAs($owner)
            ->delete("/settings/warehouses/{$defaultWarehouse->getKey()}")
            ->assertForbidden();

        $this->assertNotNull($defaultWarehouse->fresh());
    }

    public function test_non_default_warehouse_can_be_deleted(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $mainBranch = Branch::query()->where('business_id', $business->getKey())->first();

        $extra = Warehouse::query()->create([
            'business_id' => $business->getKey(),
            'branch_id' => $mainBranch->getKey(),
            'name' => 'Extra Warehouse',
            'code' => 'EXT',
            'is_default' => false,
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->delete("/settings/warehouses/{$extra->getKey()}")
            ->assertSessionHasNoErrors();

        $this->assertTrue($extra->fresh()->trashed());
    }

    public function test_warehouse_branch_must_belong_to_the_same_business(): void
    {
        [$ownerA] = $this->createOwnerWithBusiness();
        [, $businessB] = $this->createOwnerWithBusiness();
        $branchInB = Branch::query()->where('business_id', $businessB->getKey())->first();

        $this->actingAs($ownerA)
            ->post('/settings/warehouses', [
                'branch_id' => $branchInB->getKey(),
                'name' => 'Cross Tenant Warehouse',
                'code' => 'XT',
            ])
            ->assertSessionHasErrors('branch_id');
    }

    /**
     * @return array{0: User, 1: Business}
     */
    private function createOwnerWithBusiness(): array
    {
        $plan = SubscriptionPlan::factory()->create();

        $owner = app(BusinessRegistrationService::class)->register([
            'owner_name' => 'Owner '.fake()->unique()->numerify('###'),
            'owner_email' => fake()->unique()->safeEmail(),
            'owner_phone' => null,
            'password' => 'Password123!',
            'business_name' => fake()->unique()->company(),
            'business_type' => 'retail',
            'business_phone' => null,
            'country' => 'KE',
            'currency' => 'KES',
            'subscription_plan_id' => $plan->id,
        ]);

        $owner->forceFill(['email_verified_at' => now()])->save();

        return [$owner, $owner->business];
    }
}
