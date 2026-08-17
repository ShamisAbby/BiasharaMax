import InputLabel from '@/Components/InputLabel';
import PasswordInput from '@/Components/PasswordInput';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useFormErrorToasts } from '@/hooks/useFormErrorToasts';
import GuestLayout from '@/Layouts/GuestLayout';
import { ShieldCheckIcon } from '@heroicons/react/24/outline';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function PlatformLogin({ status }: { status?: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
    });

    // Sign-in failures are about the attempt, not one field —
    // see useFormErrorToasts.
    useFormErrorToasts(errors);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('platform.login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout
            tone="platform"
            panelEyebrow="BiasharaMax Admin"
            panelTitle="Platform control, secured."
            panelDescription="Manage every business, subscription, and integration running on BiasharaMax from a single console."
            panelHighlights={[
                'Full visibility across all tenants',
                'Audit-logged, role-gated access',
                'Real-time platform health monitoring',
            ]}
        >
            <Head title="SuperAdmin Login" />

            <div className="mb-8 flex items-start gap-3">
                <span className="mt-0.5 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/40">
                    <ShieldCheckIcon className="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                </span>
                <div>
                    <h1 className="text-xl font-bold text-gray-900 dark:text-gray-100">
                        Platform SuperAdmin
                    </h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Restricted access for BiasharaMax staff only.
                    </p>
                </div>
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

                <PrimaryButton
                    className="w-full justify-center py-2.5"
                    disabled={processing}
                >
                    Log in
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
