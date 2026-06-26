<?php

namespace Tests\Feature\Auth;

use App\Modules\Authentication\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\RBAC\Models\Role;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Models\SubscriptionPlan;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([PermissionSeeder::class, SubscriptionPlanSeeder::class]);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registering_creates_owner_business_roles_and_trial_subscription(): void
    {
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->first();

        $response = $this->post('/register', [
            'owner_name' => 'Jane Doe',
            'owner_email' => 'jane@example.com',
            'owner_phone' => '+254700000000',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'business_name' => 'Jane General Store',
            'business_type' => 'retail',
            'business_phone' => '+254711111111',
            'country' => 'KE',
            'currency' => 'KES',
            'subscription_plan_id' => $plan->id,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $business = Business::query()->where('name', 'Jane General Store')->firstOrFail();
        $this->assertSame(Business::STATUS_TRIAL, $business->status);
        $this->assertNotNull($business->trial_ends_at);

        $owner = $business->owner;
        $this->assertSame('jane@example.com', $owner->email);
        $this->assertSame($owner->getKey(), $business->owner_id);
        $this->assertSame(Role::OWNER, $owner->role->slug);

        $this->assertSame(5, Role::query()->where('business_id', $business->getKey())->count());

        $subscription = $business->subscription;
        $this->assertSame(Subscription::STATUS_TRIALING, $subscription->status);
        $this->assertSame($plan->getKey(), $subscription->subscription_plan_id);
    }

    public function test_registration_requires_unique_owner_email(): void
    {
        $plan = SubscriptionPlan::query()->where('slug', 'starter')->first();

        User::factory()->create([
            'email' => 'jane@example.com',
        ]);

        $response = $this->post('/register', [
            'owner_name' => 'Jane Doe',
            'owner_email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'business_name' => 'Another Store',
            'business_type' => 'retail',
            'country' => 'KE',
            'currency' => 'KES',
            'subscription_plan_id' => $plan->id,
        ]);

        $response->assertSessionHasErrors('owner_email');
        $this->assertGuest();
    }
}
