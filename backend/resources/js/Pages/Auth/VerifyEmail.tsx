import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { EnvelopeIcon } from '@heroicons/react/24/outline';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Email Verification" />

            <div className="flex justify-center">
                <span className="inline-flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/40">
                    <EnvelopeIcon className="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                </span>
            </div>

            <div className="mt-5 text-center">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Verify your email
                </h1>
                <p className="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                    Thanks for signing up! Before getting started, could you
                    verify your email address by clicking the link we just
                    emailed you? If you didn&apos;t receive it, we&apos;ll
                    gladly send another.
                </p>
            </div>

            {status === 'verification-link-sent' && (
                <div className="mt-6 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                    A new verification link has been sent to the email address
                    you provided during registration.
                </div>
            )}

            <form onSubmit={submit} className="mt-8 space-y-3">
                <PrimaryButton
                    className="w-full justify-center py-2.5"
                    disabled={processing}
                >
                    Resend verification email
                </PrimaryButton>

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="block w-full rounded-md py-2 text-center text-sm text-gray-500 underline hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-200 dark:focus:ring-offset-gray-800"
                >
                    Log out
                </Link>
            </form>
        </GuestLayout>
    );
}
