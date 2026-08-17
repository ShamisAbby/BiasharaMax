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
import PlatformLayout from '@/Layouts/PlatformLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface ModuleRow {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    version: string;
    icon: string | null;
    category: string | null;
    is_premium: boolean;
    status: 'active' | 'inactive' | 'deprecated';
    visibility: 'public' | 'hidden' | 'beta';
    is_desktop_supported: boolean;
    is_cloud_supported: boolean;
    is_hybrid_supported: boolean;
    sort_order: number;
    businesses_count: number;
    dependencies: { id: string; name: string }[];
    subscription_plans: { id: string; name: string }[];
    business_types: { id: string; name: string }[];
}

const STATUS_VARIANT = {
    active: 'success',
    inactive: 'neutral',
    deprecated: 'danger',
} as const;

const VISIBILITY_VARIANT = {
    public: 'info',
    beta: 'warning',
    hidden: 'neutral',
} as const;

const EMPTY_FORM = {
    name: '',
    slug: '',
    description: '',
    version: '1.0.0',
    icon: '',
    category: '',
    is_premium: false,
    visibility: 'public' as 'public' | 'hidden' | 'beta',
    is_desktop_supported: true,
    is_cloud_supported: true,
    is_hybrid_supported: false,
    sort_order: '0',
    dependency_ids: [] as string[],
};

