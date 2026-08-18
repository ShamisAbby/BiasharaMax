import { Subscription } from '@/types';
import { Link } from '@inertiajs/react';

/**
 * The plan card at the foot of the sidebar.
 *
 * This is the only place a vendor sees their subscription without going
 * looking for it, so it has to show *state*, not just a name. The
 * previous version rendered a plan name and an "Upgrade Plan" button and
 * nothing else — which meant a past-due subscription, an expired one and
 * a healthy one were pixel-identical, and the one screen that could have
 * warned somebody their till was about to stop working said nothing at
 * all.
 */

type Tone = 'positive' | 'warning' | 'danger';

interface Presentation {
    tone: Tone;
    /** Short state word next to the plan name. */
    badge: string;
    /** What the button does — a verb the current state actually supports. */
    action: string;
    /** One line under the name, or null when there is nothing to say. */
    note: string | null;
    /**
     * Whether the days-remaining bar is meaningful. A cancelled plan has
     * a countdown; an expired one has nothing left to count.
     */
    countdown: boolean;
}

const TONES: Record<
    Tone,
    { shell: string; badge: string; bar: string; button: string }
> = {
    positive: {
        shell: 'border-indigo-100 bg-indigo-50 dark:border-indigo-500/20 dark:bg-indigo-900/20',
        badge: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-200',
        bar: 'bg-indigo-500',
        button: 'bg-indigo-600 hover:bg-indigo-700',
    },
    warning: {
        shell: 'border-amber-200 bg-amber-50 dark:border-amber-500/25 dark:bg-amber-900/20',
        badge: 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200',
        bar: 'bg-amber-500',
        button: 'bg-amber-600 hover:bg-amber-700',
    },
    danger: {
        shell: 'border-rose-200 bg-rose-50 dark:border-rose-500/25 dark:bg-rose-900/20',
        badge: 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-200',
        bar: 'bg-rose-500',
        button: 'bg-rose-600 hover:bg-rose-700',
    },
};

const CYCLE_LABELS: Record<string, string> = {
    monthly: 'Billed monthly',
    quarterly: 'Billed quarterly',
    yearly: 'Billed yearly',
};

function daysUntil(date: string | null): number | null {
    if (!date) {
        return null;
    }

    const target = new Date(date).getTime();

    if (Number.isNaN(target)) {
        return null;
    }

    // Ceiling, so the last partial day still reads as "1 day left" rather
    // than rounding down to zero while the plan is demonstrably still on.
    return Math.ceil((target - Date.now()) / 86_400_000);
}

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

/**
 * How far out the badge starts counting down.
 *
 * Deliberately the same number as the first reminder email. Two different
 * thresholds for "your plan is ending soon" is how an interface ends up
 * disagreeing with its own notifications.
 */
const RENEWAL_WINDOW_DAYS = 30;

function plural(days: number): string {
    return days === 1 ? '1 day' : `${days} days`;
}

/**
 * Derived from status *and* the clock, because the two disagree in normal
 * operation: a subscription stays `active` in the database right up to
 * the moment a scheduled job marks it expired, so a period end in the
 * past has to be believed over the stored status.
 */
