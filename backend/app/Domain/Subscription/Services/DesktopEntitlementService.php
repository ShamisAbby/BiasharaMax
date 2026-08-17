<?php

namespace App\Domain\Subscription\Services;

use App\Domain\Business\Models\Business;
use App\Domain\Licensing\Models\License;
use App\Domain\Licensing\Models\LicenseDevice;
use App\Domain\Subscription\Models\Subscription;

/**
 * Answers one question for the desktop client: may this business use the
 * app right now, and if not, what would fix it?
 *
 * It lives on the server rather than in the Flutter app deliberately. The
 * client asking "am I allowed in?" and the server answering means the
 * rules can change — a grace period lengthened, a licence revoked — without
 * shipping a new build, and a tampered client cannot grant itself access
 * by editing a local flag.
 *
 * It also untangles two gates the desktop app had merged. A **licence**
 * authorises an installation on a particular machine; a **subscription**
 * is the business's entitlement. The app demanded a licence key before it
 * would even show a login box, but licences are only ever minted by hand
 * from the platform admin (LicenseService::generate) — so a business that
 * signed up for a trial had no key to type, and the desktop app was
 * unusable for every trial customer. Subscription decides admission here;
 * device licensing applies on top, and only where licences actually exist.
 */
class DesktopEntitlementService
{
    public const STATE_ALLOWED = 'allowed';

    /** Signed in, but the business has nothing to sign in against. */
    public const STATE_NO_SUBSCRIPTION = 'no_subscription';

    /** Trial or paid period is over and past its grace window. */
    public const STATE_LOCKED = 'locked';

    /**
     * The business holds licences, so this machine must be activated
     * against one before it can act as a till.
     */
    public const STATE_DEVICE_NOT_ACTIVATED = 'device_not_activated';

    /**
     * @return array{
     *     state: string,
     *     allowed: bool,
     *     message: string,
     *     can_start_trial: bool,
     *     requires_product_key: bool,
     *     subscription: null|array<string, mixed>,
     * }
     */
    public function describe(?Business $business, ?string $deviceFingerprint = null): array
    {
        if ($business === null) {
            return $this->state(
                self::STATE_NO_SUBSCRIPTION,
                'This account is not attached to a business.',
                canStartTrial: false,
                requiresProductKey: false,
            );
        }

        /** @var Subscription|null $subscription */
        $subscription = $business->subscription;

        if ($subscription === null) {
            return $this->state(
                self::STATE_NO_SUBSCRIPTION,
                'Your business has no subscription yet. Start a free trial or enter a product key.',
                canStartTrial: true,
                requiresProductKey: false,
            );
        }

        if ($subscription->isLocked()) {
            return $this->state(
                self::STATE_LOCKED,
                $subscription->status === Subscription::STATUS_TRIALING
                    ? 'Your free trial has ended. Enter a product key to continue.'
                    : 'Your subscription has ended. Enter a product key to continue.',
                // Not offered a second time. A trial that can be restarted
                // from the sign-in screen is not a trial, it is a free
                // product with extra steps.
                canStartTrial: false,
                requiresProductKey: true,
                subscription: $subscription,
            );
        }

        // Only businesses that actually hold licences are asked to
        // activate a device. Requiring it universally is what locked
        // trial businesses out, and inventing a licence for them would
        // hand out device slots nobody bought.
        if ($this->requiresDeviceActivation($business, $deviceFingerprint)) {
            return $this->state(
                self::STATE_DEVICE_NOT_ACTIVATED,
                'This computer is not activated yet. Enter your product key to use it as a till.',
                canStartTrial: false,
                requiresProductKey: true,
                subscription: $subscription,
            );
        }

        return $this->state(
            self::STATE_ALLOWED,
            'Subscription active.',
            canStartTrial: false,
            requiresProductKey: false,
            subscription: $subscription,
        );
    }

    private function requiresDeviceActivation(Business $business, ?string $deviceFingerprint): bool
    {
        $licences = License::query()
            ->where('business_id', $business->getKey())
            ->whereIn('status', [License::STATUS_ACTIVE, License::STATUS_EXPIRED, License::STATUS_SUSPENDED])
            ->pluck('id');

        if ($licences->isEmpty()) {
            return false;
        }

        if ($deviceFingerprint === null || $deviceFingerprint === '') {
            return true;
        }

        // `deactivated_at IS NULL`, not a status column — LicenseDevice has
        // no status. Deactivating is how an owner frees a seat after
        // replacing a machine, and counting that row as proof of
        // activation would make the release meaningless.
        return ! LicenseDevice::query()
            ->whereIn('license_id', $licences)
            ->where('hardware_fingerprint', $deviceFingerprint)
            ->whereNull('deactivated_at')
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function state(
        string $state,
        string $message,
        bool $canStartTrial,
        bool $requiresProductKey,
        ?Subscription $subscription = null,
    ): array {
        return [
            'state' => $state,
            'allowed' => $state === self::STATE_ALLOWED,
            'message' => $message,
            'can_start_trial' => $canStartTrial,
            'requires_product_key' => $requiresProductKey,
            'subscription' => $subscription === null ? null : [
                'status' => $subscription->status,
                'plan_name' => $subscription->plan?->name,
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
                // Pre-computed rather than left to the client. Two clocks
                // disagreeing is how a till shows "3 days left" on the day
                // access is cut off.
                'days_remaining' => $this->daysRemaining($subscription),
            ],
        ];
    }

    private function daysRemaining(Subscription $subscription): ?int
    {
        $end = $subscription->status === Subscription::STATUS_TRIALING
            ? $subscription->trial_ends_at
            : $subscription->current_period_end;

        if ($end === null) {
            return null;
        }

        // `diffInDays`, not `floatDiffInDays` — the latter is Carbon 2 and
        // was removed in Carbon 3, which this project is on. In Carbon 3
        // diffInDays already returns a signed float, so the second
        // argument is what keeps a past date negative rather than
        // reporting a lapsed trial as having days left.
        return max(0, (int) ceil(now()->diffInDays($end, false)));
    }
}
