<?php

namespace Tests\Feature\Business;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Branch;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Models\Warehouse;
use App\Domain\Business\Services\BusinessRegistrationService;
use App\Domain\RBAC\Models\Role;
use App\Domain\Subscription\Models\SubscriptionPlan;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_registration_provisions_a_main_branch_and_warehouse(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $mainBranch = Branch::query()->where('business_id', $business->getKey())->first();

        $this->assertNotNull($mainBranch);
        $this->assertTrue($mainBranch->is_main);
        $this->assertSame($owner->branch_id, $mainBranch->getKey());

        $mainWarehouse = Warehouse::query()->where('branch_id', $mainBranch->getKey())->first();
        $this->assertNotNull($mainWarehouse);
        $this->assertTrue($mainWarehouse->is_default);
    }

    public function test_owner_can_create_a_new_branch(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $response = $this->actingAs($owner)->post('/settings/branches', [
            'name' => 'Westlands Branch',
            'code' => 'WL',
            'phone' => '+254700000001',
            'city' => 'Nairobi',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branches', ['code' => 'WL', 'name' => 'Westlands Branch']);
    }

    public function test_main_branch_cannot_be_deleted(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $mainBranch = Branch::query()->where('business_id', $business->getKey())->first();

        $this->actingAs($owner)
            ->delete("/settings/branches/{$mainBranch->getKey()}")
            ->assertForbidden();

        $this->assertNotNull($mainBranch->fresh());
    }

    public function test_branch_with_warehouses_cannot_be_deleted_until_emptied(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $branch = Branch::query()->create([
            'business_id' => $business->getKey(),
            'name' => 'Second Branch',
            'code' => 'SB',
            'is_main' => false,
            'status' => 'active',
        ]);

        Warehouse::query()->create([
            'business_id' => $business->getKey(),
            'branch_id' => $branch->getKey(),
            'name' => 'Second Warehouse',
            'code' => 'SB-WH',
            'is_default' => false,
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->delete("/settings/branches/{$branch->getKey()}")
            ->assertSessionHasErrors('branch');

        $this->assertNotNull($branch->fresh());
    }

    public function test_employee_without_branch_permission_cannot_create_branch(): void
    {
        [, $business] = $this->createOwnerWithBusiness();
        $cashierRole = Role::query()
            ->where('business_id', $business->getKey())
            ->where('slug', Role::CASHIER)
            ->first();

        $cashier = User::factory()->create([
            'business_id' => $business->getKey(),
            'role_id' => $cashierRole->getKey(),
        ]);

        $this->actingAs($cashier)
            ->post('/settings/branches', ['name' => 'Hacked Branch', 'code' => 'HB'])
            ->assertForbidden();
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
