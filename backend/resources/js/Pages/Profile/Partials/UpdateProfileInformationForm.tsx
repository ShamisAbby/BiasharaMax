import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { PageProps } from '@/types';
import { Transition } from '@headlessui/react';
import { CameraIcon } from '@heroicons/react/24/outline';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}: {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}) {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            username: user.username ?? '',
            email: user.email,
            // The backend has accepted `phone` since the 2026_08_06
            // migration and `ProfileUpdateRequest` validates it — the form
            // simply never rendered the field. So the one detail a vendor
            // gives at signup and most often needs to correct was the one
            // they could not reach, and mobile-money checkout reads exactly
            // this column.
            phone: user.phone ?? '',
        });

    const avatarForm = useForm<{ avatar: File | null }>({ avatar: null });
    const fileInputRef = useRef<HTMLInputElement>(null);

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    const onFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        avatarForm.setData('avatar', file);
        avatarForm.post(route('profile.avatar'), {
            forceFormData: true,
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Profile Information
                </h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Update your account's profile information and email address.
                </p>
            </header>

            {/* Avatar */}
            <div className="mt-6 flex items-center gap-5">
                <div className="relative shrink-0">
                    {user.avatar_url ? (
                        <img
                            src={user.avatar_url}
                            alt={user.name}
                            className="h-20 w-20 rounded-full object-cover ring-2 ring-indigo-100 dark:ring-indigo-900/40"
                        />
                    ) : (
                        <span className="flex h-20 w-20 items-center justify-center rounded-full bg-indigo-100 text-2xl font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">
                            {user.name.charAt(0).toUpperCase()}
                        </span>
                    )}
                    <button
                        type="button"
                        onClick={() => fileInputRef.current?.click()}
                        className="absolute bottom-0 right-0 flex h-7 w-7 items-center justify-center rounded-full bg-white shadow ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:ring-gray-700"
                        title="Change photo"
                    >
                        <CameraIcon className="h-3.5 w-3.5 text-gray-600 dark:text-gray-300" />
                    </button>
                </div>
                <div>
                    <button
                        type="button"
                        onClick={() => fileInputRef.current?.click()}
                        disabled={avatarForm.processing}
                        className="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        <CameraIcon className="h-4 w-4" />
                        {avatarForm.processing ? 'Uploading…' : 'Change photo'}
                    </button>
                    <p className="mt-1 text-xs text-gray-400">
                        JPG, PNG or WebP — max 2 MB
                    </p>
                    {avatarForm.errors.avatar && (
                        <p className="mt-1 text-xs text-red-600">
                            {avatarForm.errors.avatar}
                        </p>
                    )}
                </div>
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    className="hidden"
                    onChange={onFileChange}
                />
            </div>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="name" value="Name" />
                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />
                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="username" value="Username" />
                    <TextInput
                        id="username"
                        className="mt-1 block w-full"
                        value={data.username}
                        onChange={(e) => setData('username', e.target.value)}
                        maxLength={15}
                        autoComplete="username"
                    />
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Up to 15 characters. Letters, numbers and underscores
                        only. You can sign in with this instead of your email.
                    </p>
                    <InputError className="mt-2" message={errors.username} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="email"
                    />
                    <InputError className="mt-2" message={errors.email} />
                </div>

                <div>
                    <InputLabel htmlFor="phone" value="Phone number" />

                    <TextInput
                        id="phone"
                        type="tel"
                        inputMode="tel"
                        className="mt-1 block w-full"
                        value={data.phone}
                        onChange={(e) => setData('phone', e.target.value)}
                        autoComplete="tel"
                        placeholder="07XX XXX XXX"
                    />

                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Used for mobile money payments and account recovery.
                        Tanzanian numbers in 07… or +255… form.
                    </p>

                    <InputError className="mt-2" message={errors.phone} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-gray-800 dark:text-gray-200">
                            Your email address is unverified.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="hover:text-gray:900 rounded-md text-sm text-gray-600 underline focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                            >
                                Click here to re-send the verification email.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-green-600 dark:text-green-400">
                                A new verification link has been sent to your
                                email address.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Save</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            Saved.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
