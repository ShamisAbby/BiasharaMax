import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PasswordInput from '@/Components/PasswordInput';
import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ConfirmPassword() {
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Confirm Password" />

            <div className="mb-8">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Confirm your password
                </h1>
                <p className="mt-1.5 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                    This is a secure area. Please confirm your password before
                    continuing.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-5">
                <div>
                    <InputLabel htmlFor="password" value="Password" />

                    <PasswordInput
                        id="password"
                        name="password"
                        value={data.password}
                        className="mt-1.5 block w-full"
                        isFocused={true}
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <PrimaryButton
                    className="w-full justify-center py-2.5"
                    disabled={processing}
                >
                    Confirm
                </PrimaryButton>
            </form>
        </GuestLayout>
    );
}
