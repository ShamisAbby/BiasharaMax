<?php

namespace App\Domain\Platform\Http\Controllers;

use App\Domain\Platform\Support\AdminSurface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Switches an administrator between the two admin surfaces.
 *
 * Both are served to the same `platform` guard, so this neither signs
 * anyone out nor in — it records a preference and redirects. The session
 * is untouched, which is what makes the switch instant.
 *
 * Deliberately *not* a gate. The preference decides where an admin lands
 * after signing in and where the two root URLs send them; it never
 * blocks the other surface. Two reasons, and the second is the one that
 * would bite:
 *
 *  - A screen that exists on only one surface has to stay reachable from
 *    a link or a bookmark regardless of the setting.
 *  - Enforcing the preference on every route means /admin bounces to
 *    /platform, whose own guard bounces back. A redirect loop is very
 *    easy to write here and impossible to recover from in the browser.
 */
class AdminSurfaceController extends Controller
{
    public function update(Request $request): Response
    {
        $validated = $request->validate([
            'surface' => ['required', 'string', Rule::in(AdminSurface::all())],
        ]);

        $user = Auth::guard('platform')->user();

        abort_unless($user !== null, 403);

        $user->forceFill(['preferred_admin_surface' => $validated['surface']])->save();

        /*
         * `Inertia::location()`, not `redirect()`.
         *
         * The destination is the *other application*. A plain redirect is
         * fine for the Blade form on the Filament side, but the React
         * switcher posts through Inertia's router — which follows the
         * redirect asking for an Inertia payload, receives a Livewire HTML
         * page, and cannot parse it. In development that surfaces as
         * Inertia's error overlay rendering the Filament panel inside a
         * modal on top of /admin; in production it fails silently and the
         * browser simply stays put.
         *
         * `location()` is Inertia's answer to precisely this: it returns
         * 409 with an `X-Inertia-Location` header, which the client turns
         * into a full page visit. For a non-Inertia request — the Blade
         * form — it degrades to an ordinary 302, so one line serves both
         * switchers.
         *
         * The path is hard-coded rather than resolved through route():
         * the Filament panel registers its own routes under a different
         * name prefix, and pretending a single route() call covers both
         * applications would be the more fragile lie.
         */
        return Inertia::location(AdminSurface::path($validated['surface']));
    }
}
