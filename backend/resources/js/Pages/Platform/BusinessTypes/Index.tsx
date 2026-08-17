import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import Checkbox from '@/Components/Checkbox';
import { useConfirm } from '@/Components/ConfirmDialog';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface BusinessTypeRow {
    id: string;
    name: string;
    slug: string;
    icon: string | null;
    color: string | null;
    description: string | null;
    default_currency: string | null;
    default_tax_rate: string | null;
    inventory_enabled: boolean;
    pos_enabled: boolean;
    accounting_enabled: boolean;
    crm_enabled: boolean;
    website_enabled: boolean;
    online_ordering_enabled: boolean;
    offline_mode_enabled: boolean;
    desktop_edition_enabled: boolean;
    default_employee_limit: number | null;
    default_branch_limit: number | null;
    default_warehouse_limit: number | null;
    default_storage_limit_mb: number | null;
    status: 'active' | 'inactive' | 'archived';
    sort_order: number;
    businesses_count: number;
    modules: { id: string; name: string }[];
    subscription_plans: { id: string; name: string }[];
}

const FLAGS = [
    ['inventory_enabled', 'Inventory'],
    ['pos_enabled', 'POS'],
    ['accounting_enabled', 'Accounting'],
    ['crm_enabled', 'CRM'],
    ['website_enabled', 'Website'],
    ['online_ordering_enabled', 'Online ordering'],
    ['offline_mode_enabled', 'Offline mode'],
    ['desktop_edition_enabled', 'Desktop edition'],
] as const;

const STATUS_VARIANT = {
    active: 'success',
    inactive: 'neutral',
    archived: 'warning',
} as const;

const EMPTY_FORM = {
    name: '',
    slug: '',
    icon: '',
    color: '',
    description: '',
    default_currency: 'KES',
    default_tax_rate: '',
    website_template: '',
    inventory_enabled: false,
    pos_enabled: false,
    accounting_enabled: false,
    crm_enabled: false,
    website_enabled: false,
    online_ordering_enabled: false,
    offline_mode_enabled: false,
    desktop_edition_enabled: false,
    default_employee_limit: '',
    default_branch_limit: '',
    default_warehouse_limit: '',
    default_storage_limit_mb: '',
    sort_order: '0',
    module_ids: [] as string[],
    subscription_plan_ids: [] as string[],
};