export function presentSubscription(subscription: Subscription): Presentation {
    const endsAt = subscription.current_period_end;
    const remaining = daysUntil(endsAt);
    const lapsed = remaining !== null && remaining <= 0;

    switch (subscription.status) {
        case 'trialing': {
            const trialLeft = daysUntil(subscription.trial_ends_at);

            if (trialLeft !== null && trialLeft <= 0) {
                return {
                    tone: 'danger',
                    badge: 'Trial ended',
                    action: 'Choose a plan',
                    note: 'Your free trial is over.',
                    countdown: false,
                };
            }

            return {
                tone:
                    trialLeft !== null && trialLeft <= 3
                        ? 'warning'
                        : 'positive',
                badge: 'Trial',
                action: 'Choose a plan',
                note:
                    trialLeft === null
                        ? 'Free trial in progress.'
                        : `${plural(trialLeft)} left in your free trial.`,
                countdown: true,
            };
        }

        case 'pending_payment':
            return {
                tone: 'danger',
                badge: 'Payment pending',
                action: 'Complete payment',
                // Says what is missing rather than what is wrong. Nothing
                // has failed here — the customer picked a plan and has not
                // paid yet, and telling them their account is "inactive"
                // invites a support message instead of a payment.
                note: 'Your plan starts once payment is confirmed.',
                countdown: false,
            };

        case 'past_due':
            return {
                tone: 'danger',
                badge: 'Payment due',
                // Not "Upgrade" — nothing about a larger plan fixes a
                // failed payment, and sending someone to a pricing page
                // to solve a billing problem wastes the one click they
                // were willing to give you.
                action: 'Settle payment',
                note: 'We could not take your last payment.',
                countdown: false,
            };

        case 'canceled':
            return {
                tone: 'warning',
                badge: 'Cancelled',
                action: 'Reactivate plan',
                note: endsAt
                    ? `Access continues until ${formatDate(endsAt)}.`
                    : 'This plan will not renew.',
                countdown: true,
            };

        case 'expired':
            return {
                tone: 'danger',
                badge: 'Expired',
                action: 'Renew plan',
                note: 'Renew to restore full access.',
                countdown: false,
            };

        case 'active':
        default: {
            if (lapsed) {
                return {
                    tone: 'danger',
                    badge: 'Expired',
                    action: 'Renew plan',
                    note: 'Your billing period has ended.',
                    countdown: false,
                };
            }

            // Thirty days, matching the first reminder email rather than
            // the seven days this used to use. The two disagreeing meant
            // an owner could receive "your plan ends in 30 days" and then
            // see a calm green "Active" badge in the app for three more
            // weeks — the interface quietly contradicting the warning.
            if (remaining !== null && remaining <= RENEWAL_WINDOW_DAYS) {
                return {
                    tone: remaining <= 7 ? 'danger' : 'warning',
                    badge:
                        remaining <= 7
                            ? `Ends in ${plural(remaining)}`
                            : 'Renews soon',
                    action: 'Renew plan',
                    note: `Renews in ${plural(remaining)}.`,
                    countdown: true,
                };
            }

            return {
                tone: 'positive',
                badge: 'Active',
                // "Manage" rather than "Upgrade": the sidebar has no idea
                // whether a higher plan exists, and promising one to a
                // business already on the top tier is a dead end.
                action: 'Manage plan',
                note: endsAt
                    ? `Renews ${formatDate(endsAt)}`
                    : subscription.billing_cycle
                      ? CYCLE_LABELS[subscription.billing_cycle]
                      : null,
                countdown: true,
            };
        }
    }
}

export default function SubscriptionCard({
    subscription,
    href,
}: {
    subscription: Subscription;
    href: string;
}) {
    const plan = subscription.plan;

    if (!plan) {
        return null;
    }

    const view = presentSubscription(subscription);
    const tone = TONES[view.tone];

    const start = subscription.current_period_start;
    const end = subscription.current_period_end;

    // A bar only where both ends are known. Drawing one from a guessed
    // start date would show a confident, wrong amount of time left.
    let elapsed: number | null = null;

    if (view.countdown && start && end) {
        const from = new Date(start).getTime();
        const to = new Date(end).getTime();

        if (!Number.isNaN(from) && !Number.isNaN(to) && to > from) {
            elapsed = Math.min(
                100,
                Math.max(0, ((Date.now() - from) / (to - from)) * 100),
            );
        }
    }

    return (
        <div className={`m-3 rounded-xl border p-3.5 ${tone.shell}`}>
            <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {plan.name}
                    </p>
                    {subscription.billing_cycle &&
                        view.note?.startsWith('Renews') && (
                            <p className="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                {CYCLE_LABELS[subscription.billing_cycle]}
                            </p>
                        )}
                </div>
                <span
                    className={`shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${tone.badge}`}
                >
                    {view.badge}
                </span>
            </div>

            {elapsed !== null && (
                <div
                    className="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"
                    role="progressbar"
                    aria-valuenow={Math.round(elapsed)}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-label="Billing period elapsed"
                >
                    <div
                        className={`h-full rounded-full ${tone.bar}`}
                        style={{ width: `${elapsed}%` }}
                    />
                </div>
            )}

            {view.note && (
                <p className="mt-2 text-xs leading-snug text-gray-600 dark:text-gray-400">
                    {view.note}
                </p>
            )}

            <Link
                href={href}
                className={`mt-3 block rounded-lg px-3 py-2 text-center text-xs font-semibold text-white transition ${tone.button}`}
            >
                {view.action}
            </Link>
        </div>
    );
}
