import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import Checkbox from '@/Components/Checkbox';
import { useConfirm } from '@/Components/ConfirmDialog';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PlatformSubscriptionsLayout from '@/Layouts/PlatformSubscriptionsLayout';
import { formatCurrency } from '@/lib/currency';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface PlanRow {
    id: string;
    name: string;
    slug: string;
    type: 'standard' | 'enterprise';
    description: string | null;
    price_monthly: string;
    price_quarterly: string;
    price_yearly: string;
    price_lifetime: string | null;
    trial_days: number;
    is_active: boolean;
    sort_order: number;
    max_users: number | null;
    max_branches: number | null;
    max_warehouses: number | null;
    max_products: number | null;
    max_employees: number | null;
    max_storage_mb: number | null;
    max_api_requests_per_day: number | null;
    max_notifications_per_month: number | null;
    includes_website: boolean;
    includes_ai: boolean;
    includes_offline_sync: boolean;
    includes_desktop_edition: boolean;
    includes_reports: boolean;
    subscriptions_count: number;
}

const EMPTY_FORM = {
    name: '',
    slug: '',
    type: 'standard' as 'standard' | 'enterprise',
    description: '',
    price_monthly: '0',
    price_quarterly: '0',
    price_yearly: '0',
    price_lifetime: '',
    trial_days: '30',
    is_active: true,
    sort_order: '0',
    max_users: '',
    max_branches: '',
    max_warehouses: '',
    max_products: '',
    max_employees: '',
    max_storage_mb: '',
    max_api_requests_per_day: '',
    max_notifications_per_month: '',
    includes_website: false,
    includes_ai: false,
    includes_offline_sync: false,
    includes_desktop_edition: false,
    includes_reports: true,
};

