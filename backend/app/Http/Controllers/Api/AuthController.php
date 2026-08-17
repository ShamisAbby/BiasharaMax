<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domain\Authentication\Models\User;
use App\Domain\Security\Models\AccountLockout;
use App\Domain\Security\Services\LoginSecurityService;
use App\Domain\Subscription\Services\DesktopEntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Token auth for non-browser clients (Flutter Desktop today; any future
 * mobile client the same way). The web app keeps using its session guard
 * unchanged — this exists alongside it, not instead of it.
 *
 * Deliberately separate from license activation (Licensing module): a
 * license activates the installation once per device; this authenticates
 * an individual employee on that installation, the same distinction the
 * web app already draws between "this business has a subscription" and
 * "this person is logged in".
 */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $security = app(LoginSecurityService::class);

        // The same controls the browser login runs, in the same order.
        //
        // These were missing here, and a security control that only
        // covers one of two doors is not a control: an IP an admin had
        // blocked, or an account that had been locked after repeated
        // failures, could simply log in through the desktop API instead.
        // Failed attempts weren't recorded either, so brute-force alerts
        // and automatic lockouts never fired for this route at all — the
        // Security Center would show a quiet afternoon during an attack.
        $security->ensureIpIsNotBlocked();

        $throttleKey = Str::transliterate(Str::lower($validated['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey);
            $security->recordFailedAttempt($validated['email'], 'sanctum', 'invalid_credentials');

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        // Checked only after the password verifies, so a wrong password on
        // a locked account still reads as a wrong password rather than
        // confirming the account exists.
        if ($security->isAccountLocked(AccountLockout::TYPE_USER, (string) $user->getKey())) {
            $security->recordFailedAttempt($validated['email'], 'sanctum', 'account_locked');

            throw ValidationException::withMessages([
                'email' => 'This account has been locked. Contact an administrator.',
            ]);
        }

        // A suspended employee must not be able to sign in — not here and
        // not on the web. Suspending someone on the Employees screen was
        // otherwise a label with no effect.
        if ($user->status !== User::STATUS_ACTIVE) {
            $security->recordFailedAttempt($validated['email'], 'sanctum', 'account_inactive');

            throw ValidationException::withMessages([
                'email' => 'This account is not active. Contact an administrator.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Best-effort: a failure writing a device or alert row must never
        // turn a valid sign-in into a 500.
        rescue(fn () => $security->recordSuccessfulLogin($user, AccountLockout::TYPE_USER));

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        // Scoped ability: a desktop token can act as this user for normal
        // business operations but can't be used to mint further tokens or
        // reach platform-only routes, even if the token leaks.
        $token = $user->createToken($validated['device_name'], ['desktop']);

        $user->loadMissing('business.subscription.plan');

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'business_id' => $user->business_id,
                'branch_id' => $user->branch_id,
                'role_id' => $user->role_id,
            ],
            // Sent with the login response so the client knows where to
            // send the user without a second round trip — and so a
            // business whose trial lapsed sees why on the screen after
            // sign-in rather than a dashboard that fails on first use.
            'entitlement' => app(DesktopEntitlementService::class)->describe(
                $user->business,
                $request->string('device_fingerprint')->value() ?: null,
            ),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * An explicit shape, not the model.
     *
     * `response()->json(['user' => $request->user()])` serialised every
     * column on the row. `password` and `remember_token` are hidden by
     * the model, but nothing else is — so a desktop token could read back
     * the account's phone number, lockout counters and audit columns for
     * no reason. The client needs six fields; it gets six fields.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('business.subscription.plan');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'business_id' => $user->business_id,
                'branch_id' => $user->branch_id,
                'role_id' => $user->role_id,
                'business_name' => $user->business?->name,
            ],
            'entitlement' => app(DesktopEntitlementService::class)->describe(
                $user->business,
                $request->string('device_fingerprint')->value() ?: null,
            ),
        ]);
    }
}
