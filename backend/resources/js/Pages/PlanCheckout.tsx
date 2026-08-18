import ApplicationLogo from '@/Components/ApplicationLogo';
import InputError from '@/Components/InputError';
import { formatCurrency } from '@/lib/currency';
import {
    ArrowLeftIcon,
    CreditCardIcon,
    DevicePhoneMobileIcon,
} from '@heroicons/react/24/outline';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

/**
 * Where the customer chooses how to pay.
 *
 * This page is BiasharaMax's rather than Snippe's because Snippe's hosted
 * checkout cannot offer a card: its Payment Sessions API accepts only
 * `allowed_methods: ["mobile_money"]`. Cards are reachable only through the
 * direct payments endpoint, so the choice has to be made here and
 * dispatched to the right call.
 *
 * The two methods behave differently on submit and the page says so before
 * the customer commits:
 *
 *  - **Mobile money** — a PIN prompt arrives on the phone. The browser
 *    stays put and waits, which is why the expired-plan page polls.
 *  - **Card** — the browser leaves for Snippe's secure card form and comes
 *    back afterwards.
 *
 * No card number is ever typed here. Taking one on this page would drag
 * BiasharaMax into PCI scope for no benefit, so the card option is a
 * redirect and nothing more.
 */
export default function PlanCheckout({
    plan,
    businessName,
    phone,
}: {
    plan: {
        id: string;
        name: string;
        price: string | null;
        duration_months: number | null;
    };
    businessName?: string | null;
    phone?: string | null;
}) {
    const { data, setData, post, processing, errors } = useForm({
        method: 'mobile' as 'mobile' | 'card',
        phone: phone ?? '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('subscription.renew', plan.id));
    };

    const isMobile = data.method === 'mobile';

    return (
        <>
            <Head title={`Pay for ${plan.name}`} />

            <div className="flex min-h-screen flex-col bg-gray-50 dark:bg-gray-950">
                <header className="px-6 py-6 sm:px-10">
                    <div className="flex items-center gap-3">
                        <ApplicationLogo className="h-9 w-9 fill-current text-indigo-600 dark:text-indigo-400" />
                        <span className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            BiasharaMax
                        </span>
                    </div>
                </header>

                <main className="flex flex-1 items-start justify-center px-6 pb-16">
                    <div className="w-full max-w-lg">
                        <Link
                            href={route('plan.expired')}
                            className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-900 dark:hover:text-gray-200"
                        >
                            <ArrowLeftIcon className="h-4 w-4" />
                            Choose a different plan
                        </Link>

                        <div className="mt-4 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-800">
                            <div className="border-b border-gray-100 px-6 py-5 dark:border-gray-800">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    {businessName}
                                </p>
                                <h1 className="mt-1 text-xl font-bold text-gray-900 dark:text-gray-100">
                                    {plan.name}
                                </h1>
                                <p className="mt-2 text-3xl font-bold text-indigo-600 dark:text-indigo-400">
                                    TZS {formatCurrency(plan.price ?? '0')}
                                </p>
                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {plan.duration_months} months of full access
                                </p>
                            </div>

                            <form onSubmit={submit} className="px-6 py-6">
                                <p className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    How would you like to pay?
                                </p>

                                <div className="mt-3 grid grid-cols-2 gap-3">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData('method', 'mobile')
                                        }
                                        className={`flex flex-col items-center gap-1.5 rounded-xl border p-4 transition ${
                                            isMobile
                                                ? 'border-indigo-500 bg-indigo-50/60 ring-1 ring-indigo-500 dark:bg-indigo-500/10'
                                                : 'border-gray-200 hover:border-gray-300 dark:border-gray-700'
                                        }`}
                                    >
                                        <DevicePhoneMobileIcon className="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            Mobile Money
                                        </span>
                                        <span className="text-center text-xs text-gray-500 dark:text-gray-400">
                                            M-Pesa, Airtel, Mixx, Halotel
                                        </span>
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData('method', 'card')
                                        }
                                        className={`flex flex-col items-center gap-1.5 rounded-xl border p-4 transition ${
                                            !isMobile
                                                ? 'border-indigo-500 bg-indigo-50/60 ring-1 ring-indigo-500 dark:bg-indigo-500/10'
                                                : 'border-gray-200 hover:border-gray-300 dark:border-gray-700'
                                        }`}
                                    >
                                        <CreditCardIcon className="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                                        <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            Pay with Card
                                        </span>
                                        <span className="text-center text-xs text-gray-500 dark:text-gray-400">
                                            Visa, Mastercard
                                        </span>
                                    </button>
                                </div>

                                {isMobile ? (
                                    <div className="mt-6">
                                        <label
                                            htmlFor="phone"
                                            className="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            Mobile money number
                                        </label>
                                        <input
                                            id="phone"
                                            type="tel"
                                            inputMode="tel"
                                            autoFocus
                                            value={data.phone}
                                            onChange={(e) =>
                                                setData('phone', e.target.value)
                                            }
                                            placeholder="07XX XXX XXX"
                                            className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                        />
                                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            A prompt will be sent to this
                                            number. Enter your PIN to approve
                                            it.
                                        </p>
                                        <InputError
                                            message={errors.phone}
                                            className="mt-2"
                                        />
                                    </div>
                                ) : (
                                    <div className="mt-6 rounded-xl bg-gray-50 px-4 py-4 dark:bg-gray-800/60">
                                        <p className="text-sm text-gray-700 dark:text-gray-300">
                                            You will be taken to Snippe's secure
                                            card form to enter your card
                                            details, then returned here.
                                        </p>
                                        {/*
                                            Said explicitly. A customer who
                                            expects to type a card number on
                                            this page and is suddenly on
                                            another domain has good reason to
                                            abandon the payment.
                                        */}
                                        <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            BiasharaMax never sees or stores
                                            your card number.
                                        </p>
                                    </div>
                                )}

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="mt-6 w-full rounded-lg bg-indigo-600 px-4 py-3 font-semibold text-white transition hover:bg-indigo-500 disabled:opacity-60"
                                >
                                    {processing
                                        ? 'Starting payment…'
                                        : isMobile
                                          ? `Send prompt for TZS ${formatCurrency(plan.price ?? '0')}`
                                          : `Continue to card payment`}
                                </button>

                                <p className="mt-3 text-center text-xs text-gray-500 dark:text-gray-500">
                                    Your plan activates as soon as the payment
                                    is confirmed.
                                </p>
                            </form>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
