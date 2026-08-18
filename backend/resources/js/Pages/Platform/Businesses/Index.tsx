import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiDataGrid from '@/Components/Bi/BiDataGrid';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import { BiTableColumn } from '@/Components/Bi/BiTable';
import { useConfirm } from '@/Components/ConfirmDialog';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface BusinessSummary {
    id: string;
    name: string;
    business_type: string;
    /** The business column, which is derived from the subscription. */
    status: 'trial' | 'active' | 'suspended' | 'expired';
    owner: { id: string; name: string; email: string } | null;
    subscription: {
        id: string;
        /**
         * The subscription's own state. Shown beside the business badge
         * because the two can legitimately differ — a business inside its
         * grace window is `active` while the subscription is `expired` —
         * and showing only one hid the fact that the Edit plan dialog
         * writes this one.
         */
        status: string;
        subscription_plan_id: string;
        plan_name: string | null;
    } | null;
    created_at: string;
}

interface PaginatedBusinesses {
    data: BusinessSummary[];
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
}

const STATUS_VARIANT = {
    trial: 'info',
    active: 'success',
    suspended: 'danger',
    expired: 'warning',
} as const;

export default function PlatformBusinessesIndex({
    businesses,
    filters,
    plans,
}: {
    businesses: PaginatedBusinesses;
    filters: Record<string, string>;
    plans: Array<{ id: string; name: string }>;
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const [search, setSearch] = useState(filters.search ?? '');
    const [editing, setEditing] = useState<BusinessSummary | null>(null);

    const applyFilters = (overrides: Record<string, string> = {}) => {
        router.get(
            route('platform.businesses.index'),
            { ...filters, search, ...overrides },
            { preserveState: true, replace: true },
        );
    };

    const onSearchSubmit = (e: FormEvent) => {
        e.preventDefault();
        applyFilters();
    };

    const suspend = (business: BusinessSummary) => {
        askConfirm({
            title: `Suspend ${business.name}?`,
            message: 'Everyone at this business loses access immediately.',
            tone: 'danger',
            confirmLabel: 'Suspend',
            onConfirm: () => {
                router.post(
                    route('platform.businesses.suspend', business.id),
                    {},
                    {
                        onSuccess: () =>
                            notify(`${business.name} suspended.`, 'warning'),
                    },
                );
            },
        });
    };

    const activate = (business: BusinessSummary) => {
        router.post(
            route('platform.businesses.activate', business.id),
            {},
            {
                onSuccess: () =>
                    notify(`${business.name} activated.`, 'success'),
            },
        );
    };

    const impersonate = (business: BusinessSummary) => {
        askConfirm({
            title: `Log in as ${business.owner?.name ?? 'this owner'}?`,
            message: 'This is recorded in the audit log.',
            tone: 'warning',
            confirmLabel: 'Impersonate',
            onConfirm: () => {
                router.post(
                    route('platform.businesses.impersonate', business.id),
                );
            },
        });
    };

    const { data, setData, patch, processing, errors } = useForm({
        subscription_plan_id: '',
        status: 'active',
    });

    const openEdit = (business: BusinessSummary) => {
        setData({
            subscription_plan_id:
                business.subscription?.subscription_plan_id ?? '',
            status: business.subscription?.status ?? 'active',
        });
        setEditing(business);
    };

    const submitSubscription = (e: FormEvent) => {
        e.preventDefault();
        if (!editing) return;

        patch(route('platform.businesses.subscription.update', editing.id), {
            onSuccess: () => {
                setEditing(null);
                notify('Subscription updated.', 'success');
            },
        });
    };

    const columns: BiTableColumn<BusinessSummary>[] = [
        {
            key: 'business',
            label: 'Business',
            render: (business) => (
                <>
                    <p className="font-medium text-gray-900 dark:text-gray-100">
                        {business.name}
                    </p>
                    <p className="text-sm capitalize text-gray-500 dark:text-gray-400">
                        {business.business_type}
                    </p>
                </>
            ),
        },
        {
            key: 'owner',
            label: 'Owner',
            render: (business) => (
                <>
                    <p className="text-gray-900 dark:text-gray-100">
                        {business.owner?.name ?? '—'}
                    </p>
                    <p className="text-gray-500 dark:text-gray-400">
                        {business.owner?.email}
                    </p>
                </>
            ),
        },
        {
            key: 'plan',
            label: 'Plan',
            render: (business) => business.subscription?.plan_name ?? '—',
        },
        {
            key: 'status',
            label: 'Status',
            render: (business) => (
                <div className="flex flex-col items-start gap-1">
                    <BiBadge variant={STATUS_VARIANT[business.status]}>
                        {business.status}
                    </BiBadge>
                    {/*
                        Both states, because they are different facts and
                        the difference is exactly what confused this screen:
                        "Edit plan" writes the subscription, the badge above
                        renders the business, and a business inside its
                        grace window is legitimately `active` while its
                        subscription reads `expired`. Showing one and
                        editing the other made the form look broken.
                    */}
                    {business.subscription?.status &&
                        business.subscription.status !== business.status && (
                            <span className="text-xs text-gray-500 dark:text-gray-400">
                                plan: {business.subscription.status}
                            </span>
                        )}
                </div>
            ),
        },
        {
            key: 'actions',
            label: 'Actions',
            align: 'right',
            render: (business) => (
                <div className="flex justify-end gap-3">
                    <button
                        onClick={() => openEdit(business)}
                        className="text-indigo-600 hover:underline"
                    >
                        Edit plan
                    </button>
                    <Link
                        href={route(
                            'platform.businesses.modules.index',
                            business.id,
                        )}
                        className="text-indigo-600 hover:underline"
                    >
                        Sections
                    </Link>
                    {business.owner && (
                        <button
                            onClick={() => impersonate(business)}
                            className="text-indigo-600 hover:underline"
                        >
                            Log in as
                        </button>
                    )}
                    {business.status === 'suspended' ? (
                        <button
                            onClick={() => activate(business)}
                            className="text-emerald-600 hover:underline"
                        >
                            Activate
                        </button>
                    ) : (
                        <button
                            onClick={() => suspend(business)}
                            className="text-red-600 hover:underline"
                        >
                            Suspend
                        </button>
                    )}
                </div>
            ),
        },
    ];

    return (
        <PlatformLayout>
            <Head title="Businesses" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                        Businesses
                    </h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {businesses.meta.total} total
                    </p>
                </div>

                <BiDataGrid
                    columns={columns}
                    paginated={businesses}
                    rowKey={(business) => business.id}
                    emptyMessage="No businesses match these filters."
                    toolbar={
                        <>
                            <form
                                onSubmit={onSearchSubmit}
                                className="flex gap-2"
                            >
                                <TextInput
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search by business name or owner email"
                                    className="w-72"
                                />
                                <SecondaryButton type="submit">
                                    Search
                                </SecondaryButton>
                            </form>

                            <SelectInput
                                value={filters.status ?? ''}
                                onChange={(e) =>
                                    applyFilters({ status: e.target.value })
                                }
                            >
                                <option value="">All statuses</option>
                                <option value="trial">Trial</option>
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                                <option value="expired">Expired</option>
                            </SelectInput>
                        </>
                    }
                />
            </div>

            <BiModal
                show={editing !== null}
                onClose={() => setEditing(null)}
                title={`Edit subscription — ${editing?.name ?? ''}`}
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setEditing(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="edit-subscription-form"
                            disabled={processing}
                        >
                            Save
                        </BiButton>
                    </>
                }
            >
                <form id="edit-subscription-form" onSubmit={submitSubscription}>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Plan
                        </label>
                        <SelectInput
                            className="mt-1 block w-full"
                            value={data.subscription_plan_id}
                            onChange={(e) =>
                                setData('subscription_plan_id', e.target.value)
                            }
                        >
                            <option value="">Select a plan</option>
                            {plans.map((plan) => (
                                <option key={plan.id} value={plan.id}>
                                    {plan.name}
                                </option>
                            ))}
                        </SelectInput>
                        {errors.subscription_plan_id && (
                            <p className="mt-1 text-sm text-red-600">
                                {errors.subscription_plan_id}
                            </p>
                        )}
                    </div>

                    <div className="mt-4">
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Subscription status
                        </label>
                        <SelectInput
                            className="mt-1 block w-full"
                            value={data.status}
                            onChange={(e) => setData('status', e.target.value)}
                        >
                            <option value="trialing">Trialing</option>
                            <option value="active">Active</option>
                            <option value="past_due">Past due</option>
                            <option value="canceled">Canceled</option>
                            <option value="expired">Expired</option>
                        </SelectInput>
                    </div>
                </form>
            </BiModal>
        </PlatformLayout>
    );
}
