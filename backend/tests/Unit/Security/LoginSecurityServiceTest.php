<?php

namespace Tests\Unit\Security;

use App\Domain\Security\Models\BlockedIp;
use App\Domain\Security\Models\FailedLoginAttempt;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Security\Services\LoginSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginSecurityServiceTest extends TestCase
{
    use RefreshDatabase;

    private LoginSecurityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LoginSecurityService::class);
    }

    public function test_recording_an_attempt_persists_it(): void
    {
        $this->service->recordFailedAttempt('user@example.com', 'platform', 'invalid_password');

        $this->assertDatabaseHas('failed_login_attempts', [
            'email' => 'user@example.com',
            'guard' => 'platform',
            'reason' => 'invalid_password',
        ]);
    }

    public function test_four_failed_attempts_do_not_trigger_a_brute_force_alert(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->service->recordFailedAttempt('user@example.com', 'platform');
        }

        $this->assertSame(0, SecurityAlert::query()->where('type', SecurityAlert::TYPE_BRUTE_FORCE)->count());
    }

    public function test_five_failed_attempts_trigger_exactly_one_brute_force_alert(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordFailedAttempt('user@example.com', 'platform');
        }

        $this->assertSame(1, SecurityAlert::query()->where('type', SecurityAlert::TYPE_BRUTE_FORCE)->count());
    }

    /**
     * Further attempts past the threshold must NOT each file their own
     * alert. An attack is hundreds of attempts, and one row per attempt
     * would bury the Security Center under duplicates of a single
     * incident. One open alert per email per window is the signal; the
     * failed_login_attempts table keeps the full count.
     */
    public function test_attempts_past_the_threshold_do_not_file_duplicate_alerts(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->service->recordFailedAttempt('user@example.com', 'platform');
        }

        $this->assertSame(1, SecurityAlert::query()->where('type', SecurityAlert::TYPE_BRUTE_FORCE)->count());
        $this->assertSame(20, FailedLoginAttempt::query()->where('email', 'user@example.com')->count());
    }

    /**
     * Resolving the alert lets the next attempt raise a fresh one, so a
     * renewed attack after an admin has triaged the first is still
     * surfaced.
     */
    public function test_a_resolved_alert_does_not_suppress_a_new_one(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordFailedAttempt('user@example.com', 'platform');
        }

        SecurityAlert::query()->update(['is_resolved' => true]);

        $this->service->recordFailedAttempt('user@example.com', 'platform');

        $this->assertSame(2, SecurityAlert::query()->where('type', SecurityAlert::TYPE_BRUTE_FORCE)->count());
    }

    public function test_attempts_for_different_emails_are_tracked_independently(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordFailedAttempt('a@example.com', 'platform');
        }
        $this->service->recordFailedAttempt('b@example.com', 'platform');

        $this->assertSame(1, SecurityAlert::query()->count());
    }

    public function test_is_ip_blocked_reflects_active_blocks_only(): void
    {
        BlockedIp::query()->create(['ip_address' => '10.0.0.1', 'is_permanent' => true]);
        BlockedIp::query()->create(['ip_address' => '10.0.0.2', 'is_permanent' => false, 'expires_at' => now()->subDay()]);

        $this->assertTrue($this->service->isIpBlocked('10.0.0.1'));
        $this->assertFalse($this->service->isIpBlocked('10.0.0.2'));
        $this->assertFalse($this->service->isIpBlocked('10.0.0.3'));
    }

    public function test_is_account_locked_reflects_active_lockouts_only(): void
    {
        \App\Domain\Security\Models\AccountLockout::query()->create([
            'lockable_type' => 'platform_user', 'lockable_id' => '019f0000-0000-7000-8000-000000000001', 'locked_at' => now(),
        ]);
        \App\Domain\Security\Models\AccountLockout::query()->create([
            'lockable_type' => 'platform_user', 'lockable_id' => '019f0000-0000-7000-8000-000000000002',
            'locked_at' => now(), 'unlocked_at' => now(),
        ]);

        $this->assertTrue($this->service->isAccountLocked('platform_user', '019f0000-0000-7000-8000-000000000001'));
        $this->assertFalse($this->service->isAccountLocked('platform_user', '019f0000-0000-7000-8000-000000000002'));
    }
}
