<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('inventory:check-alerts')->dailyAt('07:00');
Schedule::command('subscriptions:check-expirations')->daily();
Schedule::command('monitoring:snapshot')->everyFiveMinutes();
Schedule::command('backup:scheduled database')->daily();
Schedule::command('backup:scheduled full')->weekly();

/*
 * Platform alert email.
 *
 * Every minute is what "immediately" means for a derived feed — there is
 * no event to hook, only conditions that become true. `withoutOverlapping`
 * matters because the run reconciles notification state: two concurrent
 * runs could both see an alert as unemailed and send it twice, which is
 * the one failure this whole mechanism exists to prevent.
 *
 * `runInBackground` keeps a slow SMTP host from delaying the rest of the
 * schedule behind it.
 */
Schedule::command('platform:dispatch-alerts')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
