<?php

namespace Tests\Feature\Subscription;

use App\Domain\Subscription\Models\SubscriptionPlan;
use App\Domain\Subscription\Services\SubscriptionService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class PlanLimitEnforcementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_cannot_create_a_branch_beyond_the_plan_limit(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $plan = SubscriptionPlan::factory()->create(['max_branches' => 1]);
        app(SubscriptionService::class)->changePlan($business->subscription, $plan, 'monthly');

        // createOwnerWithBusiness() already provisions one main branch.
        $response = $this->actingAs($owner)->post('/settings/branches', [
            'name' => 'Second Branch',
            'code' => 'SB',
        ]);

        $response->assertSessionHasErrors('branch');
        $this->assertDatabaseMissing('branches', ['name' => 'Second Branch']);
    }

    public function test_owner_can_create_a_branch_within_the_plan_limit(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $plan = SubscriptionPlan::factory()->create(['max_branches' => 5]);
        app(SubscriptionService::class)->changePlan($business->subscription, $plan, 'monthly');

        $response = $this->actingAs($owner)->post('/settings/branches', [
            'name' => 'Second Branch',
            'code' => 'SB',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('branches', ['name' => 'Second Branch']);
    }
}
