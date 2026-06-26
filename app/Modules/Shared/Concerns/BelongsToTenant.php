<?php

namespace App\Modules\Shared\Concerns;

use App\Modules\Business\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Enforces multi-tenant isolation: every query against the model is scoped
 * to the authenticated business user's business_id. Platform (Super Admin)
 * guard sessions bypass the scope so the platform can manage all tenants.
 * New records are automatically stamped with the current business_id.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::guard('platform')->check()) {
                return;
            }

            if (Auth::guard('web')->check()) {
                $builder->where($builder->getModel()->getTable().'.business_id', Auth::guard('web')->user()->business_id);
            }
        });

        static::creating(function (Model $model) {
            if ($model->business_id === null && Auth::guard('web')->check()) {
                $model->business_id = Auth::guard('web')->user()->business_id;
            }
        });
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
