<?php

namespace App\Domain\Platform\Http\Requests;

use App\Domain\Authentication\Support\UserIdentityRules;
use App\Domain\Security\Models\AccountLockout;
use App\Domain\Security\Services\LoginSecurityService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Still posted as `email` so the existing login page keeps
            // working, but the value may now be either an email address
            // or a username — so the `email` rule is gone. Format is
            // settled by which column it is matched against; an
            // identifier that is neither simply fails to authenticate,
            // with the same generic message as a wrong password.
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $security = app(LoginSecurityService::class);

        // Before anything else: an IP an admin has blocked never gets as
        // far as a credential check.
        $security->ensureIpIsNotBlocked();

        $this->ensureIsNotRateLimited();

        $identifier = (string) $this->string('email');

        $credentials = [
            UserIdentityRules::loginColumn($identifier) => $identifier,
            'password' => (string) $this->string('password'),
        ];

        if (! Auth::guard('platform')->attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            // Persists the attempt and raises a brute-force alert once
            // the threshold is crossed. RateLimiter still owns the
            // throttling itself; this is the durable record behind it.
            $security->recordFailedAttempt($identifier, 'platform', 'invalid_credentials');

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        /** @var \App\Domain\Authentication\Models\PlatformUser $user */
        $user = Auth::guard('platform')->user();

        // Checked only after the password is verified, so a wrong
        // password on a locked account still reads as a wrong password
        // rather than confirming the account exists. The session is torn
        // down first — `attempt()` has already established it.
        if ($security->isAccountLocked(AccountLockout::TYPE_PLATFORM_USER, (string) $user->getKey())) {
            Auth::guard('platform')->logout();
            $security->recordFailedAttempt($identifier, 'platform', 'account_locked');

            throw ValidationException::withMessages([
                'email' => 'This account has been locked. Contact an administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // Detection is best-effort: a failure writing a device or
        // alert row must never turn a valid sign-in into a 500.
        rescue(fn () => $security->recordSuccessfulLogin($user, AccountLockout::TYPE_PLATFORM_USER));

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|platform|'.$this->ip());
    }
}
