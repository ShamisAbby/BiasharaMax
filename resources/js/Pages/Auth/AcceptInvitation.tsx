import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function AcceptInvitation({
    employeeName,
    businessName,
}: {
    employeeName: string;
    businessName: string | null;
}) {
    const { data, setData, post, processing, errors } = useForm({
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(window.location.pathname + window.location.search);
    };

    return (
        <GuestLayout>
            <Head title="Accept invitation" />

            <div className="mb-6 text-center">
                <h1 className="text-xl font-bold text-gray-900 dark:text-gray-100">
                    Welcome, {employeeName}
                </h1>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {businessName
                        ? `Set a password to join ${businessName}.`
                        : 'Set a password to activate your account.'}
                </p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <InputLabel htmlFor="password" value="Password" />
                    <TextInput
                        id="password"
                        type="password"
                        className="mt-1 block w-full"
                        value={data.password}
                        autoComplete="new-password"
                        isFocused
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="password_confirmation" value="Confirm password" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        className="mt-1 block w-full"
                        value={data.password_confirmation}
                        autoComplete="new-password"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        required
                    />
                    <InputError message={errors.password_confirmation} className="mt-2" />
                </div>

                <div className="flex justify-end">
                    <PrimaryButton disabled={processing}>Activate account</PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
