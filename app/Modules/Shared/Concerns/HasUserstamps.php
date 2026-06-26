<?php

namespace App\Modules\Shared\Concerns;

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

    protected static function currentActorId(): ?string
    {
        return Auth::guard('platform')->id() ?? Auth::guard('web')->id();
    }
}