export default function SubscriptionPlansIndex({
    plans,
}: {
    plans: PlanRow[];
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const [editing, setEditing] = useState<PlanRow | null>(null);
    const [creating, setCreating] = useState(false);

    const { data, setData, post, patch, processing, errors, reset } =
        useForm(EMPTY_FORM);

    const openCreate = () => {
        reset();
        setData(EMPTY_FORM);
        setCreating(true);
    };

    const openEdit = (plan: PlanRow) => {
        setData({
            name: plan.name,
            slug: plan.slug,
            type: plan.type,
            description: plan.description ?? '',
            price_monthly: plan.price_monthly,
            price_quarterly: plan.price_quarterly,
            price_yearly: plan.price_yearly,
            price_lifetime: plan.price_lifetime ?? '',
            trial_days: String(plan.trial_days),
            is_active: plan.is_active,
            sort_order: String(plan.sort_order),
            max_users: plan.max_users?.toString() ?? '',
            max_branches: plan.max_branches?.toString() ?? '',
            max_warehouses: plan.max_warehouses?.toString() ?? '',
            max_products: plan.max_products?.toString() ?? '',
            max_employees: plan.max_employees?.toString() ?? '',
            max_storage_mb: plan.max_storage_mb?.toString() ?? '',
            max_api_requests_per_day:
                plan.max_api_requests_per_day?.toString() ?? '',
            max_notifications_per_month:
                plan.max_notifications_per_month?.toString() ?? '',
            includes_website: plan.includes_website,
            includes_ai: plan.includes_ai,
            includes_offline_sync: plan.includes_offline_sync,
            includes_desktop_edition: plan.includes_desktop_edition,
            includes_reports: plan.includes_reports,
        });
        setEditing(plan);
    };

    const close = () => {
        setEditing(null);
        setCreating(false);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (editing) {
            patch(route('platform.subscriptions.plans.update', editing.id), {
                onSuccess: () => {
                    close();
                    notify('Plan updated.', 'success');
                },
            });
        } else {
            post(route('platform.subscriptions.plans.store'), {
                onSuccess: () => {
                    close();
                    notify('Plan created.', 'success');
                },
            });
        }
    };

    const toggleActive = (plan: PlanRow) => {
        router.post(
            route(
                plan.is_active
                    ? 'platform.subscriptions.plans.deactivate'
                    : 'platform.subscriptions.plans.activate',
                plan.id,
            ),
        );
    };

    const destroy = (plan: PlanRow) => {
        askConfirm({
            title: `Delete "${plan.name}"?`,
            message: 'This cannot be undone.',
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route('platform.subscriptions.plans.destroy', plan.id),
                );
            },
        });
    };

    const show = editing !== null || creating;

    return (
        <PlatformSubscriptionsLayout title="Plans">
            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    {plans.length} plans in the catalog.
                </p>
                <BiButton onClick={openCreate}>New plan</BiButton>
            </div>

            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {plans.map((plan) => (
                    <BiCard
                        key={plan.id}
                        title={plan.name}
                        actions={
                            <BiBadge
                                variant={plan.is_active ? 'success' : 'neutral'}
                            >
                                {plan.is_active ? 'Active' : 'Inactive'}
                            </BiBadge>
                        }
                    >
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {plan.description}
                        </p>
                        <p className="mt-3 text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {formatCurrency(plan.price_monthly)}
                            <span className="text-sm font-normal text-gray-500">
                                {' '}
                                /mo
                            </span>
                        </p>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            {plan.subscriptions_count} subscriber
                            {plan.subscriptions_count === 1 ? '' : 's'}
                        </p>

                        <div className="mt-4 flex gap-3 text-sm">
                            <button
                                onClick={() => openEdit(plan)}
                                className="text-indigo-600 hover:underline"
                            >
                                Edit
                            </button>
                            <button
                                onClick={() => toggleActive(plan)}
                                className="text-gray-600 hover:underline dark:text-gray-300"
                            >
                                {plan.is_active ? 'Deactivate' : 'Activate'}
                            </button>
                            {plan.subscriptions_count === 0 && (
                                <button
                                    onClick={() => destroy(plan)}
                                    className="text-red-600 hover:underline"
                                >
                                    Delete
                                </button>
                            )}
                        </div>
                    </BiCard>
                ))}
            </div>

            <BiModal
                show={show}
                onClose={close}
                title={editing ? `Edit ${editing.name}` : 'New plan'}
                maxWidth="2xl"
                footer={
                    <>
                        <SecondaryButton type="button" onClick={close}>
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="plan-form"
                            disabled={processing}
                        >
                            Save
                        </BiButton>
                    </>
                }
            >
                <form id="plan-form" onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Name
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                            {errors.name && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.name}
                                </p>
                            )}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Slug
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={data.slug}
                                onChange={(e) =>
                                    setData('slug', e.target.value)
                                }
                            />
                            {errors.slug && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.slug}
                                </p>
                            )}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Type
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={data.type}
                                onChange={(e) =>
                                    setData(
                                        'type',
                                        e.target.value as
                                            | 'standard'
                                            | 'enterprise',
                                    )
                                }
                            >
                                <option value="standard">Standard</option>
                                <option value="enterprise">Enterprise</option>
                            </SelectInput>
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Description
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                        />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Monthly price
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                value={data.price_monthly}
                                onChange={(e) =>
                                    setData('price_monthly', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Quarterly price
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                value={data.price_quarterly}
                                onChange={(e) =>
                                    setData('price_quarterly', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Yearly price
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                value={data.price_yearly}
                                onChange={(e) =>
                                    setData('price_yearly', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Lifetime price
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                value={data.price_lifetime}
                                onChange={(e) =>
                                    setData('price_lifetime', e.target.value)
                                }
                                placeholder="Leave blank if unsupported"
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Trial days
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                value={data.trial_days}
                                onChange={(e) =>
                                    setData('trial_days', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Max users
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                placeholder="Unlimited"
                                value={data.max_users}
                                onChange={(e) =>
                                    setData('max_users', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Max branches
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                placeholder="Unlimited"
                                value={data.max_branches}
                                onChange={(e) =>
                                    setData('max_branches', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Max warehouses
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                placeholder="Unlimited"
                                value={data.max_warehouses}
                                onChange={(e) =>
                                    setData('max_warehouses', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Max products
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                placeholder="Unlimited"
                                value={data.max_products}
                                onChange={(e) =>
                                    setData('max_products', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Max employees
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                placeholder="Unlimited"
                                value={data.max_employees}
                                onChange={(e) =>
                                    setData('max_employees', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Max storage (MB)
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                placeholder="Unlimited"
                                value={data.max_storage_mb}
                                onChange={(e) =>
                                    setData('max_storage_mb', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Max API req/day
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                placeholder="Unlimited"
                                value={data.max_api_requests_per_day}
                                onChange={(e) =>
                                    setData(
                                        'max_api_requests_per_day',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        {(
                            [
                                ['includes_website', 'Website included'],
                                ['includes_ai', 'AI included'],
                                [
                                    'includes_offline_sync',
                                    'Offline sync included',
                                ],
                                [
                                    'includes_desktop_edition',
                                    'Desktop edition included',
                                ],
                                ['includes_reports', 'Reports included'],
                                ['is_active', 'Active'],
                            ] as const
                        ).map(([key, label]) => (
                            <label
                                key={key}
                                className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <Checkbox
                                    checked={data[key] as boolean}
                                    onChange={(e) =>
                                        setData(key, e.target.checked)
                                    }
                                />
                                {label}
                            </label>
                        ))}
                    </div>
                </form>
            </BiModal>
        </PlatformSubscriptionsLayout>
    );
}
