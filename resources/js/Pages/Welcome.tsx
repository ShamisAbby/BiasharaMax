import ApplicationLogo from '@/Components/ApplicationLogo';
import PrimaryButton from '@/Components/PrimaryButton';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

export default function Welcome({
    auth,
    canLogin,
    canRegister,
}: PageProps<{ canLogin: boolean; canRegister: boolean }>) {
    return (
        <>
            <Head title="BiasharaOS — Run your business from one platform" />

            <div className="flex min-h-screen flex-col bg-gray-50 dark:bg-gray-900">
                <header className="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">
                        <div className="flex items-center gap-2">
                            <ApplicationLogo className="h-8 w-auto fill-current text-indigo-600" />
                            <span className="text-lg font-bold text-gray-900 dark:text-gray-100">
                                BiasharaOS
                            </span>
                        </div>

                        <nav className="flex items-center gap-4">
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
                        </nav>
                    </div>
                </header>

                <main className="flex flex-1 items-center">
                    <div className="mx-auto max-w-3xl px-6 py-24 text-center">
                        <h1 className="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl dark:text-gray-100">
                            The complete Business Operating System
                        </h1>
                        <p className="mt-6 text-lg text-gray-600 dark:text-gray-400">
                            Inventory, POS, purchasing, CRM, accounting and your
                            own business website &mdash; all in one platform.
                            Works online and offline. 30 days free, no card
                            required.
                        </p>
                        <div className="mt-10">
                            {canRegister && (
                                <Link href={route('register')}>
                                    <PrimaryButton className="px-6 py-3 text-sm">
                                        Start your free trial
                                    </PrimaryButton>
                                </Link>
                            )}
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
