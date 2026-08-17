<?php

namespace App\Domain\Security\Services;

use App\Domain\Security\Models\AccountLockout;
use App\Domain\Security\Models\BlockedIp;
use App\Domain\Security\Models\FailedLoginAttempt;
use App\Domain\Security\Models\SecurityAlert;
use App\Domain\Security\Models\TrustedDevice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;
use Illuminate\Validation\ValidationException;

/**
 * The enforcement and detection layer behind the Security Center.
 *
 * Laravel's RateLimiter still owns short-term throttling; this owns the
 * durable side — the queryable history of failed attempts, the admin's
 * explicit IP blocks and account lockouts, and the alerts raised from
 * both.
 *
 * Everything here is called from the two login requests
 * (PlatformLoginRequest and LoginRequest); nothing else needs to know
 * about it.
 */
class LoginSecurityService
{
    private const BRUTE_FORCE_THRESHOLD = 5;

    private const BRUTE_FORCE_WINDOW_MINUTES = 15;

    /** How long one open alert suppresses duplicates of the same kind. */
    private const ALERT_DEDUP_WINDOW_MINUTES = 15;

    /**
     * Logins outside this range raise a low-severity suspicious-login
     * alert. Deliberately wide: the point is to notice a 03:00 sign-in
     * on an account that only ever works office hours, not to flag
     * anyone working late.
     */
    private const UNUSUAL_HOUR_START = 1;

    private const UNUSUAL_HOUR_END = 5;

    public function recordFailedAttempt(string $email, string $guard, ?string $reason = null): FailedLoginAttempt
    {
        $attempt = FailedLoginAttempt::query()->create([
            'email' => $email,
            'guard' => $guard,
            // `failed_login_attempts.ip_address` is NOT NULL, unlike the
            // other tables here, so a request with no resolvable IP
            // (console, some proxy setups) must not insert null.
            'ip_address' => Request::ip() ?? '0.0.0.0',
            'user_agent' => Request::userAgent(),
            'reason' => $reason,
        ]);

        $this->checkForBruteForce($email, $guard);

        return $attempt;
    }

    public function isIpBlocked(string $ipAddress): bool
    {
        return BlockedIp::query()
            ->where('ip_address', $ipAddress)
            ->get()
            ->contains(fn (BlockedIp $blocked) => $blocked->isActive());
    }

    public function isAccountLocked(string $lockableType, string $lockableId): bool
    {
        return AccountLockout::query()
            ->where('lockable_type', $lockableType)
            ->where('lockable_id', $lockableId)
            ->get()
            ->contains(fn (AccountLockout $lockout) => $lockout->isActive());
    }

    /**
     * Refuses the request outright when the caller's IP is blocked, and
     * records the attempt so an admin can see the block doing its job.
     *
     * Thrown as a ValidationException on the identifier field so it
     * surfaces the same way a wrong password does — a blocked visitor is
     * told nothing useful about why.
     *
     * @throws ValidationException
     */
    public function ensureIpIsNotBlocked(string $identifierField = 'email'): void
    {
        $ip = Request::ip();

        if ($ip === null || ! $this->isIpBlocked($ip)) {
            return;
        }

        // Deduplicated per IP per window. This fires before the rate
        // limiter (a blocked IP shouldn't reach a credential check at
        // all), so without dedup every single POST from that IP would
        // insert another row — an unthrottled write amplification against
        // the very table the Security Center reads.
        $alreadyOpen = SecurityAlert::query()
            ->where('type', SecurityAlert::TYPE_BLOCKED_IP_ATTEMPT)
            ->where('ip_address', $ip)
            ->where('is_resolved', false)
            ->where('created_at', '>=', now()->subMinutes(self::ALERT_DEDUP_WINDOW_MINUTES))
            ->exists();

        if (! $alreadyOpen) {
            SecurityAlert::query()->create([
                'type' => SecurityAlert::TYPE_BLOCKED_IP_ATTEMPT,
                'severity' => SecurityAlert::SEVERITY_MEDIUM,
                'ip_address' => $ip,
                'description' => "Blocked IP {$ip} attempted to sign in.",
                'metadata' => ['user_agent' => Request::userAgent()],
            ]);
        }

        throw ValidationException::withMessages([
            $identifierField => trans('auth.failed'),
        ]);
    }

