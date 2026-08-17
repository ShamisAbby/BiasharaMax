<?php

namespace App\Domain\Security\Services;

use App\Domain\Authentication\Models\PlatformUser;
use App\Domain\Authentication\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Who is currently signed in, for both admin surfaces' Security Centre.
 *
 * Shared because the two answered differently. The Filament page
 * declared that sessions cannot be listed unless SESSION_DRIVER is
 * `database`; the Inertia controller simply queried the `sessions` table
 * and reported whatever it found — which on this installation is nothing,
 * because the driver is Redis. Same question, same database, two answers:
 * one "we can't tell", one "nobody is online".
 *
 * The second is the dangerous one. On a Security Centre, "0 active
 * sessions" reads as a fact about the platform, not a limitation of how
 * it is configured — and it is exactly the number someone would check
 * before concluding an intruder had logged out.
 */
class ActiveSessionService
{
    /**
     * Sessions are read from the `sessions` table, which only exists as
     * a source of truth under the database driver. Redis and file
     * sessions are real but unqueryable this way.
     */
    public function areTracked(): bool
    {
        return config('session.driver') === 'database';
    }

    public function driver(): string
    {
        return (string) config('session.driver');
    }

    /**
     * Sessions active within the given window.
     *
     * Returns an empty collection when tracking is unavailable — callers
     * must pair this with [areTracked] to tell "nobody is signed in"
     * apart from "we cannot see who is".
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recent(int $minutes = 30, int $limit = 100): Collection
    {
        if (! $this->areTracked()) {
            return collect();
        }

        return DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes($minutes)->getTimestamp())
            ->orderByDesc('last_activity')
            ->limit($limit)
            ->get()
            ->map(function ($session): array {
                // A session row's user_id could belong to either guard,
                // and the two tables are separate — so a platform admin
                // is looked up first and a tenant user only if that
                // misses. Both missing means an unauthenticated session,
                // which is legitimate and shown as a guest.
                $platformUser = PlatformUser::find($session->user_id);
                $user = $platformUser ? null : User::find($session->user_id);

                return [
                    'id' => $session->id,
                    'user_name' => $platformUser?->name ?? $user?->name,
                    'user_type' => $platformUser ? 'platform_user' : ($user ? 'user' : null),
                    'ip_address' => $session->ip_address,
                    'last_activity' => $session->last_activity,
                ];
            });
    }
}
