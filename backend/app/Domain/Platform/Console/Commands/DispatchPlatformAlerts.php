<?php

namespace App\Domain\Platform\Console\Commands;

use App\Domain\Platform\Notifications\PlatformAlertDigestNotification;
use App\Domain\Platform\Notifications\PlatformAlertNotification;
use App\Domain\Platform\Services\PlatformNotificationService;
use App\Domain\Platform\Services\PlatformNotificationStateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Emails new platform alerts, once each.
 *
 * Run from the scheduler rather than from the notification bell's poll
 * endpoint, which is the tempting shortcut and the wrong one: a GET that
 * sends email fires for whichever admin happens to load a page first,
 * sends nothing at all if nobody is signed in, and turns opening the
 * dashboard into an outbound SMTP call.
 *
 * "Immediately" is therefore every-minute scheduling. The delay is
 * bounded and the behaviour is the same whether or not anyone is
 * looking, which is the property an alerting system actually needs.
 *
 * @see \App\Domain\Platform\Services\PlatformNotificationStateService
 */
class DispatchPlatformAlerts extends Command
{
    protected $signature = 'platform:dispatch-alerts
                            {--dry-run : List what would be sent without sending or recording it}';

    protected $description = 'Email new platform alerts to the configured operator addresses';

    public function handle(
        PlatformNotificationService $feed,
        PlatformNotificationStateService $state,
    ): int {
        /*
         * `all()`, not `current()`. Dismissal controls what an operator
         * sees in the top bar; it must not silence the email for an
         * unresolved critical alert. Someone clicking Clear is saying "I
         * have seen this", not "do not tell the on-call".
         */
        $items = $feed->all();

        // Reconciles state and prunes keys whose underlying problem is
        // fixed, so this runs even when there is nothing to send.
        $unemailed = $state->unemailed($items);

        $sendable = collect($unemailed)
            ->filter(fn (array $item): bool => in_array(
                $item['severity'] ?? '',
                config('platform_notifications.email_severities', []),
                true,
            ))
            ->values();

        if ($sendable->isEmpty()) {
            $this->info('No new alerts to email.');

            return self::SUCCESS;
        }

        $recipients = config('platform_notifications.recipients', []);

        if ($recipients === []) {
            /*
             * Marked as emailed even though nothing was sent. Otherwise
             * configuring a recipient later would immediately deliver
             * every alert accumulated since install — the backlog flood
             * that makes people turn alerting off again.
             */
            $state->markEmailed($sendable->pluck('id')->all());

            $this->warn('No recipients configured (PLATFORM_ALERT_RECIPIENTS); '.$sendable->count().' alerts marked as seen.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($sendable as $item) {
                $this->line('would send: ['.$item['severity'].'] '.$item['title']);
            }

            return self::SUCCESS;
        }

        $threshold = (int) config('platform_notifications.max_individual_emails_per_run', 10);
        $route = Notification::route('mail', $recipients);

        if ($sendable->count() > $threshold) {
            $route->notify(new PlatformAlertDigestNotification($sendable->all()));

            $this->warn($sendable->count().' alerts exceeded the per-run limit; sent one digest instead.');
        } else {
            foreach ($sendable as $item) {
                $route->notify(new PlatformAlertNotification($item));
            }

            $this->info('Emailed '.$sendable->count().' alert(s).');
        }

        // After queueing, not after delivery — a send that fails retries
        // through the queue, whereas marking on delivery would re-send
        // the alert on every run until SMTP recovered.
        $state->markEmailed($sendable->pluck('id')->all());

        return self::SUCCESS;
    }
}
