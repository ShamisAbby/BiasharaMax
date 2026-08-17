import Checkbox from '@/Components/Checkbox';
import InputLabel from '@/Components/InputLabel';
import PasswordInput from '@/Components/PasswordInput';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useFormErrorToasts } from '@/hooks/useFormErrorToasts';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    // Sign-in failures are about the attempt, not one field —
    // see useFormErrorToasts.
    useFormErrorToasts(errors);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <div className="mb-8">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Welcome back
                </h1>
                <p className="mt-1.5 text-sm text-gray-500 dark:text-gray-400">
                    Log in to continue to your dashboard.
                </p>
            </div>

            {status && (
                <div className="mb-6 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel htmlFor="email" value="Email or username" />

                    {/*
                      type="text", not "email": the field now accepts a
                      username too, and the browser's built-in email
                      validation would reject one before the form is ever
                      submitted. The field name stays `email` to match the
                      server-side request.
                    */}
                    <TextInput
                        id="email"
                        type="text"
                        name="email"
                        value={data.email}
                        className="mt-1.5 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                </div>

                <div>
                    <InputLabel htmlFor="password" value="Password" />

                    <PasswordInput
                        id="password"
                        name="password"
                        value={data.password}
                        className="mt-1.5 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                </div>

                <div className="flex items-center justify-between">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData(
                                    'remember',
                                    (e.target.checked || false) as false,
                                )
                            }
                        />
                        <span className="ms-2 text-sm text-gray-600 dark:text-gray-400">
                            Remember me
                        </span>
                    </label>

                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="rounded-md text-sm font-medium text-indigo-600 hover:text-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-indigo-400 dark:hover:text-indigo-300 dark:focus:ring-offset-gray-800"
                        >
                            Forgot password?
                        </Link>
                    )}
                </div>

                <PrimaryButton
                    className="w-full justify-center py-2.5"
                    disabled={processing}
                >
                    Log in
                </PrimaryButton>

                <p className="text-center text-sm text-gray-500 dark:text-gray-400">
                    Don&apos;t have an account?{' '}
                    <Link
                        href={route('register')}
                        className="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                    >
                        Start your free trial
                    </Link>
                </p>
            </form>
        </GuestLayout>
    );
}
