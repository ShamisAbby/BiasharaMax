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
        if (Auth::guard('platform')->check()) {
            return Auth::guard('platform')->id();
        }

        return Auth::guard('sanctum')->id() ?? Auth::guard('web')->id();
    }
}
