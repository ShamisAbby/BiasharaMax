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
import PlatformRbacLayout from '@/Layouts/PlatformRbacLayout';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface PlatformRoleRow {
    id: string;
    name: string;
    slug: string;
    is_system: boolean;
    description: string | null;
    permissions_count: number;
    platform_users_count: number;
    permission_ids: string[];
}

interface PermissionOption {
    id: string;
    module: string;
    action: string;
    name: string;
    slug: string;
}

const EMPTY_FORM = {
    name: '',
    slug: '',
    description: '',
    permission_ids: [] as string[],
};

export default function PlatformRolesIndex({
    platformRoles: roles,
    templates,
    permissions,
}: {
    platformRoles: PlatformRoleRow[];
    templates: { id: string; name: string }[];
    permissions: PermissionOption[];
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const [editing, setEditing] = useState<PlatformRoleRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [cloning, setCloning] = useState<PlatformRoleRow | null>(null);
    const [cloneName, setCloneName] = useState('');
    const [applying, setApplying] = useState<PlatformRoleRow | null>(null);
    const [templateId, setTemplateId] = useState('');

    const { data, setData, post, patch, processing, errors, reset } =
        useForm(EMPTY_FORM);

    const openCreate = () => {
        reset();
        setData(EMPTY_FORM);
        setCreating(true);
    };

    const openEdit = (role: PlatformRoleRow) => {
        setData({
            name: role.name,
            slug: role.slug,
            description: role.description ?? '',
            permission_ids: role.permission_ids,
        });
        setEditing(role);
    };

    const close = () => {
        setEditing(null);
        setCreating(false);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (editing) {
            patch(route('platform.rbac.platform-roles.update', editing.id), {
                onSuccess: () => {
                    close();
                    notify('Platform role updated.', 'success');
                },
            });
        } else {
            post(route('platform.rbac.platform-roles.store'), {
                onSuccess: () => {
                    close();
                    notify('Platform role created.', 'success');
                },
            });
        }
    };

    const destroy = (role: PlatformRoleRow) => {
        askConfirm({
            title: `Delete "${role.name}"?`,
            message: 'This cannot be undone.',
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route('platform.rbac.platform-roles.destroy', role.id),
                );
            },
        });
    };

    const submitClone = (e: FormEvent) => {
        e.preventDefault();
        if (!cloning) return;

        router.post(
            route('platform.rbac.platform-roles.clone', cloning.id),
            { name: cloneName },
            {
                onSuccess: () => {
                    setCloning(null);
                    setCloneName('');
                    notify('Platform role cloned.', 'success');
                },
            },
        );
    };

    const submitApplyTemplate = (e: FormEvent) => {
        e.preventDefault();
        if (!applying) return;

        router.post(
            route('platform.rbac.platform-roles.apply-template', applying.id),
            { role_template_id: templateId },
            {
                onSuccess: () => {
                    setApplying(null);
                    setTemplateId('');
                    notify('Template applied.', 'success');
                },
            },
        );
    };

    const togglePermission = (id: string) => {
        setData(
            'permission_ids',
            data.permission_ids.includes(id)
                ? data.permission_ids.filter((p) => p !== id)
                : [...data.permission_ids, id],
        );
    };

    const show = editing !== null || creating;
    const permissionsByModule = permissions.reduce<
        Record<string, PermissionOption[]>
    >((acc, perm) => {
        (acc[perm.module] ??= []).push(perm);
        return acc;
    }, {});

    return (
        <PlatformRbacLayout title="Platform Roles">
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {roles.length} platform roles. These control access to
                        the SuperAdmin console itself.
                    </p>
                    <BiButton onClick={openCreate}>New platform role</BiButton>
                </div>

                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {roles.map((role) => (
                        <BiCard
                            key={role.id}
                            title={role.name}
                            actions={
                                role.is_system && (
                                    <BiBadge variant="info">System</BiBadge>
                                )
                            }
                        >
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {role.description ?? 'No description'}
                            </p>
                            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                {role.permissions_count} permissions ·{' '}
                                {role.platform_users_count} users
                            </p>

                            <div className="mt-4 flex flex-wrap gap-3 text-sm">
                                <button
                                    onClick={() => openEdit(role)}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Edit
                                </button>
                                <button
                                    onClick={() => {
                                        setCloning(role);
                                        setCloneName(`${role.name} copy`);
                                    }}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Clone
                                </button>
                                <button
                                    onClick={() => {
                                        setApplying(role);
                                        setTemplateId('');
                                    }}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Apply template
                                </button>
                                {!role.is_system &&
                                    role.platform_users_count === 0 && (
                                        <button
                                            onClick={() => destroy(role)}
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
                title={editing ? `Edit ${editing.name}` : 'New platform role'}
                maxWidth="2xl"
                footer={
                    <>
                        <SecondaryButton type="button" onClick={close}>
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="platform-role-form"
                            disabled={processing}
                        >
                            Save
                        </BiButton>
                    </>
                }
            >
                <form
                    id="platform-role-form"
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

                    <div>
                        <p className="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                            Permissions
                        </p>
                        <div className="max-h-72 space-y-3 overflow-y-auto rounded-md border border-gray-200 p-3 dark:border-gray-700">
                            {Object.entries(permissionsByModule).map(
                                ([module, perms]) => (
                                    <div key={module}>
                                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                            {module}
                                        </p>
                                        <div className="mt-1 grid grid-cols-2 gap-1 sm:grid-cols-3">
                                            {perms.map((perm) => (
                                                <label
                                                    key={perm.id}
                                                    className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                                >
                                                    <Checkbox
                                                        checked={data.permission_ids.includes(
                                                            perm.id,
                                                        )}
                                                        onChange={() =>
                                                            togglePermission(
                                                                perm.id,
                                                            )
                                                        }
                                                    />
                                                    {perm.action}
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                ),
                            )}
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
                        <BiButton type="submit" form="clone-platform-role-form">
                            Clone
                        </BiButton>
                    </>
                }
            >
                <form id="clone-platform-role-form" onSubmit={submitClone}>
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

            <BiModal
                show={applying !== null}
                onClose={() => setApplying(null)}
                title={`Apply template — ${applying?.name ?? ''}`}
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setApplying(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="apply-template-form"
                            disabled={!templateId}
                        >
                            Apply
                        </BiButton>
                    </>
                }
            >
                <form id="apply-template-form" onSubmit={submitApplyTemplate}>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Template (replaces current permissions)
                    </label>
                    <SelectInput
                        className="mt-1 block w-full"
                        value={templateId}
                        onChange={(e) => setTemplateId(e.target.value)}
                    >
                        <option value="">Select a template</option>
                        {templates.map((template) => (
                            <option key={template.id} value={template.id}>
                                {template.name}
                            </option>
                        ))}
                    </SelectInput>
                </form>
            </BiModal>
        </PlatformRbacLayout>
    );
}
