<?php

namespace App\Domain\Shared\Concerns;

use App\Domain\Business\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Enforces multi-tenant isolation: every query against the model is scoped
 * to the authenticated business user's business_id. Platform (Super Admin)
 * guard sessions bypass the scope so the platform can manage all tenants.
 * New records are automatically stamped with the current business_id.
 *
 * Resolves the acting user from either the `web` session guard (browser
 * app) or the `sanctum` token guard (Flutter Desktop / future API
 * clients) — a business user is a business user regardless of which
 * client authenticated them.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::guard('platform')->check()) {
                return;
            }

            $businessId = static::resolveTenantBusinessId();

            if ($businessId !== null) {
                $builder->where($builder->getModel()->getTable().'.business_id', $businessId);
            }
        });

        static::creating(function (Model $model) {
            if ($model->business_id === null) {
                $model->business_id = static::resolveTenantBusinessId();
            }
        });
    }

    /**
     * `sanctum` is checked before `web`, deliberately. `web`'s
     * `SessionGuard::check()` reflects whatever user was last attached to
     * that guard instance and does **not** re-validate against the current
     * request — in production that's fine (a session guard's state *is*
     * the current request's session), but Laravel's testing HTTP client
     * reuses one application/container across every simulated request in a
     * test method, so a `SessionGuard` populated by an earlier `actingAs()`
     * call stays "checked" for every subsequent simulated request in that
     * test, even ones authenticating as a completely different user via a
     * different guard. `sanctum`'s guard (`TokenGuard`/`RequestGuard`)
     * doesn't have this problem — it re-resolves the bearer token from the
     * *current* request's `Authorization` header on every `check()`/`user()`
     * call, so it can never return stale state from an earlier request.
     * Checking it first means a genuine Sanctum-authenticated request
     * always wins over a merely-cached `web` session, in both tests and
     * production (a real browser session request never carries a bearer
     * token in the first place, so this reordering changes nothing for the
     * `web`-only case).
     */
    protected static function resolveTenantBusinessId(): ?string
    {
        $user = Auth::guard('sanctum')->user() ?? Auth::guard('web')->user();

        return $user?->business_id;
    }

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
