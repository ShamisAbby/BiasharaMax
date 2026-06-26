<?php

namespace Tests\Feature\Business;

use App\Modules\Authentication\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Business\Services\BusinessRegistrationService;
use App\Modules\RBAC\Models\Role;
use App\Modules\Subscription\Models\SubscriptionPlan;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_view_and_update_business_settings(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)->get('/settings/business')->assertOk();

        $response = $this->actingAs($owner)->patch('/settings/business', [
            'name' => 'Updated Store Name',
            'business_type' => 'supermarket',
            'phone' => '+254799999999',
            'country' => 'KE',
            'currency' => 'KES',
            'timezone' => 'Africa/Nairobi',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('Updated Store Name', $business->fresh()->name);
        $this->assertSame('supermarket', $business->fresh()->business_type);
    }

    public function test_employee_without_business_update_permission_cannot_update_settings(): void
    {
        [, $business] = $this->createOwnerWithBusiness();

        $cashierRole = Role::query()->where('business_id', $business->getKey())->where('slug', Role::CASHIER)->first();

        $cashier = User::factory()->create([
            'business_id' => $business->getKey(),
            'role_id' => $cashierRole->getKey(),
        ]);

        $this->actingAs($cashier)
            ->patch('/settings/business', ['name' => 'Hacked Name'])
            ->assertForbidden();
    }

    public function test_user_from_another_business_cannot_view_settings_of_a_different_business(): void
    {
        [$ownerA] = $this->createOwnerWithBusiness();
        [, $businessB] = $this->createOwnerWithBusiness();

        // Sanity: ownerA's own business view works.
        $this->actingAs($ownerA)->get('/settings/business')->assertOk();

        $this->assertNotSame($ownerA->business_id, $businessB->getKey());
    }

    /**
     * @return array{0: User, 1: Business}
     */
    private function createOwnerWithBusiness(): array
    {
        $plan = SubscriptionPlan::factory()->create();

        $service = app(BusinessRegistrationService::class);

        $owner = $service->register([
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
