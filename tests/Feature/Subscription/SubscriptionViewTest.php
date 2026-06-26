<?php

namespace Tests\Feature\Subscription;

use App\Modules\Business\Services\BusinessRegistrationService;
use App\Modules\Subscription\Models\SubscriptionPlan;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_view_their_trialing_subscription(): void
    {
        $plan = SubscriptionPlan::factory()->create(['slug' => 'starter']);

        $owner = app(BusinessRegistrationService::class)->register([
            'owner_name' => 'Jane Doe',
            'owner_email' => 'jane@example.com',
            'owner_phone' => null,
            'password' => 'Password123!',
            'business_name' => 'Jane General Store',
            'business_type' => 'retail',
            'business_phone' => null,
            'country' => 'KE',
            'currency' => 'KES',
            'subscription_plan_id' => $plan->id,
        ]);
        $owner->forceFill(['email_verified_at' => now()])->save();

        $response = $this->actingAs($owner)->get('/settings/subscription');

        $response->assertOk();
    }
}
