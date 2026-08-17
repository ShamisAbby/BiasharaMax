<?php

namespace Tests\Feature\Security;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Security\Models\AccountLockout;
use App\Domain\Security\Models\BlockedIp;
use App\Domain\Security\Models\FailedLoginAttempt;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Security\Models\TrustedDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the Security Center actually doing something.
 *
 * Before this was wired up, LoginSecurityService had no callers at all:
 * blocked IPs weren't blocked, locked accounts weren't locked, and no
 * alert was ever generated. These tests exist so that can't silently
 * regress to a screen full of nothing again.
 */
class LoginSecurityEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function platformUser(string $password = 'correct-password'): PlatformUser
    {
        return PlatformUser::factory()->create(['password' => Hash::make($password)]);
    }

    public function test_a_blocked_ip_cannot_sign_in_even_with_correct_credentials(): void
    {
        $user = $this->platformUser();

        BlockedIp::query()->create(['ip_address' => '127.0.0.1', 'is_permanent' => true]);

        $this->from(route('platform.login'))
            ->post(route('platform.login'), [
                'email' => $user->email,
                'password' => 'correct-password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest('platform');
    }

    public function test_a_blocked_ip_attempt_raises_an_alert(): void
    {
        $user = $this->platformUser();
        BlockedIp::query()->create(['ip_address' => '127.0.0.1', 'is_permanent' => true]);

        $this->post(route('platform.login'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $this->assertSame(
            1,
            SecurityAlert::query()->where('type', SecurityAlert::TYPE_BLOCKED_IP_ATTEMPT)->count(),
        );
    }

    /**
     * The alert fires before the rate limiter, so without dedup every
     * request from a blocked IP would insert another row.
     */
    public function test_repeated_blocked_ip_attempts_do_not_flood_the_alert_table(): void
    {
        $user = $this->platformUser();
        BlockedIp::query()->create(['ip_address' => '127.0.0.1', 'is_permanent' => true]);

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('platform.login'), [
                'email' => $user->email,
                'password' => 'correct-password',
            ]);
        }

        $this->assertSame(
            1,
            SecurityAlert::query()->where('type', SecurityAlert::TYPE_BLOCKED_IP_ATTEMPT)->count(),
        );
    }

    public function test_an_expired_block_does_not_prevent_sign_in(): void
    {
        $user = $this->platformUser();

        BlockedIp::query()->create([
            'ip_address' => '127.0.0.1',
            'is_permanent' => false,
            'expires_at' => now()->subDay(),
        ]);

        $this->post(route('platform.login'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('platform.dashboard'));

        $this->assertAuthenticatedAs($user, 'platform');
    }

    public function test_a_locked_account_cannot_sign_in_with_the_correct_password(): void
    {
        $user = $this->platformUser();

        AccountLockout::query()->create([
            'lockable_type' => AccountLockout::TYPE_PLATFORM_USER,
            'lockable_id' => $user->getKey(),
            'reason' => 'Manual lock during investigation.',
            'locked_at' => now(),
        ]);

        $this->from(route('platform.login'))
            ->post(route('platform.login'), [
                'email' => $user->email,
                'password' => 'correct-password',
            ])
            ->assertSessionHasErrors('email');

        // The critical assertion: Auth::attempt() succeeds before the
        // lockout check runs, so the session it established must be torn
        // down again or a locked account would be signed in regardless.
        $this->assertGuest('platform');
    }

    public function test_an_unlocked_account_can_sign_in_again(): void
    {
        $user = $this->platformUser();

        AccountLockout::query()->create([
            'lockable_type' => AccountLockout::TYPE_PLATFORM_USER,
            'lockable_id' => $user->getKey(),
            'locked_at' => now()->subHour(),
            'unlocked_at' => now(),
        ]);

        $this->post(route('platform.login'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertRedirect(route('platform.dashboard'));

        $this->assertAuthenticatedAs($user, 'platform');
    }

    public function test_a_failed_login_is_recorded(): void
    {
        $user = $this->platformUser();

        $this->post(route('platform.login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertDatabaseHas('failed_login_attempts', [
            'email' => $user->email,
            'guard' => 'platform',
            'reason' => 'invalid_credentials',
        ]);
    }

    public function test_a_successful_login_records_the_device_and_alerts_once(): void
    {
        $user = $this->platformUser();

        $this->post(route('platform.login'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $this->assertSame(1, TrustedDevice::query()->count());
        $this->assertSame(1, SecurityAlert::query()->where('type', SecurityAlert::TYPE_NEW_DEVICE)->count());

        $this->post(route('platform.logout'));

        $this->post(route('platform.login'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        // Same device: no second device row, and no second alert.
        $this->assertSame(1, TrustedDevice::query()->count());
        $this->assertSame(1, SecurityAlert::query()->where('type', SecurityAlert::TYPE_NEW_DEVICE)->count());
    }

    public function test_an_authenticated_403_raises_a_permission_violation_alert(): void
    {
        $user = $this->platformUser();

        // A platform user with a role that grants nothing: the role
        // itself matters, since an account with NO roles is treated as
        // unrestricted and would never hit a 403.
        $role = \App\Domain\RBAC\Models\PlatformRole::factory()->create();
        $user->platformRoles()->sync([$role->getKey()]);

        $this->actingAs($user, 'platform')
            ->get(route('platform.system.settings.index'))
            ->assertForbidden();

        $this->assertSame(
            1,
            SecurityAlert::query()->where('type', SecurityAlert::TYPE_PERMISSION_VIOLATION)->count(),
        );
    }

    /**
     * An unauthenticated request is redirected to login rather than
     * refused, and either way there is no user to attribute a violation
     * to — so nothing should be recorded.
     */
    public function test_an_unauthenticated_request_does_not_raise_an_alert(): void
    {
        $this->get(route('platform.system.settings.index'));

        $this->assertSame(
            0,
            SecurityAlert::query()->where('type', SecurityAlert::TYPE_PERMISSION_VIOLATION)->count(),
        );
    }
}
