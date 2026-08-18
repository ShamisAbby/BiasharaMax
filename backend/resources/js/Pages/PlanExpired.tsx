import ApplicationLogo from '@/Components/ApplicationLogo';
import { formatCurrency } from '@/lib/currency';
import { SubscriptionPlan } from '@/types';
import { ClockIcon } from '@heroicons/react/24/outline';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Where a business goes when its plan runs out.
 *
 * Separate from the suspension page on purpose. Both mean "no access", but
 * they are not the same message: an expired plan is an invoice, a
 * suspension is an accusation. Showing the suspension page for an expiry —
 * which this app did — tells a paying customer they have done something
 * wrong and sends them to support instead of to a renew button.
 *
 * Standalone, like the suspension page, so no part of the authenticated
 * layout has to render for an account that currently has no entitlements.
 *
 * Note what is deliberately absent: any offer of a free trial. The trial
 * is a one-time introduction to the product, not a way to keep using it
 * for free after paying once. Offering it here would let anyone extend
 * indefinitely by letting their plan lapse.
 */
export default function PlanExpired({
    businessName,
    plans,
    expiredOn,
    status,
    pendingPlanName,
    checkoutMessage,
}: {
    businessName?: string | null;
    plans: SubscriptionPlan[];
    expiredOn?: string | null;
    status?: string | null;
    /** Set when a renewal has been chosen and is waiting on payment. */
    pendingPlanName?: string | null;
    /**
     * What the payment gateway actually said.
     *
     * Replaces a hardcoded "online payment is not switched on yet", which
     * kept saying exactly that after Snippe had been switched on — the page
     * asserting something about the system that the system had stopped
     * agreeing with.
     */
    checkoutMessage?: string | null;
}) {
    const { processing } = useForm({});

    // Without this the page was identical before and after clicking Renew:
    // the request succeeded, the subscription moved to `pending_payment`,
    // and the screen redrew looking exactly the same. From the customer's
    // side that is indistinguishable from a broken button, and the natural
    // next move is to click it again.
    const awaitingPayment =
        status === 'renewal-started' ||
        status === 'payment-pending' ||
        status === 'payment-failed' ||
        !!pendingPlanName;

    const [checking, setChecking] = useState(false);

    /**
     * Ask the server whether the money has landed, every few seconds.
     *
     * Mobile money has no return trip: the customer approves a USSD prompt
     * on the handset and the browser is never navigated anywhere. Without
     * this the page simply sat there after a successful payment — which is
     * exactly what happened, and from the customer's side is
     * indistinguishable from having been charged for nothing.
     *
     * The endpoint asks the gateway, so this also recovers a webhook that
     * never arrived rather than waiting on one forever.
     */
    useEffect(() => {
        if (!awaitingPayment) {
            return;
        }

        let cancelled = false;

        const poll = async () => {
            try {
                const response = await window.axios.get(
                    route('subscription.payment-status'),
                );

                if (!cancelled && response?.data?.state === 'paid') {
                    // A full visit, not a partial reload: the account has
                    // just regained access and every shared prop — modules,
                    // permissions, subscription — is now different.
                    router.visit(route('dashboard'));
                }
            } catch {
                // Silent by design. This runs every few seconds; a blocked
                // or offline poll must not throw a banner at someone who is
                // mid-payment.
            }
        };

        void poll();
        const interval = setInterval(poll, 5000);

        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, [awaitingPayment]);

    const checkNow = async () => {
        setChecking(true);

        try {
            const response = await window.axios.get(
                route('subscription.payment-status'),
            );

            if (response?.data?.state === 'paid') {
                router.visit(route('dashboard'));

                return;
            }
        } finally {
            setChecking(false);
        }
    };

    return (
        <>
            <Head title="Plan expired" />

            <div className="flex min-h-screen flex-col bg-gray-50 dark:bg-gray-950">
                <header className="px-6 py-6 sm:px-10">
                    <div className="flex items-center gap-3">
                        <ApplicationLogo className="h-9 w-9 fill-current text-indigo-600 dark:text-indigo-400" />
                        <span className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            BiasharaMax
                        </span>
                    </div>
                </header>

                <main className="flex flex-1 items-start justify-center px-6 pb-16 pt-4">
                    <div className="w-full max-w-3xl text-center">
                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-500/15">
                            <ClockIcon className="h-8 w-8 text-indigo-600 dark:text-indigo-400" />
                        </div>

                        <h1 className="mt-6 text-2xl font-bold text-gray-900 dark:text-gray-100 sm:text-3xl">
                            Your plan has expired
                        </h1>

                        <p className="mt-4 text-base leading-relaxed text-gray-600 dark:text-gray-400">
                            {businessName ? (
                                <>
                                    The subscription for{' '}
                                    <span className="font-semibold text-gray-900 dark:text-gray-200">
                                        {businessName}
                                    </span>{' '}
                                    ended
                                </>
                            ) : (
                                <>Your subscription ended</>
                            )}
                            {expiredOn ? ` on ${expiredOn}` : ''}. Choose a plan
                            below to pick up exactly where you left off — your
                            data is untouched.
                        </p>

                        {awaitingPayment && (
                            <div className="mx-auto mt-6 max-w-xl rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 text-left dark:border-amber-500/40 dark:bg-amber-500/10">
                                <p className="font-semibold text-amber-900 dark:text-amber-200">
                                    {pendingPlanName
                                        ? `${pendingPlanName} selected — payment not yet received`
                                        : 'Payment not yet received'}
                                </p>
                                <p className="mt-1 text-sm text-amber-800 dark:text-amber-300">
                                    {checkoutMessage ??
                                        'Your choice of plan has been recorded. Access returns as soon as payment clears.'}
                                </p>

                                {/*
                                    A manual escape hatch beside the
                                    automatic poll. If someone paid and
                                    closed the tab, this is how they get
                                    their account back without contacting
                                    support.
                                */}
                                <button
                                    type="button"
                                    onClick={checkNow}
                                    disabled={checking}
                                    className="mt-3 rounded-lg border border-amber-400 px-3 py-1.5 text-sm font-medium text-amber-900 transition hover:bg-amber-100 disabled:opacity-60 dark:border-amber-500/40 dark:text-amber-200 dark:hover:bg-amber-500/20"
                                >
                                    {checking
                                        ? 'Checking…'
                                        : "I've paid — check now"}
                                </button>
                            </div>
                        )}

                        <div className="mt-8 grid gap-4 sm:grid-cols-3">
                            {(plans ?? []).map((plan) => (
                                <div
                                    key={plan.id}
                                    className="flex flex-col rounded-2xl bg-white p-5 text-left ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800"
                                >
                                    <div className="font-semibold text-gray-900 dark:text-gray-100">
                                        {plan.name}
                                    </div>
                                    <div className="mt-1 text-xl font-bold text-indigo-600 dark:text-indigo-400">
                                        TZS{' '}
                                        {formatCurrency(
                                            plan.price ?? plan.price_monthly,
                                        )}
                                    </div>
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {plan.duration_months} months of full
                                        access
                                    </p>

                                    {/*
                                        Goes to the checkout page rather
                                        than charging on click. Picking a
                                        plan and choosing how to pay are
                                        two decisions, and firing a real
                                        payment from a card button gave the
                                        customer no chance to make the
                                        second one.
                                    */}
                                    <Link
                                        href={route('subscription.checkout', {
                                            plan: plan.id,
                                        })}
                                        disabled={processing}
                                        className="mt-4 block w-full rounded-lg bg-indigo-600 px-4 py-2 text-center text-sm font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-60"
                                    >
                                        Renew for {plan.duration_months} months
                                    </Link>
                                </div>
                            ))}
                        </div>

                        <p className="mt-6 text-xs text-gray-500 dark:text-gray-500">
                            Renewing starts a new paid term from the day your
                            payment is confirmed. Nothing is deleted while your
                            account is unpaid.
                        </p>

                        <div className="mt-8">
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="text-sm font-medium text-gray-500 underline-offset-4 hover:text-gray-900 hover:underline dark:hover:text-gray-200"
                            >
                                Sign out
                            </Link>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
