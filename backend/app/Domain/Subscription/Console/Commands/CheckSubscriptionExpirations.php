<?php

namespace App\Domain\Subscription\Console\Commands;

use App\Domain\Subscription\Models\Subscription;
use App\Domain\Subscription\Notifications\SubscriptionExpiringSoonNotification;
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
        $this->sendRenewalReminders();
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

    /**
     * Warn paying customers before their term ends.
     *
     * Only on specific days, not every day for a month. A daily reminder
     * for thirty days teaches the recipient that mail from this sender can
     * be deleted unread — which costs more than the reminders are worth,
     * and does its damage precisely on the day the message matters.
     *
     * Thirty days is the first because that is the notice a business needs
     * to move money; the rest close in as the deadline does.
     *
     * @var array<int, int>
     */
    private const REMINDER_DAYS = [30, 14, 7, 3, 1];

    private function sendRenewalReminders(): void
    {
        $sent = 0;

        foreach (self::REMINDER_DAYS as $days) {
            // A whole-day window, so a subscription is caught once no
            // matter what time the command happens to run. Comparing to an
            // exact timestamp would miss any subscription whose expiry
            // moment falls outside the minute this runs.
            $target = Carbon::now()->addDays($days);

            Subscription::query()
                ->where('status', Subscription::STATUS_ACTIVE)
                ->whereNotNull('current_period_end')
                ->whereBetween('current_period_end', [$target->copy()->startOfDay(), $target->copy()->endOfDay()])
                ->with('business.owner')
                ->get()
                ->each(function (Subscription $subscription) use ($days, &$sent) {
                    $subscription->business?->owner?->notify(
                        new SubscriptionExpiringSoonNotification($subscription, $days),
                    );

                    $sent++;
                });
        }

        $this->info("Sent {$sent} renewal reminder(s).");
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
