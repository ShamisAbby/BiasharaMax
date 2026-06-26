import ApplicationLogo from '@/Components/ApplicationLogo';
import PrimaryButton from '@/Components/PrimaryButton';
import SectionHeading from '@/Components/SectionHeading';
import { formatCurrency } from '@/lib/currency';
import { PageProps, SubscriptionPlan } from '@/types';
import {
    BanknotesIcon,
    Bars3Icon,
    BellAlertIcon,
    BuildingStorefrontIcon,
    CalculatorIcon,
    CheckIcon,
    CubeIcon,
    GlobeAltIcon,
    ShieldCheckIcon,
    ShoppingCartIcon,
    SignalSlashIcon,
    TruckIcon,
    UserGroupIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

const NAV_LINKS = [
    { href: '#features', label: 'Features' },
    { href: '#businesses', label: 'Who it’s for' },
    { href: '#pricing', label: 'Pricing' },
];

const FEATURES = [
    {
        icon: CubeIcon,
        title: 'Inventory',
        description:
            'Track stock across branches and warehouses, batches, expiry dates and barcodes in real time.',
    },
    {
        icon: ShoppingCartIcon,
        title: 'POS & Sales',
        description:
            'Fast, touch-friendly point of sale with multiple payment methods, discounts and receipts.',
    },
    {
        icon: TruckIcon,
        title: 'Purchasing',
        description:
            'Manage suppliers and purchase orders, and keep stock levels accurate as deliveries arrive.',
    },
    {
        icon: UserGroupIcon,
        title: 'CRM & Debt',
        description:
            'Customer profiles, loyalty, and debt tracking so nothing owed to your business gets forgotten.',
    },
    {
        icon: CalculatorIcon,
        title: 'Accounting',
        description:
            'Expenses, income, and cashbook in one place — know your numbers without a spreadsheet.',
    },
    {
        icon: GlobeAltIcon,
        title: 'Your own website',
        description:
            'Every business gets a professional website, edited from the dashboard. No coding required.',
    },
    {
        icon: BellAlertIcon,
        title: 'Notifications',
        description:
            'SMS, email, WhatsApp and push notifications keep you and your customers in the loop.',
    },
    {
        icon: SignalSlashIcon,
        title: 'Works offline',
        description:
            'Keep selling and tracking stock even when the internet drops. Syncs automatically when it’s back.',
    },
    {
        icon: ShieldCheckIcon,
        title: 'Roles & permissions',
        description:
            'Give every employee exactly the access they need — owner, manager, cashier, and more.',
    },
];

const BUSINESS_TYPES = [
    'Retail Shops',
    'Supermarkets',
    'Restaurants',
    'Pharmacies',
    'Hardware Stores',
    'Electronics Shops',
    'Fashion Stores',
    'Beauty Salons',
    'Wholesale',
    'Service Businesses',
];

const HOW_IT_WORKS = [
    {
        step: '1',
        title: 'Create your business',
        description:
            'Register in minutes and start a 30-day free trial — no credit card required.',
    },
    {
        step: '2',
        title: 'Set up your team',
        description:
            'Add branches, invite employees and assign roles with exactly the access they need.',
    },
    {
        step: '3',
        title: 'Run your business',
        description:
            'Sell, track stock, manage customers and get paid — online or offline.',
    },
];

export default function Welcome({
    auth,
    canLogin,
    canRegister,
    plans,
}: PageProps<{
    canLogin: boolean;
    canRegister: boolean;
    plans: SubscriptionPlan[];
}>) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    return (
        <>
            <Head title="BiasharaOS — The Business Operating System" />

            <div className="bg-white dark:bg-gray-900">
                {/* Header */}
                <header className="sticky top-0 z-40 border-b border-gray-200 bg-white/90 backdrop-blur dark:border-gray-800 dark:bg-gray-900/90">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                        <Link href="/" className="flex items-center gap-2">
                            <ApplicationLogo className="h-8 w-auto fill-current text-indigo-600" />
                            <span className="text-lg font-bold text-gray-900 dark:text-gray-100">
                                BiasharaOS
                            </span>
                        </Link>

                        <nav className="hidden items-center gap-8 md:flex">
                            {NAV_LINKS.map((link) => (
                                <a
                                    key={link.href}
                                    href={link.href}
                                    className="text-sm font-medium text-gray-600 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400"
                                >
                                    {link.label}
                                </a>
                            ))}
                        </nav>

                        <div className="hidden items-center gap-4 md:flex">
                            {auth.user ? (
                                <Link
                                    href={route('dashboard')}
                                    className="text-sm font-medium text-gray-700 hover:text-indigo-600 dark:text-gray-300"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    {canLogin && (
                                        <Link
                                            href={route('login')}
                                            className="text-sm font-medium text-gray-700 hover:text-indigo-600 dark:text-gray-300"
                                        >
                                            Log in
                                        </Link>
                                    )}
                                    {canRegister && (
                                        <Link href={route('register')}>
                                            <PrimaryButton>
                                                Start free trial
                                            </PrimaryButton>
                                        </Link>
                                    )}
                                </>
                            )}
                        </div>

                        <button
                            type="button"
                            className="-m-2.5 p-2.5 text-gray-700 md:hidden dark:text-gray-300"
                            onClick={() => setMobileMenuOpen(true)}
                        >
                            <Bars3Icon className="h-6 w-6" />
                        </button>
                    </div>

                    {mobileMenuOpen && (
                        <div className="fixed inset-0 z-50 bg-white px-6 py-4 md:hidden dark:bg-gray-900">
                            <div className="flex items-center justify-between">
                                <Link
                                    href="/"
                                    className="flex items-center gap-2"
                                >
                                    <ApplicationLogo className="h-8 w-auto fill-current text-indigo-600" />
                                    <span className="text-lg font-bold text-gray-900 dark:text-gray-100">
                                        BiasharaOS
                                    </span>
                                </Link>
                                <button
                                    type="button"
                                    className="-m-2.5 p-2.5 text-gray-700 dark:text-gray-300"
                                    onClick={() => setMobileMenuOpen(false)}
                                >
                                    <XMarkIcon className="h-6 w-6" />
                                </button>
                            </div>
                            <div className="mt-8 space-y-4">
                                {NAV_LINKS.map((link) => (
                                    <a
                                        key={link.href}
                                        href={link.href}
                                        onClick={() => setMobileMenuOpen(false)}
                                        className="block text-base font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {link.label}
                                    </a>
                                ))}
                                <div className="border-t border-gray-200 pt-4 dark:border-gray-700">
                                    {canLogin && (
                                        <Link
                                            href={route('login')}
                                            className="block py-2 text-base font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            Log in
                                        </Link>
                                    )}
                                    {canRegister && (
                                        <Link
                                            href={route('register')}
                                            className="mt-2 block"
                                        >
                                            <PrimaryButton className="w-full justify-center">
                                                Start free trial
                                            </PrimaryButton>
                                        </Link>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </header>

                {/* Hero */}
                <section className="relative overflow-hidden">
                    <div className="mx-auto max-w-7xl px-6 py-20 sm:py-28">
                        <div className="mx-auto max-w-3xl text-center">
                            <p className="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-sm font-medium text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                30 days free &middot; No credit card required
                            </p>
                            <h1 className="mt-6 text-4xl font-bold tracking-tight text-gray-900 sm:text-6xl dark:text-gray-100">
                                The complete Business Operating System
                            </h1>
                            <p className="mt-6 text-lg leading-8 text-gray-600 dark:text-gray-400">
                                Inventory, POS, purchasing, CRM, accounting and
                                your own business website &mdash; all in one
                                platform. Built for retail, restaurants,
                                pharmacies and service businesses. Works online
                                and offline.
                            </p>
                            <div className="mt-10 flex items-center justify-center gap-4">
                                {canRegister && (
                                    <Link href={route('register')}>
                                        <PrimaryButton className="px-6 py-3 text-sm">
                                            Start your free trial
                                        </PrimaryButton>
                                    </Link>
                                )}
                                <a
                                    href="#pricing"
                                    className="text-sm font-semibold text-gray-700 hover:text-indigo-600 dark:text-gray-300"
                                >
                                    See pricing &rarr;
                                </a>
                            </div>
                        </div>

                        {/* Stylized product preview */}
                        <div className="mx-auto mt-16 max-w-4xl">
                            <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800">
                                <div className="flex items-center gap-2 border-b border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900">
                                    <span className="h-3 w-3 rounded-full bg-red-400" />
                                    <span className="h-3 w-3 rounded-full bg-yellow-400" />
                                    <span className="h-3 w-3 rounded-full bg-green-400" />
                                    <span className="ms-3 text-xs text-gray-400">
                                        app.biasharaos.com/dashboard
                                    </span>
                                </div>
                                <div className="grid gap-4 p-6 sm:grid-cols-4">
                                    {[
                                        {
                                            label: "Today's Sales",
                                            value: 'TZS 1,940,200',
                                            color: 'text-emerald-600',
                                        },
                                        {
                                            label: 'Outstanding Debts',
                                            value: 'TZS 286,000',
                                            color: 'text-orange-600',
                                        },
                                        {
                                            label: 'Inventory Value',
                                            value: 'TZS 28.5M',
                                            color: 'text-indigo-600',
                                        },
                                        {
                                            label: 'Low Stock Items',
                                            value: '7',
                                            color: 'text-red-600',
                                        },
                                    ].map((stat) => (
                                        <div
                                            key={stat.label}
                                            className="rounded-lg border border-gray-100 p-4 dark:border-gray-700"
                                        >
                                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                                {stat.label}
                                            </p>
                                            <p
                                                className={`mt-2 text-xl font-bold ${stat.color}`}
                                            >
                                                {stat.value}
                                            </p>
                                        </div>
                                    ))}
                                    <div className="col-span-full h-32 rounded-lg bg-gradient-to-br from-indigo-50 to-white dark:from-indigo-950 dark:to-gray-800" />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Features */}
                <section
                    id="features"
                    className="bg-gray-50 py-20 sm:py-28 dark:bg-gray-950/40"
                >
                    <div className="mx-auto max-w-7xl px-6">
                        <SectionHeading
                            eyebrow="Everything in one place"
                            title="Run your entire business from one platform"
                            description="Stop juggling separate apps and notebooks. BiasharaOS brings every part of your business operations together."
                        />

                        <div className="mx-auto mt-16 grid max-w-5xl grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                            {FEATURES.map((feature) => (
                                <div key={feature.title} className="flex gap-4">
                                    <div className="flex h-11 w-11 flex-none items-center justify-center rounded-lg bg-indigo-600">
                                        <feature.icon
                                            className="h-6 w-6 text-white"
                                            aria-hidden="true"
                                        />
                                    </div>
                                    <div>
                                        <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100">
                                            {feature.title}
                                        </h3>
                                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                            {feature.description}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Business types */}
                <section id="businesses" className="py-20 sm:py-28">
                    <div className="mx-auto max-w-7xl px-6">
                        <SectionHeading
                            eyebrow="Built for businesses like yours"
                            title="One platform, every kind of business"
                        />

                        <div className="mx-auto mt-12 flex max-w-4xl flex-wrap justify-center gap-3">
                            {BUSINESS_TYPES.map((type) => (
                                <span
                                    key={type}
                                    className="inline-flex items-center gap-2 rounded-full border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                                >
                                    <BuildingStorefrontIcon className="h-4 w-4 text-indigo-600" />
                                    {type}
                                </span>
                            ))}
                        </div>
                    </div>
                </section>

                {/* How it works */}
                <section className="bg-gray-50 py-20 sm:py-28 dark:bg-gray-950/40">
                    <div className="mx-auto max-w-7xl px-6">
                        <SectionHeading
                            eyebrow="Getting started"
                            title="Up and running in minutes"
                        />

                        <div className="mx-auto mt-16 grid max-w-4xl gap-12 sm:grid-cols-3">
                            {HOW_IT_WORKS.map((item) => (
                                <div key={item.step} className="text-center">
                                    <div className="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white">
                                        {item.step}
                                    </div>
                                    <h3 className="mt-4 text-base font-semibold text-gray-900 dark:text-gray-100">
                                        {item.title}
                                    </h3>
                                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                        {item.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Pricing */}
                <section id="pricing" className="py-20 sm:py-28">
                    <div className="mx-auto max-w-7xl px-6">
                        <SectionHeading
                            eyebrow="Simple pricing"
                            title="Plans that grow with your business"
                            description="Every plan starts with a 30-day free trial. No setup fees, cancel anytime."
                        />

                        <div className="mx-auto mt-16 grid max-w-5xl gap-8 sm:grid-cols-3">
                            {plans.map((plan, index) => (
                                <div
                                    key={plan.id}
                                    className={`rounded-2xl border p-8 ${
                                        index === 1
                                            ? 'border-indigo-600 ring-2 ring-indigo-600'
                                            : 'border-gray-200 dark:border-gray-700'
                                    }`}
                                >
                                    {index === 1 && (
                                        <p className="mb-4 inline-flex items-center rounded-full bg-indigo-600 px-3 py-1 text-xs font-semibold text-white">
                                            Most popular
                                        </p>
                                    )}
                                    <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        {plan.name}
                                    </h3>
                                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        {plan.description}
                                    </p>
                                    <p className="mt-6">
                                        <span className="text-4xl font-bold text-gray-900 dark:text-gray-100">
                                            TZS{' '}
                                            {formatCurrency(plan.price_monthly)}
                                        </span>
                                        <span className="text-sm text-gray-500 dark:text-gray-400">
                                            /month
                                        </span>
                                    </p>
                                    <ul className="mt-6 space-y-3">
                                        {plan.features.map((feature) => (
                                            <li
                                                key={feature}
                                                className="flex gap-2 text-sm text-gray-600 dark:text-gray-400"
                                            >
                                                <CheckIcon className="h-5 w-5 flex-none text-indigo-600" />
                                                {feature}
                                            </li>
                                        ))}
                                    </ul>
                                    {canRegister && (
                                        <Link
                                            href={route('register')}
                                            className="mt-8 block"
                                        >
                                            <PrimaryButton className="w-full justify-center">
                                                Start free trial
                                            </PrimaryButton>
                                        </Link>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Final CTA */}
                <section className="bg-indigo-600">
                    <div className="mx-auto max-w-4xl px-6 py-16 text-center sm:py-20">
                        <h2 className="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                            Ready to digitize your business?
                        </h2>
                        <p className="mt-4 text-lg text-indigo-100">
                            Join businesses running on BiasharaOS. Start your
                            free trial today.
                        </p>
                        {canRegister && (
                            <div className="mt-8">
                                <Link href={route('register')}>
                                    <button className="inline-flex items-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-indigo-600 shadow-sm hover:bg-indigo-50">
                                        Start your free trial
                                    </button>
                                </Link>
                            </div>
                        )}
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-gray-200 dark:border-gray-800">
                    <div className="mx-auto max-w-7xl px-6 py-12">
                        <div className="flex flex-col items-center justify-between gap-6 sm:flex-row">
                            <div className="flex items-center gap-2">
                                <ApplicationLogo className="h-6 w-auto fill-current text-indigo-600" />
                                <span className="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                    BiasharaOS
                                </span>
                            </div>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                &copy; {new Date().getFullYear()} BiasharaOS.
                                All rights reserved.
                            </p>
                            <div className="flex gap-2 text-xs text-gray-400">
                                <BanknotesIcon className="h-4 w-4" />
                                <span>
                                    Built for African businesses, scalable
                                    globally.
                                </span>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
