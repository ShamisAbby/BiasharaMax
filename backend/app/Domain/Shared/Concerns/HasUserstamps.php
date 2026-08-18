<?php

namespace App\Domain\Shared\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Automatically stamps created_by / updated_by / deleted_by with the id of
 * whichever guard (business user or platform user) is currently authenticated.
 */
trait HasUserstamps
{
    public static function bootHasUserstamps(): void
    {
        static::creating(function (Model $model) {
            $actorId = static::currentActorId();

            if ($actorId !== null && $model->isFillable('created_by')) {
                $model->created_by ??= $actorId;
                $model->updated_by ??= $actorId;
            }
        });

        static::updating(function (Model $model) {
            $actorId = static::currentActorId();

            if ($actorId !== null && $model->isFillable('updated_by')) {
                $model->updated_by = $actorId;
            }
        });

        static::deleting(function (Model $model) {
            $actorId = static::currentActorId();

            if ($actorId !== null && $model->isFillable('deleted_by') && method_exists($model, 'trashed')) {
                $model->deleted_by = $actorId;
                $model->saveQuietly();
            }
        });
    }

    /**
     * See the matching docblock on BelongsToTenant::resolveTenantBusinessId()
     * for why `sanctum` is checked before `web` — `sanctum`'s guard
     * re-validates the current request's bearer token on every call rather
     * than returning cached state, so it can never win over a genuinely
     * current `web` session, but a stale cached `web` session can
     * (incorrectly) win over a genuinely current `sanctum` request if
     * checked first. `platform` stays a special-cased first check — a
     * SuperAdmin acting on a tenant-owned record should always be
     * attributed to the platform guard.
     */
    protected static function currentActorId(): ?string
    {
        // A model whose userstamp columns are foreign-keyed to one table
        // must never be stamped with an id from the other.
        //
        // `payment_transactions.created_by` references `platform_users`,
        // which was fine while only SuperAdmins created transactions. The
        // moment a vendor renewed their own subscription, this trait wrote
        // a `users` id into that column and MySQL refused the insert with
        // a foreign key violation — a 500 on the renew button, and an
        // error naming a constraint rather than the assumption behind it.
        //
        // Fifteen tables key their userstamps to `platform_users`. Any of
        // them written by a vendor would fail the same way, so the guard is
        // declared per model rather than patched at one call site.
        $required = static::userstampGuard();

        if ($required !== null) {
            return Auth::guard($required)->check()
                ? Auth::guard($required)->id()
                // Null rather than a wrong id. Losing the attribution is a
                // smaller loss than failing the write, and a foreign key
                // pointing at a row that does not exist is not attribution
                // anyway.
                : null;
        }

        if (Auth::guard('platform')->check()) {
            return Auth::guard('platform')->id();
        }

        return Auth::guard('sanctum')->id() ?? Auth::guard('web')->id();
    }

    /**
     * Which guard owns this model's userstamp columns.
     *
     * Null — the default — means either guard may stamp, which is correct
     * for tables whose `created_by` has no foreign key or references a
     * table both kinds of user appear in. Models whose userstamps are
     * keyed to `platform_users` should return `'platform'`.
     */
    protected static function userstampGuard(): ?string
    {
        return null;
    }
}