export default function ModulesIndex({
    modules: list,
    plans,
    businessTypes,
}: {
    modules: ModuleRow[];
    plans: { id: string; name: string }[];
    businessTypes: { id: string; name: string }[];
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const [editing, setEditing] = useState<ModuleRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [versioning, setVersioning] = useState<ModuleRow | null>(null);
    const [versionInput, setVersionInput] = useState('');
    const [versionNotes, setVersionNotes] = useState('');
    const [assigning, setAssigning] = useState<ModuleRow | null>(null);
    const [assignPlanIds, setAssignPlanIds] = useState<string[]>([]);
    const [assignTypeIds, setAssignTypeIds] = useState<string[]>([]);

    const { data, setData, post, patch, processing, errors, reset } =
        useForm(EMPTY_FORM);

    const openCreate = () => {
        reset();
        setData(EMPTY_FORM);
        setCreating(true);
    };

    const openEdit = (module: ModuleRow) => {
        setData({
            name: module.name,
            slug: module.slug,
            description: module.description ?? '',
            version: module.version,
            icon: module.icon ?? '',
            category: module.category ?? '',
            is_premium: module.is_premium,
            visibility: module.visibility,
            is_desktop_supported: module.is_desktop_supported,
            is_cloud_supported: module.is_cloud_supported,
            is_hybrid_supported: module.is_hybrid_supported,
            sort_order: String(module.sort_order),
            dependency_ids: module.dependencies.map((d) => d.id),
        });
        setEditing(module);
    };

    const close = () => {
        setEditing(null);
        setCreating(false);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (editing) {
            patch(route('platform.modules.update', editing.id), {
                onSuccess: () => {
                    close();
                    notify('Module updated.', 'success');
                },
            });
        } else {
            post(route('platform.modules.store'), {
                onSuccess: () => {
                    close();
                    notify('Module created.', 'success');
                },
            });
        }
    };

    const toggleEnabled = (module: ModuleRow) => {
        router.post(
            route(
                module.status === 'active'
                    ? 'platform.modules.disable'
                    : 'platform.modules.enable',
                module.id,
            ),
        );
    };

    const destroy = (module: ModuleRow) => {
        askConfirm({
            title: `Delete "${module.name}"?`,
            message: 'This cannot be undone.',
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(route('platform.modules.destroy', module.id));
            },
        });
    };

    const openVersion = (module: ModuleRow) => {
        setVersionInput(module.version);
        setVersionNotes('');
        setVersioning(module);
    };

    const submitVersion = (e: FormEvent) => {
        e.preventDefault();
        if (!versioning) return;

        router.post(
            route('platform.modules.version.update', versioning.id),
            { version: versionInput, notes: versionNotes },
            {
                onSuccess: () => {
                    setVersioning(null);
                    notify('Module version updated.', 'success');
                },
            },
        );
    };

    const openAssign = (module: ModuleRow) => {
        setAssignPlanIds(module.subscription_plans.map((p) => p.id));
        setAssignTypeIds(module.business_types.map((t) => t.id));
        setAssigning(module);
    };

    const submitAssign = (e: FormEvent) => {
        e.preventDefault();
        if (!assigning) return;

        router.patch(
            route('platform.modules.plans.update', assigning.id),
            { plan_ids: assignPlanIds },
            {
                onSuccess: () =>
                    router.patch(
                        route(
                            'platform.modules.business-types.update',
                            assigning.id,
                        ),
                        { business_type_ids: assignTypeIds },
                        {
                            onSuccess: () => {
                                setAssigning(null);
                                notify(
                                    'Module assignments updated.',
                                    'success',
                                );
                            },
                        },
                    ),
            },
        );
    };

    const togglePlanId = (id: string) => {
        setAssignPlanIds((prev) =>
            prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id],
        );
    };

    const toggleTypeId = (id: string) => {
        setAssignTypeIds((prev) =>
            prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id],
        );
    };

    const toggleDependency = (moduleId: string) => {
        setData(
            'dependency_ids',
            data.dependency_ids.includes(moduleId)
                ? data.dependency_ids.filter((id) => id !== moduleId)
                : [...data.dependency_ids, moduleId],
        );
    };

    const show = editing !== null || creating;

    return (
        <PlatformLayout>
            <Head title="Modules" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Modules
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {list.length} modules registered.
                        </p>
                    </div>
                    <BiButton onClick={openCreate}>New module</BiButton>
                </div>

                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {list.map((module) => (
                        <BiCard
                            key={module.id}
                            title={module.name}
                            actions={
                                <div className="flex gap-1.5">
                                    <BiBadge
                                        variant={STATUS_VARIANT[module.status]}
                                    >
                                        {module.status}
                                    </BiBadge>
                                    <BiBadge
                                        variant={
                                            VISIBILITY_VARIANT[
                                                module.visibility
                                            ]
                                        }
                                    >
                                        {module.visibility}
                                    </BiBadge>
                                </div>
                            }
                        >
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {module.description ?? 'No description'}
                            </p>
                            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                v{module.version}{' '}
                                {module.category ? `· ${module.category}` : ''}
                                {module.is_premium ? ' · Premium' : ''}
                            </p>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {module.businesses_count} installed ·{' '}
                                {module.dependencies.length} dependencies ·{' '}
                                {module.subscription_plans.length} plans
                            </p>

                            <div className="mt-4 flex flex-wrap gap-3 text-sm">
                                <button
                                    onClick={() => openEdit(module)}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Edit
                                </button>
                                <button
                                    onClick={() => openVersion(module)}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Version
                                </button>
                                <button
                                    onClick={() => openAssign(module)}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Assign
                                </button>
                                <button
                                    onClick={() => toggleEnabled(module)}
                                    className="text-gray-600 hover:underline dark:text-gray-300"
                                >
                                    {module.status === 'active'
                                        ? 'Disable'
                                        : 'Enable'}
                                </button>
                                {module.businesses_count === 0 && (
                                    <button
                                        onClick={() => destroy(module)}
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
                title={editing ? `Edit ${editing.name}` : 'New module'}
                maxWidth="2xl"
                footer={
                    <>
                        <SecondaryButton type="button" onClick={close}>
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="module-form"
                            disabled={processing}
                        >
                            Save
                        </BiButton>
                    </>
                }
            >
                <form id="module-form" onSubmit={submit} className="space-y-4">
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
                                Version
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={data.version}
                                onChange={(e) =>
                                    setData('version', e.target.value)
                                }
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Category
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={data.category}
                                onChange={(e) =>
                                    setData('category', e.target.value)
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
                                Visibility
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={data.visibility}
                                onChange={(e) =>
                                    setData(
                                        'visibility',
                                        e.target
                                            .value as typeof data.visibility,
                                    )
                                }
                            >
                                <option value="public">Public</option>
                                <option value="beta">Beta</option>
                                <option value="hidden">Hidden</option>
                            </SelectInput>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        {(
                            [
                                ['is_premium', 'Premium'],
                                ['is_desktop_supported', 'Desktop supported'],
                                ['is_cloud_supported', 'Cloud supported'],
                                ['is_hybrid_supported', 'Hybrid supported'],
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

                    <div>
                        <p className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Dependencies
                        </p>
                        <div className="grid max-h-40 grid-cols-2 gap-2 overflow-y-auto sm:grid-cols-3">
                            {list
                                .filter((m) => m.id !== editing?.id)
                                .map((module) => (
                                    <label
                                        key={module.id}
                                        className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        <Checkbox
                                            checked={data.dependency_ids.includes(
                                                module.id,
                                            )}
                                            onChange={() =>
                                                toggleDependency(module.id)
                                            }
                                        />
                                        {module.name}
                                    </label>
                                ))}
                        </div>
                    </div>
                </form>
            </BiModal>

            <BiModal
                show={versioning !== null}
                onClose={() => setVersioning(null)}
                title={`Update version — ${versioning?.name ?? ''}`}
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setVersioning(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton type="submit" form="module-version-form">
                            Save
                        </BiButton>
                    </>
                }
            >
                <form
                    id="module-version-form"
                    onSubmit={submitVersion}
                    className="space-y-4"
                >
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            New version
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={versionInput}
                            onChange={(e) => setVersionInput(e.target.value)}
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Release notes
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={versionNotes}
                            onChange={(e) => setVersionNotes(e.target.value)}
                        />
                    </div>
                </form>
            </BiModal>

            <BiModal
                show={assigning !== null}
                onClose={() => setAssigning(null)}
                title={`Assign — ${assigning?.name ?? ''}`}
                maxWidth="lg"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setAssigning(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton type="submit" form="module-assign-form">
                            Save
                        </BiButton>
                    </>
                }
            >
                <form
                    id="module-assign-form"
                    onSubmit={submitAssign}
                    className="space-y-4"
                >
                    <div>
                        <p className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Subscription plans
                        </p>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {plans.map((plan) => (
                                <label
                                    key={plan.id}
                                    className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                >
                                    <Checkbox
                                        checked={assignPlanIds.includes(
                                            plan.id,
                                        )}
                                        onChange={() => togglePlanId(plan.id)}
                                    />
                                    {plan.name}
                                </label>
                            ))}
                        </div>
                    </div>
                    <div>
                        <p className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Business types
                        </p>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {businessTypes.map((type) => (
                                <label
                                    key={type.id}
                                    className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                >
                                    <Checkbox
                                        checked={assignTypeIds.includes(
                                            type.id,
                                        )}
                                        onChange={() => toggleTypeId(type.id)}
                                    />
                                    {type.name}
                                </label>
                            ))}
                        </div>
                    </div>
                </form>
            </BiModal>
        </PlatformLayout>
    );
}
