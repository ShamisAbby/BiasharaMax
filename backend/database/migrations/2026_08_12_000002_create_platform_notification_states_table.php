<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memory for the platform's notification feed, which has none of its own.
 *
 * PlatformNotificationService derives its items live — unresolved
 * security alerts, failed backups, expiring licences, at-risk businesses
 * — and recomputes them on every poll. That is fine for displaying
 * current state and useless for two things the feed now has to do:
 *
 *  - **Dismissal.** Deleting a derived item is meaningless; it is
 *    regenerated a second later. Dismissal has to be recorded against
 *    the item's key instead.
 *  - **Emailing once.** With no record of what has been sent, an
 *    "email each alert immediately" rule would re-send every alert on
 *    every run, forever.
 *
 * One row per notification key, holding both facts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_notification_states', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            /*
             * The stable identifier the service generates, e.g.
             * `security-alert-<uuid>` or `churn-<business_id>`. Unique
             * because state is per notification, not per admin — a
             * dismissed platform alert is dismissed for the operator
             * team, and an emailed one must not be emailed again by
             * whoever polls next.
             */
            $table->string('notification_key')->unique();

            $table->string('type', 40);
            $table->string('severity', 20);

            $table->timestamp('first_seen_at');
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->uuid('dismissed_by')->nullable();

            $table->timestamps();

            // The feed filters on these two on every poll.
            $table->index('dismissed_at');
            $table->index('emailed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_notification_states');
    }
};
