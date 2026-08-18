import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import {
    BuildingStorefrontIcon,
    ExclamationTriangleIcon,
    KeyIcon,
    UserCircleIcon,
} from '@heroicons/react/24/outline';
import { Head, Link, usePage } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

/**
 * The vendor's own account settings.
 *
 * Two changes worth explaining.
 *
 * **Everything from signup is now reachable.** Registration collects an
 * owner name, email and phone, plus the business name, type, phone,
 * country and currency. The account details lived here, the business
 * details lived under Settings → Business, and nothing connected the two —
 * so the phone number a vendor gave at signup had no visible home at all.
 * That is the field mobile-money checkout reads, which is how a payment
 * came to fail with "unsupported mobile carrier" against a placeholder
 * number nobody could edit.
 *
 * **Sections are labelled and separated.** The previous layout stacked
 * three identical white cards with no icons, no grouping and a destructive
 * "Delete account" sitting flush against the password form. Danger reads
 * as routine when it is styled like everything above it.
 */
export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    const { auth } = usePage<PageProps>().props;
    const business = auth.business;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    My account
                </h2>
            }
        >
            <Head title="My account" />

            <div className="py-10">
                <div className="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
                    {/*
                        Business details are not duplicated here. Two forms
                        writing one row is how they drift apart, and this
                        codebase has spent the week on exactly that class of
                        bug. A signpost instead.
                    */}
                    {business && (
                        <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-indigo-50 px-6 py-5 ring-1 ring-indigo-100 dark:bg-indigo-500/10 dark:ring-indigo-500/20">
                            <div className="flex items-start gap-3">
                                <BuildingStorefrontIcon className="mt-0.5 h-6 w-6 shrink-0 text-indigo-600 dark:text-indigo-400" />
                                <div>
                                    <p className="font-semibold text-gray-900 dark:text-gray-100">
                                        {business.name}
                                    </p>
                                    <p className="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                                        Business name, type, phone, address,
                                        country and currency are managed
                                        separately.
                                    </p>
                                </div>
                            </div>

                            <Link
                                href={route('settings.business.edit')}
                                className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500"
                            >
                                Business settings
                            </Link>
                        </div>
                    )}

                    <section className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <div className="flex items-center gap-3 border-b border-gray-100 px-6 py-4 dark:border-gray-700/60">
                            <UserCircleIcon className="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                Your details
                            </h3>
                        </div>

                        <div className="px-6 py-6">
                            <UpdateProfileInformationForm
                                mustVerifyEmail={mustVerifyEmail}
                                status={status}
                                className="max-w-xl"
                            />
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                        <div className="flex items-center gap-3 border-b border-gray-100 px-6 py-4 dark:border-gray-700/60">
                            <KeyIcon className="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">
                                Password
                            </h3>
                        </div>

                        <div className="px-6 py-6">
                            <UpdatePasswordForm className="max-w-xl" />
                        </div>
                    </section>

                    {/*
                        Visually separated and red-edged. Deleting an
                        account takes the business, its stock, its sales and
                        its staff with it, and the previous layout gave that
                        button the same white card as "change your name".
                    */}
                    <section className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-red-200 dark:bg-gray-800 dark:ring-red-500/30">
                        <div className="flex items-center gap-3 border-b border-red-100 bg-red-50/60 px-6 py-4 dark:border-red-500/20 dark:bg-red-500/10">
                            <ExclamationTriangleIcon className="h-5 w-5 text-red-600 dark:text-red-400" />
                            <h3 className="font-semibold text-red-900 dark:text-red-200">
                                Danger zone
                            </h3>
                        </div>

                        <div className="px-6 py-6">
                            <DeleteUserForm className="max-w-xl" />
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
