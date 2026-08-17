<?php

namespace Tests\Unit\Security;

use App\Domain\Security\Models\AccountLockout;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Security\Services\SecurityScoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityScoreServiceTest extends TestCase
{
    use RefreshDatabase;

    private SecurityScoreService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SecurityScoreService::class);
    }

    public function test_a_clean_platform_scores_one_hundred(): void
    {
        $result = $this->service->compute();

        $this->assertSame(100.0, $result['score']);
        $this->assertSame([], $result['signals']);
    }

    public function test_an_unresolved_critical_alert_deducts_points(): void
    {
        SecurityAlert::factory()->create([
            'severity' => SecurityAlert::SEVERITY_CRITICAL,
            'is_resolved' => false,
        ]);

        $result = $this->service->compute();

        $this->assertSame(80.0, $result['score']);
        $this->assertCount(1, $result['signals']);
        $this->assertSame('Unresolved critical alerts', $result['signals'][0]['label']);
    }

    public function test_resolved_alerts_do_not_affect_the_score(): void
    {
        SecurityAlert::factory()->create([
            'severity' => SecurityAlert::SEVERITY_CRITICAL,
            'is_resolved' => true,
        ]);

        $result = $this->service->compute();

        $this->assertSame(100.0, $result['score']);
    }

    public function test_critical_alerts_are_capped_at_a_forty_point_deduction(): void
    {
        SecurityAlert::factory()->count(5)->create([
            'severity' => SecurityAlert::SEVERITY_CRITICAL,
            'is_resolved' => false,
        ]);

        $result = $this->service->compute();

        $this->assertSame(60.0, $result['score']);
    }

    public function test_score_never_drops_below_zero_even_with_every_signal_maxed(): void
    {
        SecurityAlert::factory()->count(5)->create(['severity' => SecurityAlert::SEVERITY_CRITICAL, 'is_resolved' => false]);
        SecurityAlert::factory()->count(5)->create(['severity' => SecurityAlert::SEVERITY_HIGH, 'is_resolved' => false]);
        SecurityAlert::factory()->count(5)->create(['severity' => SecurityAlert::SEVERITY_MEDIUM, 'is_resolved' => false]);

        for ($i = 0; $i < 5; $i++) {
            AccountLockout::query()->create([
                'lockable_type' => AccountLockout::TYPE_USER,
                'lockable_id' => (string) \Illuminate\Support\Str::uuid(),
                'reason' => 'too many failed attempts',
                'locked_at' => now(),
                'expires_at' => now()->addHour(),
            ]);
        }

        $result = $this->service->compute();

        $this->assertSame(0.0, $result['score']);
    }

    public function test_an_active_lockout_deducts_points(): void
    {
        AccountLockout::query()->create([
            'lockable_type' => AccountLockout::TYPE_USER,
            'lockable_id' => (string) \Illuminate\Support\Str::uuid(),
            'reason' => 'too many failed attempts',
            'locked_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $result = $this->service->compute();

        $this->assertSame(95.0, $result['score']);
    }

    public function test_an_unlocked_lockout_does_not_affect_the_score(): void
    {
        AccountLockout::query()->create([
            'lockable_type' => AccountLockout::TYPE_USER,
            'lockable_id' => (string) \Illuminate\Support\Str::uuid(),
            'reason' => 'too many failed attempts',
            'locked_at' => now()->subHour(),
            'unlocked_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $result = $this->service->compute();

        $this->assertSame(100.0, $result['score']);
    }
}
