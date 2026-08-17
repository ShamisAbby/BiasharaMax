<?php

namespace App\Domain\Platform\Http\Controllers\Auth;

use App\Domain\Platform\Http\Requests\PlatformLoginRequest;
use App\Domain\Platform\Support\AdminSurface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PlatformAuthenticatedSessionController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (Auth::guard('platform')->check()) {
            return redirect()->to($this->landingPath());
        }

        return Inertia::render('Platform/Auth/Login', [
            'status' => session('status'),
        ]);
    }

    public function store(PlatformLoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->to($this->destinationFor($request));
    }

    /**
     * Where to send an administrator who has just signed in.
     *
     * A deep link still wins — someone who followed a link to a specific
     * screen wants that screen. But **only if it is on the surface they
     * signed into**.
     *
     * That qualifier is the fix for a genuinely confusing bug: the two
     * admin surfaces share the `platform` guard, so hitting any
     * `/platform` URL while signed out stores it as `intended` and
     * bounces to `/admin/login`. Signing in there then dropped the
     * administrator into the Filament panel — a different application
     * from the one whose login form they had just filled in, and not the
     * one their preference asked for either. It looked like the setting
     * had been ignored.
     *
     * Cross-surface intended URLs are dropped rather than followed. The
     * cost is one extra click on the rare deep link into the other panel;
     * the alternative is a sign-in that lands somewhere you did not ask
     * for and cannot explain.
     */
    private function destinationFor(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');

        if (is_string($intended) && $this->isOnInertiaAdmin($intended)) {
            return $intended;
        }

        return $this->landingPath();
    }

    /**
     * Whether a stored intended URL belongs to the Inertia admin served
     * by this login form.
     */
    private function isOnInertiaAdmin(string $url): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        // The login page itself is never a destination — following it
        // would bounce a freshly authenticated admin straight back to a
        // form they have already completed.
        if ($path === '' || str_starts_with($path, 'admin/login')) {
            return false;
        }

        return AdminSurface::fromPath($path) === AdminSurface::INERTIA
            && str_starts_with($path, 'admin');
    }

    /**
     * Where a plain sign-in lands, honouring the administrator's chosen
     * surface. Falls back to the Inertia dashboard for anyone who has
     * never chosen — see AdminSurface::DEFAULT for why that one.
     */
    private function landingPath(): string
    {
        $user = Auth::guard('platform')->user();

        $surface = $user?->preferredAdminSurface() ?? AdminSurface::DEFAULT;

        return $surface === AdminSurface::FILAMENT
            ? AdminSurface::path(AdminSurface::FILAMENT)
            : route('platform.dashboard', absolute: false);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('platform')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
