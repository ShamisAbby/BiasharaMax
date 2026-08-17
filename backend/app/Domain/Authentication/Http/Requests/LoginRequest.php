<?php

namespace App\Domain\Authentication\Http\Requests;

use App\Domain\Authentication\Support\UserIdentityRules;
use App\Domain\Security\Models\AccountLockout;
use App\Domain\Security\Services\LoginSecurityService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use App\Domain\Authentication\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Accepts an email address OR a username — see
            // UserIdentityRules::loginColumn(). The `email` rule is
            // gone for that reason; the field name is unchanged so the
            // existing login page keeps working.
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
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

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            // Persists the attempt and raises a brute-force alert once
            // the threshold is crossed. RateLimiter still owns the
            // throttling itself; this is the durable record behind it.
            $security->recordFailedAttempt($identifier, 'web', 'invalid_credentials');

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        /** @var \App\Domain\Authentication\Models\User $user */
        $user = Auth::user();

        // Checked only after the password is verified, so a wrong
        // password on a locked account still reads as a wrong password
        // rather than confirming the account exists. The session is torn
        // down first — `attempt()` has already established it.
        if ($security->isAccountLocked(AccountLockout::TYPE_USER, (string) $user->getKey())) {
            Auth::logout();
            $security->recordFailedAttempt($identifier, 'web', 'account_locked');

            throw ValidationException::withMessages([
                'email' => 'This account has been locked. Contact an administrator.',
            ]);
        }

        // A suspended employee must not be able to sign in. Nothing
        // checked this before — on either guard — so suspending someone
        // on the Employees screen changed a badge and nothing else. The
        // session is torn down first, because `attempt()` above has
        // already established one.
        if ($user->status !== User::STATUS_ACTIVE) {
            Auth::logout();
            $security->recordFailedAttempt($identifier, 'web', 'account_inactive');

            throw ValidationException::withMessages([
                'email' => 'This account is not active. Contact an administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        // Detection is best-effort: a failure writing a device or
        // alert row must never turn a valid sign-in into a 500.
        rescue(fn () => $security->recordSuccessfulLogin($user, AccountLockout::TYPE_USER));

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
    }

    /**
     * Ensure the login request is not rate limited.
     *
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

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