    /**
     * Post-login detection. Raises a new-device alert the first time an
     * account is seen from a given browser/IP combination, and a
     * suspicious-login alert for sign-ins in the small hours.
     *
     * The device is recorded either way, so the same device only ever
     * alerts once.
     */
    public function recordSuccessfulLogin(Model $user, string $authenticatableType): void
    {
        $fingerprint = $this->deviceFingerprint();

        $key = [
            'authenticatable_type' => $authenticatableType,
            'authenticatable_id' => $user->getKey(),
            'device_fingerprint' => $fingerprint,
        ];

        $isNewDevice = ! TrustedDevice::query()->where($key)->exists();

        if ($isNewDevice) {
            SecurityAlert::query()->create([
                'type' => SecurityAlert::TYPE_NEW_DEVICE,
                'severity' => SecurityAlert::SEVERITY_LOW,
                'ip_address' => Request::ip(),
                'description' => "First sign-in for {$user->getAttribute('email')} from a new device.",
                'metadata' => [
                    'authenticatable_type' => $authenticatableType,
                    'authenticatable_id' => $user->getKey(),
                    'user_agent' => Request::userAgent(),
                ],
            ]);
        }

        // updateOrCreate rather than firstOrNew+save: two concurrent
        // logins from the same device would otherwise collide on the
        // unique (authenticatable, fingerprint) index and 500 the login.
        TrustedDevice::query()->updateOrCreate($key, [
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'last_used_at' => now(),
        ]);

        $this->checkForUnusualHour($user, $authenticatableType);
    }

    /**
     * Raised centrally from the exception handler whenever an
     * authenticated user is refused by a permission check — a signal
     * that someone is probing for access they don't have.
     */
    public function recordPermissionViolation(Model $user, string $path): void
    {
        SecurityAlert::query()->create([
            'type' => SecurityAlert::TYPE_PERMISSION_VIOLATION,
            'severity' => SecurityAlert::SEVERITY_MEDIUM,
            'ip_address' => Request::ip(),
            'description' => "{$user->getAttribute('email')} was denied access to {$path}.",
            'metadata' => [
                'user_id' => $user->getKey(),
                'path' => $path,
                'user_agent' => Request::userAgent(),
            ],
        ]);
    }

    private function checkForBruteForce(string $email, string $guard): void
    {
        $recentFailures = FailedLoginAttempt::query()
            ->where('email', $email)
            ->where('guard', $guard)
            ->where('created_at', '>=', now()->subMinutes(self::BRUTE_FORCE_WINDOW_MINUTES))
            ->count();

        if ($recentFailures < self::BRUTE_FORCE_THRESHOLD) {
            return;
        }

        // One open alert per email+guard: without this every further
        // attempt past the threshold would file another, burying the
        // Security Center under duplicates during a single attack.
        $alreadyOpen = SecurityAlert::query()
            ->where('type', SecurityAlert::TYPE_BRUTE_FORCE)
            ->where('is_resolved', false)
            ->where('created_at', '>=', now()->subMinutes(self::BRUTE_FORCE_WINDOW_MINUTES))
            ->whereJsonContains('metadata->email', $email)
            ->exists();

        if ($alreadyOpen) {
            return;
        }

        SecurityAlert::query()->create([
            'type' => SecurityAlert::TYPE_BRUTE_FORCE,
            'severity' => SecurityAlert::SEVERITY_HIGH,
            'ip_address' => Request::ip(),
            'description' => "{$recentFailures} failed login attempts for {$email} ({$guard}) within ".self::BRUTE_FORCE_WINDOW_MINUTES.' minutes.',
            'metadata' => ['email' => $email, 'guard' => $guard, 'attempts' => $recentFailures],
        ]);
    }

    private function checkForUnusualHour(Model $user, string $authenticatableType): void
    {
        $hour = (int) now()->format('G');

        if ($hour < self::UNUSUAL_HOUR_START || $hour > self::UNUSUAL_HOUR_END) {
            return;
        }

        SecurityAlert::query()->create([
            'type' => SecurityAlert::TYPE_SUSPICIOUS_LOGIN,
            'severity' => SecurityAlert::SEVERITY_LOW,
            'ip_address' => Request::ip(),
            'description' => "{$user->getAttribute('email')} signed in at ".now()->format('H:i').', outside normal hours.',
            'metadata' => [
                'authenticatable_type' => $authenticatableType,
                'authenticatable_id' => $user->getKey(),
                'hour' => $hour,
            ],
        ]);
    }

    /**
     * A device is identified by user agent + IP. Not a strong
     * fingerprint — a changed IP counts as a new device — but it needs
     * no client-side cooperation and errs toward alerting rather than
     * staying silent.
     */
    private function deviceFingerprint(): string
    {
        return hash('sha256', Request::userAgent().'|'.Request::ip());
    }
}