export default function BusinessTypesIndex({
    businessTypes: types,
    modules,
    plans,
}: {
    businessTypes: BusinessTypeRow[];
    modules: { id: string; name: string }[];
    plans: { id: string; name: string }[];
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const [editing, setEditing] = useState<BusinessTypeRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [cloning, setCloning] = useState<BusinessTypeRow | null>(null);
    const [cloneName, setCloneName] = useState('');

    const { data, setData, post, patch, processing, errors, reset } =
        useForm(EMPTY_FORM);

    const openCreate = () => {
        reset();
        setData(EMPTY_FORM);
        setCreating(true);
    };

    const openEdit = (type: BusinessTypeRow) => {
        setData({
            name: type.name,
            slug: type.slug,
            icon: type.icon ?? '',
            color: type.color ?? '',
            description: type.description ?? '',
            default_currency: type.default_currency ?? '',
            default_tax_rate: type.default_tax_rate ?? '',
            website_template: '',
            inventory_enabled: type.inventory_enabled,
            pos_enabled: type.pos_enabled,
            accounting_enabled: type.accounting_enabled,
            crm_enabled: type.crm_enabled,
            website_enabled: type.website_enabled,
            online_ordering_enabled: type.online_ordering_enabled,
            offline_mode_enabled: type.offline_mode_enabled,
            desktop_edition_enabled: type.desktop_edition_enabled,
            default_employee_limit:
                type.default_employee_limit?.toString() ?? '',
            default_branch_limit: type.default_branch_limit?.toString() ?? '',
            default_warehouse_limit:
                type.default_warehouse_limit?.toString() ?? '',
            default_storage_limit_mb:
                type.default_storage_limit_mb?.toString() ?? '',
            sort_order: String(type.sort_order),
            module_ids: type.modules.map((m) => m.id),
            subscription_plan_ids: type.subscription_plans.map((p) => p.id),
        });
        setEditing(type);
    };

    const close = () => {
        setEditing(null);
        setCreating(false);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (editing) {
            patch(route('platform.business-types.update', editing.id), {
                onSuccess: () => {
                    close();
                    notify('Business type updated.', 'success');
                },
            });
        } else {
            post(route('platform.business-types.store'), {
                onSuccess: () => {
                    close();
                    notify('Business type created.', 'success');
                },
            });
        }
    };

    const archive = (type: BusinessTypeRow) => {
        router.post(route('platform.business-types.archive', type.id));
    };

    const activate = (type: BusinessTypeRow) => {
        router.post(route('platform.business-types.activate', type.id));
    };

    const deactivate = (type: BusinessTypeRow) => {
        router.post(route('platform.business-types.deactivate', type.id));
    };

    const destroy = (type: BusinessTypeRow) => {
        askConfirm({
            title: `Delete "${type.name}"?`,
            message: 'This cannot be undone.',
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route('platform.business-types.destroy', type.id),
                );
            },
        });
    };

    const submitClone = (e: FormEvent) => {
        e.preventDefault();
        if (!cloning) return;

        router.post(
            route('platform.business-types.clone', cloning.id),
            { name: cloneName },
            {
                onSuccess: () => {
                    setCloning(null);
                    setCloneName('');
                    notify('Business type cloned.', 'success');
                },
            },
        );
    };

    const toggleModule = (moduleId: string) => {
        setData(
            'module_ids',
            data.module_ids.includes(moduleId)
                ? data.module_ids.filter((id) => id !== moduleId)
                : [...data.module_ids, moduleId],
        );
    };

    const togglePlan = (planId: string) => {
        setData(
            'subscription_plan_ids',
            data.subscription_plan_ids.includes(planId)
                ? data.subscription_plan_ids.filter((id) => id !== planId)
                : [...data.subscription_plan_ids, planId],
        );
    };

    const show = editing !== null || creating;

    return (
        <PlatformLayout>
            <Head title="Business Types" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Business Types
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {types.length} types configured.
                        </p>
                    </div>
                    <BiButton onClick={openCreate}>New business type</BiButton>
                </div>

                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {types.map((type) => (
                        <BiCard
                            key={type.id}
                            title={type.name}
                            actions={
                                <BiBadge variant={STATUS_VARIANT[type.status]}>
                                    {type.status}
                                </BiBadge>
                            }
                        >
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {type.description ?? 'No description'}
                            </p>

                            <div className="mt-3 flex flex-wrap gap-1.5">
                                {FLAGS.filter(([key]) => type[key]).map(
                                    ([key, label]) => (
                                        <BiBadge key={key} variant="info">
                                            {label}
                                        </BiBadge>
                                    ),
                                )}
                            </div>

                            <p className="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                {type.businesses_count} business
                                {type.businesses_count === 1 ? '' : 'es'} ·{' '}
                                {type.modules.length} modules ·{' '}
                                {type.subscription_plans.length} plans
                            </p>

                            <div className="mt-4 flex flex-wrap gap-3 text-sm">
                                <button
                                    onClick={() => openEdit(type)}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Edit
                                </button>
                                <button
                                    onClick={() => {
                                        setCloning(type);
                                        setCloneName(`${type.name} copy`);
                                    }}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Clone
                                </button>
                                {type.status === 'active' ? (
                                    <button
                                        onClick={() => deactivate(type)}
                                        className="text-gray-600 hover:underline dark:text-gray-300"
                                    >
                                        Deactivate
                                    </button>
                                ) : (
                                    <button
                                        onClick={() => activate(type)}
                                        className="text-emerald-600 hover:underline"
                                    >
                                        Activate
                                    </button>
                                )}
                                {type.status !== 'archived' && (
                                    <button
                                        onClick={() => archive(type)}
                                        className="text-amber-600 hover:underline"
                                    >
                                        Archive
                                    </button>
                                )}
                                {type.businesses_count === 0 && (
                                    <button
                                        onClick={() => destroy(type)}
                                        className="text-red-600 hover:underline"
                                    >
                                        Delete
                                    </button>
                                )}
                            </div>
                        </BiCard>
                    ))}
                </div>
            </div>

            <BiModal
                show={show}
                onClose={close}
                title={editing ? `Edit ${editing.name}` : 'New business type'}
                maxWidth="2xl"
                footer={
                    <>
                        <SecondaryButton type="button" onClick={close}>
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="business-type-form"
                            disabled={processing}
                        >
                            Save
                        </BiButton>
                    </>
                }
            >
                <form
                    id="business-type-form"
                    onSubmit={submit}
                    className="space-y-4"
                >
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
                                Currency
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={data.default_currency}
                                onChange={(e) =>
                                    setData('default_currency', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Default tax rate (%)
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                value={data.default_tax_rate}
                                onChange={(e) =>
                                    setData('default_tax_rate', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Icon
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={data.icon}
                                onChange={(e) =>
                                    setData('icon', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Color
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                placeholder="#6366f1"
                                value={data.color}
                                onChange={(e) =>
                                    setData('color', e.target.value)
                                }
                            />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Default employee limit
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                placeholder="Advisory only"
                                value={data.default_employee_limit}
                                onChange={(e) =>
                                    setData(
                                        'default_employee_limit',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Default branch limit
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                placeholder="Advisory only"
                                value={data.default_branch_limit}
                                onChange={(e) =>
                                    setData(
                                        'default_branch_limit',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Default warehouse limit
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                placeholder="Advisory only"
                                value={data.default_warehouse_limit}
                                onChange={(e) =>
                                    setData(
                                        'default_warehouse_limit',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Default storage (MB)
                            </label>
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                placeholder="Advisory only"
                                value={data.default_storage_limit_mb}
                                onChange={(e) =>
                                    setData(
                                        'default_storage_limit_mb',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                    </div>
                    <p className="text-xs text-gray-500 dark:text-gray-400">
                        These limits are advisory defaults only — the
                        subscription plan's limits are what's actually enforced.
                    </p>

                    <div>
                        <p className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Feature flags
                        </p>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            {FLAGS.map(([key, label]) => (
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
                    </div>

                    <div>
                        <p className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Default modules
                        </p>
                        <div className="grid max-h-40 grid-cols-2 gap-2 overflow-y-auto sm:grid-cols-3">
                            {modules.map((module) => (
                                <label
                                    key={module.id}
                                    className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                >
                                    <Checkbox
                                        checked={data.module_ids.includes(
                                            module.id,
                                        )}
                                        onChange={() => toggleModule(module.id)}
                                    />
                                    {module.name}
                                </label>
                            ))}
                        </div>
                    </div>

                    <div>
                        <p className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Eligible subscription plans
                        </p>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {plans.map((plan) => (
                                <label
                                    key={plan.id}
                                    className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                >
                                    <Checkbox
                                        checked={data.subscription_plan_ids.includes(
                                            plan.id,
                                        )}
                                        onChange={() => togglePlan(plan.id)}
                                    />
                                    {plan.name}
                                </label>
                            ))}
                        </div>
                    </div>
                </form>
            </BiModal>

            <BiModal
                show={cloning !== null}
                onClose={() => setCloning(null)}
                title={`Clone ${cloning?.name ?? ''}`}
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setCloning(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton type="submit" form="clone-business-type-form">
                            Clone
                        </BiButton>
                    </>
                }
            >
                <form id="clone-business-type-form" onSubmit={submitClone}>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        New name
                    </label>
                    <TextInput
                        className="mt-1 block w-full"
                        value={cloneName}
                        onChange={(e) => setCloneName(e.target.value)}
                    />
                </form>
            </BiModal>
        </PlatformLayout>
    );
}
