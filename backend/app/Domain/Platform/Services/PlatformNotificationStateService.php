<?php

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Models\PlatformNotificationState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reconciles the live notification feed with what has been emailed and
 * dismissed.
 *
 * The feed is derived: PlatformNotificationService recomputes it from
 * current conditions every time it is asked. This class is the memory
 * bolted alongside it.
 *
 * The pruning rule is the interesting part. A state row is deleted the
 * moment its key stops appearing in the feed — that is, when the
 * underlying problem is fixed. So:
 *
 *  - Dismiss a failed-backup alert → stays hidden while that backup is
 *    still failed.
 *  - The backup succeeds → the item stops being generated → the state
 *    row is pruned.
 *  - The same backup fails again → a brand new item, un-dismissed, and
 *    emailed again.
 *
 * That is what makes "dismiss" mean "I have seen this" rather than
 * "never tell me about this again", which is the wrong promise for an
 * alert about something still broken.
 */
class PlatformNotificationStateService
{
    /**
     * Brings stored state in line with the current feed, and returns it
     * keyed by notification key.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<string, PlatformNotificationState>
     */
    public function sync(array $items): Collection
    {
        $keys = collect($items)->pluck('id')->all();

        /*
         * Resolved problems lose their memory. Done first, so a key that
         * vanished and came back within the same run is treated as new.
         *
         * An empty feed means every problem is fixed, so the unfiltered
         * delete is correct rather than an accident — it is spelled out
         * as a branch because a `whereNotIn` with an empty array matches
         * nothing in some drivers and everything in others, and quietly
         * wiping the table is not something to leave to that.
         */
        $prune = PlatformNotificationState::query();

        if ($keys !== []) {
            $prune->whereNotIn('notification_key', $keys);
        }

        $prune->delete();

        if ($items === []) {
            return collect();
        }

        $now = now();

        /*
         * `insertOrIgnore` rather than upsert: every column here except
         * `first_seen_at` is state we must not clobber. An upsert that
         * refreshed the row would wipe `emailed_at` on every poll and
         * re-send every alert — precisely the bug this table exists to
         * prevent.
         */
        DB::table('platform_notification_states')->insertOrIgnore(
            collect($items)->map(fn (array $item): array => [
                'id' => (string) Str::uuid(),
                'notification_key' => $item['id'],
                'type' => $item['type'],
                'severity' => $item['severity'],
                'first_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all(),
        );

        return PlatformNotificationState::query()
            ->whereIn('notification_key', $keys)
            ->get()
            ->keyBy('notification_key');
    }

    /**
     * Keys that have never been emailed.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function unemailed(array $items): array
    {
        $states = $this->sync($items);

        return collect($items)
            ->filter(fn (array $item): bool => ! ($states[$item['id']] ?? null)?->hasBeenEmailed())
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $keys
     */
    public function markEmailed(array $keys): void
    {
        if ($keys === []) {
            return;
        }

        PlatformNotificationState::query()
            ->whereIn('notification_key', $keys)
            ->update(['emailed_at' => now()]);
    }

    public function dismiss(string $key, ?string $platformUserId = null): void
    {
        PlatformNotificationState::query()
            ->where('notification_key', $key)
            ->update([
                'dismissed_at' => now(),
                'dismissed_by' => $platformUserId,
            ]);
    }

    /**
     * Dismisses everything currently in the feed.
     *
     * Scoped to the keys passed in rather than updating the whole table,
     * so an item that appears between the operator loading the page and
     * pressing Clear is not silently dismissed unseen.
     *
     * @param  array<int, string>  $keys
     */
    public function dismissAll(array $keys, ?string $platformUserId = null): int
    {
        if ($keys === []) {
            return 0;
        }

        return PlatformNotificationState::query()
            ->whereIn('notification_key', $keys)
            ->whereNull('dismissed_at')
            ->update([
                'dismissed_at' => now(),
                'dismissed_by' => $platformUserId,
            ]);
    }

    /**
     * @return array<int, string>
     */
    public function dismissedKeys(): array
    {
        return PlatformNotificationState::query()
            ->whereNotNull('dismissed_at')
            ->pluck('notification_key')
            ->all();
    }
}
