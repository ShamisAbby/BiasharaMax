import ApplicationLogo from '@/Components/ApplicationLogo';
import { CheckCircleIcon } from '@heroicons/react/24/solid';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

const TONE_GRADIENT = {
    tenant: 'from-indigo-600 via-indigo-700 to-violet-800',
    platform: 'from-slate-900 via-indigo-950 to-slate-900',
};

const MAX_WIDTH = {
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    '2xl': 'sm:max-w-2xl',
};

export default function GuestLayout({
    children,
    panelEyebrow = 'BiasharaMax',
    panelTitle = 'Run your business, beautifully.',
    panelDescription = 'One platform for inventory, sales, finance, and growth — built for businesses in Zanzibar.',
    panelHighlights = [
        'Real-time inventory across every branch',
        'Sales, payments, and reporting in one place',
        'Set up in minutes, no credit card required',
    ],
    tone = 'tenant',
    maxWidth = 'md',
}: PropsWithChildren<{
    panelEyebrow?: string;
    panelTitle?: string;
    panelDescription?: string;
    panelHighlights?: string[] | null;
    tone?: 'tenant' | 'platform';
    maxWidth?: 'md' | 'lg' | '2xl';
}>) {
    return (
        <div className="flex min-h-screen bg-gray-50 dark:bg-gray-900">
            <div
                className={`relative hidden w-[42%] shrink-0 flex-col justify-between overflow-hidden bg-gradient-to-br p-12 text-white lg:flex ${TONE_GRADIENT[tone]}`}
            >
                <div className="pointer-events-none absolute -left-24 -top-24 h-80 w-80 rounded-full bg-white/10 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-32 -right-16 h-96 w-96 rounded-full bg-white/10 blur-3xl" />

                <Link
                    href="/"
                    className="relative z-10 flex items-center gap-2.5"
                >
                    <ApplicationLogo className="h-9 w-9 fill-current text-white" />
                    <span className="text-lg font-bold">{panelEyebrow}</span>
                </Link>

                <div className="relative z-10">
                    <h1 className="text-3xl font-bold leading-tight tracking-tight">
                        {panelTitle}
                    </h1>
                    <p className="mt-4 max-w-sm text-sm leading-relaxed text-white/80">
                        {panelDescription}
                    </p>

                    {panelHighlights && panelHighlights.length > 0 && (
                        <ul className="mt-8 space-y-3">
                            {panelHighlights.map((highlight) => (
                                <li
                                    key={highlight}
                                    className="flex items-start gap-2.5 text-sm text-white/90"
                                >
                                    <CheckCircleIcon className="mt-0.5 h-5 w-5 shrink-0 text-white/70" />
                                    <span>{highlight}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                <p className="relative z-10 text-xs text-white/50">
                    &copy; {new Date().getFullYear()} BiasharaMax. All rights
                    reserved.
                </p>
            </div>

            <div className="flex flex-1 flex-col items-center justify-center px-4 py-10 sm:px-6 lg:px-12">
                <Link
                    href="/"
                    className="mb-8 flex items-center gap-2 lg:hidden"
                >
                    <ApplicationLogo className="h-9 w-9 fill-current text-indigo-600" />
                    <span className="text-lg font-bold text-gray-900 dark:text-gray-100">
                        BiasharaMax
                    </span>
                </Link>

                <div
                    className={`w-full overflow-hidden rounded-2xl border border-gray-100 bg-white px-6 py-8 shadow-xl shadow-gray-200/60 dark:border-gray-700 dark:bg-gray-800 dark:shadow-none sm:px-10 sm:py-10 ${MAX_WIDTH[maxWidth]}`}
                >
                    {children}
                </div>
            </div>
        </div>
    );
}
