import ApplicationLogo from '@/Components/ApplicationLogo';
import {
    ChatBubbleLeftRightIcon,
    EnvelopeIcon,
    LockClosedIcon,
    PhoneIcon,
} from '@heroicons/react/24/outline';
import { Head, Link } from '@inertiajs/react';

/**
 * Where a suspended business lands.
 *
 * Deliberately NOT inside AuthenticatedLayout. That layout builds the
 * sidebar, module list, currency switcher and notification poller for a
 * business that has just been locked out — every one of those reads data
 * this account is no longer entitled to, and one of them was throwing
 * before the page could render at all. A locked-out user should meet a
 * page that depends on nothing.
 *
 * It also says what it is. "Your subscription has ended" sends someone to
 * pay again; a suspension is not a billing state and paying will not lift
 * it. The only useful action here is talking to a person, so that is the
 * only action offered.
 */
export default function Suspended({
    businessName,
    supportEmail,
    supportPhone,
    whatsappUrl,
}: {
    businessName?: string | null;
    supportEmail: string;
    supportPhone: string;
    whatsappUrl: string;
}) {
    return (
        <>
            <Head title="Account suspended" />

            <div className="flex min-h-screen flex-col bg-gray-50 dark:bg-gray-950">
                <header className="px-6 py-6 sm:px-10">
                    <div className="flex items-center gap-3">
                        {/* `fill-current` plus a text colour — the mark
                            inherits its colour by design and renders as
                            an invisible shape without both. */}
                        <ApplicationLogo className="h-9 w-9 fill-current text-indigo-600 dark:text-indigo-400" />
                        <span className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            BiasharaMax
                        </span>
                    </div>
                </header>

                <main className="flex flex-1 items-center justify-center px-6 pb-16">
                    <div className="w-full max-w-xl text-center">
                        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-500/15">
                            <LockClosedIcon className="h-8 w-8 text-amber-600 dark:text-amber-400" />
                        </div>

                        <h1 className="mt-6 text-2xl font-bold text-gray-900 dark:text-gray-100 sm:text-3xl">
                            This account is temporarily suspended
                        </h1>

                        <p className="mt-4 text-base leading-relaxed text-gray-600 dark:text-gray-400">
                            {businessName ? (
                                <>
                                    Access to{' '}
                                    <span className="font-semibold text-gray-900 dark:text-gray-200">
                                        {businessName}
                                    </span>{' '}
                                    has been paused by BiasharaMax.
                                </>
                            ) : (
                                <>
                                    Access to this business has been paused by
                                    BiasharaMax.
                                </>
                            )}{' '}
                            {/* Stated plainly, because the alternative is
                                someone quietly assuming the worst. */}
                            Your data has not been deleted and nothing has been
                            lost — it will be exactly as you left it once the
                            account is reactivated.
                        </p>

                        <p className="mt-3 text-sm text-gray-500 dark:text-gray-500">
                            Renewing or changing your plan will not lift a
                            suspension. Please get in touch and we will sort it
                            out with you.
                        </p>

                        <div className="mt-8 grid gap-3 sm:grid-cols-3">
                            <a
                                href={whatsappUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="flex flex-col items-center gap-2 rounded-2xl bg-white px-4 py-5 ring-1 ring-gray-200 transition hover:shadow-md dark:bg-gray-900 dark:ring-gray-800"
                            >
                                <ChatBubbleLeftRightIcon className="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                                <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    WhatsApp
                                </span>
                                <span className="text-xs text-gray-500">
                                    Fastest reply
                                </span>
                            </a>

                            <a
                                href={`mailto:${supportEmail}`}
                                className="flex flex-col items-center gap-2 rounded-2xl bg-white px-4 py-5 ring-1 ring-gray-200 transition hover:shadow-md dark:bg-gray-900 dark:ring-gray-800"
                            >
                                <EnvelopeIcon className="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                                <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    Email
                                </span>
                                <span className="text-xs text-gray-500">
                                    {supportEmail}
                                </span>
                            </a>

                            <a
                                href={`tel:${supportPhone.replace(/\s/g, '')}`}
                                className="flex flex-col items-center gap-2 rounded-2xl bg-white px-4 py-5 ring-1 ring-gray-200 transition hover:shadow-md dark:bg-gray-900 dark:ring-gray-800"
                            >
                                <PhoneIcon className="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                                <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    Call
                                </span>
                                <span className="text-xs text-gray-500">
                                    {supportPhone}
                                </span>
                            </a>
                        </div>

                        <div className="mt-10">
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
