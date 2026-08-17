<?php

namespace Tests\Feature\Business;

use App\Domain\Authentication\Models\User;
use App\Domain\Business\Models\Business;
use App\Domain\Business\Notifications\EmployeeInvitedNotification;
use App\Domain\Business\Services\BusinessRegistrationService;
use App\Domain\RBAC\Models\Role;
use App\Domain\Subscription\Models\SubscriptionPlan;
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
            'role_ids' => [$managerRole->getKey()],
            'branch_id' => $owner->branch_id,
        ]);

        $response->assertSessionHasNoErrors();

        $employee = User::query()->where('email', 'alex@example.com')->first();
        $this->assertNotNull($employee);
        $this->assertSame(User::STATUS_INVITED, $employee->status);
        $this->assertSame($owner->getKey(), $employee->invited_by);

        // The pivot is what grants permissions; `role_id` is only kept in
        // step for screens that still read a single role.
        $this->assertTrue($employee->roles->contains($managerRole));
        $this->assertSame($managerRole->getKey(), $employee->role_id);

        Notification::assertSentTo($employee, EmployeeInvitedNotification::class);
    }

    public function test_an_employee_can_be_invited_with_more_than_one_role(): void
    {
        Notification::fake();

        [$owner, $business] = $this->createOwnerWithBusiness();

        $roles = Role::query()
            ->where('business_id', $business->getKey())
            ->whereIn('slug', [Role::MANAGER, Role::CASHIER])
            ->get();

        $this->assertCount(2, $roles, 'Expected the business to be seeded with both roles.');

        $this->actingAs($owner)->post('/settings/employees', [
            'name' => 'Multi Role',
            'email' => 'multi@example.com',
            'role_ids' => $roles->modelKeys(),
            'branch_id' => null,
        ])->assertSessionHasNoErrors();

        $employee = User::query()->where('email', 'multi@example.com')->first();

        $this->assertEqualsCanonicalizing(
            $roles->modelKeys(),
            $employee->roles->modelKeys(),
        );

        // Permissions are the union across every assigned role, so a
        // permission held by either one is granted.
        foreach ($roles as $role) {
            foreach ($role->permissions as $permission) {
                $this->assertTrue(
                    $employee->hasPermission($permission->slug),
                    "Expected the union to include {$permission->slug}.",
                );
            }
        }
    }

    public function test_inviting_an_employee_with_no_roles_is_rejected(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        // Fail closed: an employee with no roles has no permissions at all,
        // so an empty list is a mistake rather than a valid restriction.
        $this->actingAs($owner)->post('/settings/employees', [
            'name' => 'No Role',
            'email' => 'norole@example.com',
            'role_ids' => [],
            'branch_id' => null,
        ])->assertSessionHasErrors('role_ids');

        $this->assertNull(User::query()->where('email', 'norole@example.com')->first());
    }

    public function test_invited_employee_can_accept_invitation_and_set_password(): void
    {
        [$owner, $business] = $this->createOwnerWithBusiness();
        $managerRole = Role::query()->where('business_id', $business->getKey())->where('slug', Role::MANAGER)->first();

        $this->actingAs($owner)->post('/settings/employees', [
            'name' => 'Alex Employee',
            'email' => 'alex@example.com',
            'role_ids' => [$managerRole->getKey()],
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
            'role_ids' => [$ownerRole->getKey()],
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
