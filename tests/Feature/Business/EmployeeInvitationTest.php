<?php

namespace Tests\Feature\Business;

use App\Modules\Authentication\Models\User;
use App\Modules\Business\Models\Business;
use App\Modules\Business\Notifications\EmployeeInvitedNotification;
use App\Modules\Business\Services\BusinessRegistrationService;
use App\Modules\RBAC\Models\Role;
use App\Modules\Subscription\Models\SubscriptionPlan;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmployeeInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_can_invite_an_employee_and_a_notification_is_sent(): void
    {
        Notification::fake();

        [$owner, $business] = $this->createOwnerWithBusiness();
        $managerRole = Role::query()->where('business_id', $business->getKey())->where('slug', Role::MANAGER)->first();

        $response = $this->actingAs($owner)->post('/settings/employees', [
            'name' => 'Alex Employee',
            'email' => 'alex@example.com',
            'role_id' => $managerRole->getKey(),
            'branch_id' => $owner->branch_id,
        ]);

        $response->assertSessionHasNoErrors();

        $employee = User::query()->where('email', 'alex@example.com')->first();
        $this->assertNotNull($employee);
        $this->assertSame(User::STATUS_INVITED, $employee->status);
        $this->assertSame($owner->getKey(), $employee->invited_by);

        Notification::assertSentTo($employee, EmployeeInvitedNotification::class);
    }

    public function test_invited_employee_can_accept_invitation_and_set_password(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $managerRole = Role::query()->where('business_id', $business->getKey())->where('slug', Role::MANAGER)->first();

        $this->actingAs($owner)->post('/settings/employees', [
            'name' => 'Alex Employee',
            'email' => 'alex@example.com',
            'role_id' => $managerRole->getKey(),
            'branch_id' => null,
        ]);

        $employee = User::query()->where('email', 'alex@example.com')->first();

        $signedUrl = URL::temporarySignedRoute(
            'employee-invitations.accept',
            now()->addDays(7),
            ['user' => $employee->getKey()],
        );

        $this->get($signedUrl)->assertOk();

        $response = $this->post($signedUrl, [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($employee->fresh());
        $this->assertSame(User::STATUS_ACTIVE, $employee->fresh()->status);
        $this->assertNotNull($employee->fresh()->email_verified_at);
    }

    public function test_owner_cannot_have_their_role_changed_via_employee_update(): void
    {
        [$owner] = $this->createOwnerWithBusiness();
        $ownerRole = $owner->role;

        $response = $this->actingAs($owner)->patch("/settings/employees/{$owner->getKey()}", [
            'name' => $owner->name,
            'role_id' => $ownerRole->getKey(),
            'status' => 'suspended',
        ]);

        $response->assertSessionHasErrors('employee');
        $this->assertSame('active', $owner->fresh()->status);
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
