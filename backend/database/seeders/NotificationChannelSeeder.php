<?php

namespace Database\Seeders;

use App\Domain\Notifications\Models\NotificationChannel;
use Illuminate\Database\Seeder;

/**
 * Registers the notification channel catalog, mirroring the
 * PaymentGatewaySeeder pattern: every row is a real, selectable channel
 * that simply isn't configured yet.
 */
class NotificationChannelSeeder extends Seeder
{
    private const CATALOG = [
        /*
         * First, and the only one that can be enabled immediately.
         *
         * In-app delivers into the recipient's notification bell through
         * InAppDriver — no provider, no credentials, no network call. It
         * is what makes the Notification Centre usable on a fresh
         * installation instead of a screen where every campaign fails.
         */
        ['name' => 'In-app', 'channel' => NotificationChannel::CHANNEL_IN_APP, 'provider' => 'database'],
        ['name' => 'Email (SMTP)', 'channel' => NotificationChannel::CHANNEL_EMAIL, 'provider' => 'smtp'],
        ['name' => 'SMS (Africa\'s Talking)', 'channel' => NotificationChannel::CHANNEL_SMS, 'provider' => 'africastalking'],
        ['name' => 'WhatsApp Business', 'channel' => NotificationChannel::CHANNEL_WHATSAPP, 'provider' => 'whatsapp_business'],
        ['name' => 'Push (Firebase)', 'channel' => NotificationChannel::CHANNEL_PUSH, 'provider' => 'fcm'],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $index => $channel) {
            $existing = NotificationChannel::query()
                ->where('channel', $channel['channel'])
                ->where('provider', $channel['provider'])
                ->first();

            if ($existing) {
                /*
                 * Name and order only — never `is_enabled`.
                 *
                 * This previously passed `is_enabled => false` into
                 * updateOrCreate, so every re-run silently switched off
                 * every channel an operator had enabled and configured.
                 * A seeder that undoes an administrator's decisions is
                 * worse than one that does nothing.
                 */
                $existing->update([
                    'name' => $channel['name'],
                    'sort_order' => $index,
                ]);

                continue;
            }

            NotificationChannel::query()->create([
                'name' => $channel['name'],
                'channel' => $channel['channel'],
                'provider' => $channel['provider'],
                // New rows start disabled, including in-app: enabling a
                // channel is a deliberate act, and a seeder should not
                // start delivering messages on someone's behalf.
                'is_enabled' => false,
                'sort_order' => $index,
            ]);
        }
    }
}
