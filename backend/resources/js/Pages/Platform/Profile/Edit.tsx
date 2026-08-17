import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import TextInput from '@/Components/TextInput';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { PageProps } from '@/types';
import { CameraIcon } from '@heroicons/react/24/outline';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useRef } from 'react';

export default function PlatformProfileEdit() {
    const { notify } = useBiNotification();
    const { platformAuth } = usePage<PageProps>().props;
    const user = platformAuth.user!;

    const profileForm = useForm({ name: user.name, email: user.email });
    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const avatarForm = useForm<{ avatar: File | null }>({ avatar: null });

    const fileInputRef = useRef<HTMLInputElement>(null);

    const submitProfile = (e: FormEvent) => {
        e.preventDefault();
        profileForm.patch(route('platform.profile.update'), {
            onSuccess: () => notify('Profile updated.', 'success'),
        });
    };

    const submitPassword = (e: FormEvent) => {
        e.preventDefault();
        passwordForm.put(route('platform.profile.password.update'), {
            onSuccess: () => {
                passwordForm.reset();
                notify('Password updated.', 'success');
            },
        });
    };

    const onFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        avatarForm.setData('avatar', file);
        avatarForm.post(route('platform.profile.avatar'), {
            forceFormData: true,
            onSuccess: () => notify('Profile photo updated.', 'success'),
            onError: () =>
                notify('Upload failed. Max 2 MB, JPG/PNG/WebP only.', 'error'),
        });
    };

    return (
        <PlatformLayout>
            <Head title="My Profile" />

            <div className="mx-auto max-w-2xl space-y-6">
                {/* Avatar */}
                <BiCard title="Profile photo">
                    <div className="flex items-center gap-6">
                        <div className="relative shrink-0">
                            {user.avatar_url ? (
                                <img
                                    src={user.avatar_url}
                                    alt={user.name}
                                    className="h-24 w-24 rounded-full object-cover ring-2 ring-indigo-100 dark:ring-indigo-900/40"
                                />
                            ) : (
                                <span className="flex h-24 w-24 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-violet-600 text-3xl font-semibold text-white">
                                    {user.name.charAt(0).toUpperCase()}
                                </span>
                            )}
                            <button
                                type="button"
                                onClick={() => fileInputRef.current?.click()}
                                className="absolute bottom-0 right-0 flex h-8 w-8 items-center justify-center rounded-full bg-white shadow-md ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-gray-800 dark:ring-gray-700"
                                title="Change photo"
                            >
                                <CameraIcon className="h-4 w-4 text-gray-600 dark:text-gray-300" />
                            </button>
                        </div>
                        <div>
                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {user.name}
                            </p>
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {user.email}
                            </p>
                            <button
                                type="button"
                                onClick={() => fileInputRef.current?.click()}
                                disabled={avatarForm.processing}
                                className="mt-3 inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                <CameraIcon className="h-4 w-4" />
                                {avatarForm.processing
                                    ? 'Uploading…'
                                    : 'Change photo'}
                            </button>
                            <p className="mt-1 text-xs text-gray-400">
                                JPG, PNG or WebP — max 2 MB
                            </p>
                        </div>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            className="hidden"
                            onChange={onFileChange}
                        />
                    </div>
                    {avatarForm.errors.avatar && (
                        <p className="mt-2 text-sm text-red-600">
                            {avatarForm.errors.avatar}
                        </p>
                    )}
                </BiCard>

                {/* Profile info */}
                <BiCard title="Profile information">
                    <form onSubmit={submitProfile} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Name
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={profileForm.data.name}
                                onChange={(e) =>
                                    profileForm.setData('name', e.target.value)
                                }
                            />
                            {profileForm.errors.name && (
                                <p className="mt-1 text-sm text-red-600">
                                    {profileForm.errors.name}
                                </p>
                            )}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Email
                            </label>
                            <TextInput
                                type="email"
                                className="mt-1 block w-full"
                                value={profileForm.data.email}
                                onChange={(e) =>
                                    profileForm.setData('email', e.target.value)
                                }
                            />
                            {profileForm.errors.email && (
                                <p className="mt-1 text-sm text-red-600">
                                    {profileForm.errors.email}
                                </p>
                            )}
                        </div>
                        <div className="flex justify-end">
                            <BiButton
                                type="submit"
                                disabled={profileForm.processing}
                            >
                                Save
                            </BiButton>
                        </div>
                    </form>
                </BiCard>

                {/* Change password */}
                <BiCard title="Change password">
                    <form onSubmit={submitPassword} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Current password
                            </label>
                            <TextInput
                                type="password"
                                className="mt-1 block w-full"
                                value={passwordForm.data.current_password}
                                onChange={(e) =>
                                    passwordForm.setData(
                                        'current_password',
                                        e.target.value,
                                    )
                                }
                            />
                            {passwordForm.errors.current_password && (
                                <p className="mt-1 text-sm text-red-600">
                                    {passwordForm.errors.current_password}
                                </p>
                            )}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                New password
                            </label>
                            <TextInput
                                type="password"
                                className="mt-1 block w-full"
                                value={passwordForm.data.password}
                                onChange={(e) =>
                                    passwordForm.setData(
                                        'password',
                                        e.target.value,
                                    )
                                }
                            />
                            {passwordForm.errors.password && (
                                <p className="mt-1 text-sm text-red-600">
                                    {passwordForm.errors.password}
                                </p>
                            )}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Confirm new password
                            </label>
                            <TextInput
                                type="password"
                                className="mt-1 block w-full"
                                value={passwordForm.data.password_confirmation}
                                onChange={(e) =>
                                    passwordForm.setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="flex justify-end">
                            <BiButton
                                type="submit"
                                disabled={passwordForm.processing}
                            >
                                Update password
                            </BiButton>
                        </div>
                    </form>
                </BiCard>
            </div>
        </PlatformLayout>
    );
}
