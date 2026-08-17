import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <div className="mb-8">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Forgot your password?
                </h1>
                <p className="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                    No problem. Tell us your email address and we&apos;ll send
                    you a link to choose a new one.
                </p>
            </div>

            {status && (
                <div className="mb-6 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1.5 block w-full"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <PrimaryButton
                    className="w-full justify-center py-2.5"
                    disabled={processing}
                >
                    Email password reset link
                </PrimaryButton>

                <p className="text-center text-sm text-gray-500 dark:text-gray-400">
                    <Link
                        href={route('login')}
                        className="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                    >
                        Back to log in
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
