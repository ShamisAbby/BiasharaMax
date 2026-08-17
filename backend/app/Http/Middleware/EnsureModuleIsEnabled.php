<?php

namespace App\Http\Middleware;

use App\Domain\ModuleManagement\Services\BusinessModuleResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks routes belonging to a module the business doesn't have.
 *
 * The sidebar already hides disabled sections, but hiding a link is not
 * access control — the URLs are guessable, they get bookmarked, and they
 * survive in browser history. Without this the "off" switch would be
 * decoration.
 *
 * Returns 404, not 403. A business that doesn't have the Payroll module
 * shouldn't be told that Payroll exists and they aren't allowed it; that
 * is what the Super Admin chose "hidden completely" to avoid. 403 would
 * also be wrong in substance: they are not being denied permission, the
 * feature is simply not part of their installation.
 */
class EnsureModuleIsEnabled
{
    public function __construct(
        private readonly BusinessModuleResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $business = $request->user()?->business;

        // No business means no module configuration to consult. Anything
        // that needs a business already fails further in; failing here
        // would turn a missing relation into a confusing 404.
        if ($business === null) {
            return $next($request);
        }

        foreach ($modules as $module) {
            if ($this->resolver->hasModule($business, $module)) {
                return $next($request);
            }
        }

        abort(404);
    }
}
