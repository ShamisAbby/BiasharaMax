<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roleSlugs): Response
    {
        // Reads every assigned role, not just the legacy `role_id`
        // column: a user now holds any number of roles and passes if ANY
        // of them matches.
        $userRoleSlugs = $request->user()?->roles->pluck('slug')->all() ?? [];

        abort_unless(
            array_intersect($userRoleSlugs, $roleSlugs) !== [],
            403,
        );

        return $next($request);
    }
}
