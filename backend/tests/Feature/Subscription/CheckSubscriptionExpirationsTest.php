<?php

namespace Tests\Feature\Subscription;

use App\Domain\Business\Models\Business;
use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Notifications\SubscriptionExpiredNotification;
use App\Domain\Subscription\Notifications\TrialEndingSoonNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesBusinesses;
use Tests\TestCase;

class CheckSubscriptionExpirationsTest extends TestCase
{
    use CreatesBusinesses, RefreshDatabase;

    public function test_command_expires_lapsed_trials_and_starts_grace_period(): void
    {
        Notification::fake();

        [$owner, $business] = $this->createOwnerWithBusiness();
        $business->subscription->forceFill(['trial_ends_at' => now()->subDay()])->save();

        $this->artisan('subscriptions:check-expirations')->assertExitCode(0);

        $subscription = $business->subscription->fresh();
        $this->assertSame(Subscription::STATUS_EXPIRED, $subscription->status);
        $this->assertTrue($subscription->isInGracePeriod());
        $this->assertSame(Business::STATUS_EXPIRED, $business->fresh()->status);

        Notification::assertSentTo($owner, SubscriptionExpiredNotification::class);
    }

    public function test_command_sends_a_reminder_for_trials_ending_within_3_days(): void
    {
        Notification::fake();

        [$owner, $business] = $this->createOwnerWithBusiness();
        $business->subscription->forceFill(['trial_ends_at' => now()->addDays(2)])->save();

        $this->artisan('subscriptions:check-expirations')->assertExitCode(0);

        Notification::assertSentTo($owner, TrialEndingSoonNotification::class);
        $this->assertSame(Subscription::STATUS_TRIALING, $business->subscription->fresh()->status);
    }

    public function test_command_does_not_touch_trials_with_time_remaining(): void
    {
        Notification::fake();

        [$owner, $business] = $this->createOwnerWithBusiness();
        $business->subscription->forceFill(['trial_ends_at' => now()->addDays(20)])->save();

        $this->artisan('subscriptions:check-expirations')->assertExitCode(0);

        $this->assertSame(Subscription::STATUS_TRIALING, $business->subscription->fresh()->status);
        Notification::assertNotSentTo($owner, TrialEndingSoonNotification::class);
        Notification::assertNotSentTo($owner, SubscriptionExpiredNotification::class);
    }
}
