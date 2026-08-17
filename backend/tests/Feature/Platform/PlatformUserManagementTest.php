<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Authentication\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class PlatformUserManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    public function test_superadmin_can_list_users_across_every_business(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [$ownerOne] = $this->createOwnerWithBusiness();
        [$ownerTwo] = $this->createOwnerWithBusiness();

        $this->actingAs($platformUser, 'platform')
            ->get(route('platform.users.index'))
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Platform/Users/Index')
                ->has('users.data', 2)
            );

        $this->assertNotNull($ownerOne);
        $this->assertNotNull($ownerTwo);
    }

    public function test_superadmin_can_deactivate_and_activate_a_user(): void
    {
        $platformUser = PlatformUser::factory()->create();
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.users.deactivate', $owner->id))
            ->assertRedirect();

        $this->assertSame(User::STATUS_SUSPENDED, $owner->fresh()->status);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.users.activate', $owner->id))
            ->assertRedirect();

        $this->assertSame(User::STATUS_ACTIVE, $owner->fresh()->status);
    }

    public function test_superadmin_can_send_a_password_reset_to_a_user(): void
    {
        Notification::fake();

        $platformUser = PlatformUser::factory()->create();
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.users.send-password-reset', $owner->id))
            ->assertRedirect();

        Notification::assertSentTo($owner, \Illuminate\Auth\Notifications\ResetPassword::class);
    }

    public function test_tenant_user_cannot_access_platform_user_management(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.users.index'))
            ->assertRedirect(route('platform.login'));
    }
}
