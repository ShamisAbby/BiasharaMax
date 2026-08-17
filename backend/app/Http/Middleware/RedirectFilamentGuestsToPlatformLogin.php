<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate;

/**
 * Filament's own Authenticate::redirectTo() returns Filament::getLoginUrl(),
 * which is null whenever a panel's built-in login is disabled (see
 * PlatformPanelProvider — ->login() is deliberately not called, since this
 * app keeps its existing platform.login/2FA/signed-invitation flow rather
 * than Filament's own auth pages). A null redirectTo() falls through to
 * Laravel's default AuthenticationException handling, which redirects to
 * the `login` named route (the *tenant* web-guard login) — wrong for a
 * platform-panel visitor. Overriding just the redirect target here, while
 * keeping the parent's authenticate()/canAccessPanel() production gate
 * intact, is the minimal fix.
 */
class RedirectFilamentGuestsToPlatformLogin extends Authenticate
{
    protected function redirectTo($request): ?string
    {
        return route('platform.login');
    }
}
