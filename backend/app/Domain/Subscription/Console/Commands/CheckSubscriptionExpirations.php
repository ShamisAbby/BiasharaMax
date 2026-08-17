<?php

namespace App\Domain\Subscription\Console\Commands;

use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Notifications\TrialEndingSoonNotification;
use App\Domain\Subscription\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Runs daily (see routes/console.php). Three jobs in one pass:
 *  1. Warn trials that end within 3 days (renewal reminder).
 *  2. Flip lapsed trials/subscriptions to "expired" and start their
 *     7-day grace period.
 *  3. Lock businesses whose grace period has run out (handled
 *     implicitly — Subscription::isLocked() reads grace_period_ends_at,
 *     nothing further to write once it's in the past).
 */
class CheckSubscriptionExpirations extends Command
{
    protected $signature = 'subscriptions:check-expirations';

    protected $description = 'Send trial-ending reminders and expire lapsed subscriptions/trials.';

    public function handle(SubscriptionService $subscriptions): int
    {
        $this->sendTrialEndingReminders();
        $this->expireLapsedSubscriptions($subscriptions);

        return self::SUCCESS;
    }

    private function sendTrialEndingReminders(): void
    {
        $reminderWindow = Carbon::now()->addDays(3);

        Subscription::query()
            ->where('status', Subscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [Carbon::now(), $reminderWindow])
            ->with('business.owner')
            ->get()
            ->each(function (Subscription $subscription) {
                $subscription->business?->owner?->notify(new TrialEndingSoonNotification($subscription));
            });

        $this->info('Trial-ending reminders sent.');
    }

    private function expireLapsedSubscriptions(SubscriptionService $subscriptions): void
    {
        $lapsedTrials = Subscription::query()
            ->where('status', Subscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', Carbon::now())
            ->get();

        $lapsedPeriods = Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', Carbon::now())
            ->get();

        $lapsedTrials->merge($lapsedPeriods)->each(function (Subscription $subscription) use ($subscriptions) {
            $subscriptions->expire($subscription);
        });

        $this->info("Expired {$lapsedTrials->count()} trial(s) and {$lapsedPeriods->count()} subscription(s).");
    }
}
