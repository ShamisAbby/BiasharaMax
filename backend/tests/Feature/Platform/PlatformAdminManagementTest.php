<?php

namespace Tests\Feature\Platform;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\RBAC\Models\PlatformRole;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class PlatformAdminManagementTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(PlatformRoleSeeder::class);
    }

    public function test_platform_user_with_permission_can_invite_a_new_admin(): void
    {
        Notification::fake();
        $inviter = PlatformUser::factory()->create();
        $role = PlatformRole::query()->where('slug', PlatformRole::PLATFORM_ADMIN)->first();

        $this->actingAs($inviter, 'platform')->post(route('platform.staff.store'), [
            'name' => 'New Admin',
            'email' => 'new-admin@biasharamax.test',
            'platform_role_id' => $role->id,
        ])->assertSessionHasNoErrors();

        $invited = PlatformUser::query()->where('email', 'new-admin@biasharamax.test')->first();
        $this->assertNotNull($invited);
        $this->assertSame(PlatformUser::STATUS_INVITED, $invited->status);
        $this->assertSame($role->id, $invited->platform_role_id);
    }

    public function test_invited_admin_can_accept_invitation_and_set_a_password(): void
    {
        Notification::fake();
        $inviter = PlatformUser::factory()->create();

        $this->actingAs($inviter, 'platform')->post(route('platform.staff.store'), [
            'name' => 'New Admin',
            'email' => 'new-admin@biasharamax.test',
        ]);
        $invited = PlatformUser::query()->where('email', 'new-admin@biasharamax.test')->first();

        $signedUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'platform.staff-invitations.accept',
            now()->addDays(7),
            ['platform_user' => $invited->id],
        );

        $this->get($signedUrl)->assertOk();

        $this->post($signedUrl, [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('platform.dashboard', absolute: false));

        $invited->refresh();
        $this->assertSame(PlatformUser::STATUS_ACTIVE, $invited->status);
        $this->assertNotNull($invited->email_verified_at);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassword123!', $invited->password));
    }

    public function test_platform_admin_without_permission_cannot_invite_an_admin(): void
    {
        $restrictedRole = PlatformRole::query()->create(['name' => 'Support', 'slug' => 'support', 'is_system' => false]);
        $platformUser = PlatformUser::factory()->create(['platform_role_id' => $restrictedRole->id]);

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.staff.store'), ['name' => 'X', 'email' => 'x@biasharamax.test'])
            ->assertForbidden();
    }

    public function test_admin_cannot_deactivate_their_own_account(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->post(route('platform.staff.deactivate', $platformUser->id))
            ->assertSessionHasErrors('platform_user');

        $this->assertSame(PlatformUser::STATUS_ACTIVE, $platformUser->fresh()->status);
    }

    public function test_admin_cannot_remove_their_own_account(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')
            ->delete(route('platform.staff.destroy', $platformUser->id))
            ->assertSessionHasErrors('platform_user');

        $this->assertNotNull($platformUser->fresh());
    }

    public function test_admin_can_deactivate_and_reactivate_another_admin(): void
    {
        $actor = PlatformUser::factory()->create();
        $target = PlatformUser::factory()->create();

        $this->actingAs($actor, 'platform')->post(route('platform.staff.deactivate', $target->id));
        $this->assertSame(PlatformUser::STATUS_SUSPENDED, $target->fresh()->status);

        $this->actingAs($actor, 'platform')->post(route('platform.staff.activate', $target->id));
        $this->assertSame(PlatformUser::STATUS_ACTIVE, $target->fresh()->status);
    }

    public function test_admin_can_update_their_own_profile(): void
    {
        $platformUser = PlatformUser::factory()->create();

        $this->actingAs($platformUser, 'platform')->patch(route('platform.profile.update'), [
            'name' => 'Updated Name',
            'email' => $platformUser->email,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Updated Name', $platformUser->fresh()->name);
    }

    public function test_admin_can_change_their_own_password(): void
    {
        $platformUser = PlatformUser::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make('OldPassword123!')]);

        $this->actingAs($platformUser, 'platform')->put(route('platform.profile.password.update'), [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassword123!', $platformUser->fresh()->password));
    }

    public function test_changing_password_requires_correct_current_password(): void
    {
        $platformUser = PlatformUser::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make('OldPassword123!')]);

        $this->actingAs($platformUser, 'platform')->put(route('platform.profile.password.update'), [
            'current_password' => 'WrongPassword!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_tenant_user_cannot_access_platform_staff_management(): void
    {
        [$owner] = $this->createOwnerWithBusiness();

        $this->actingAs($owner)
            ->get(route('platform.staff.index'))
            ->assertRedirect(route('platform.login'));
    }
}
