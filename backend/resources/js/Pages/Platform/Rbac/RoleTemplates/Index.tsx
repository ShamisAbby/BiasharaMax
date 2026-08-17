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

interface RoleTemplateRow {
    id: string;
    name: string;
    slug: string;
    scope: 'tenant' | 'platform';
    description: string | null;
    is_system: boolean;
    permissions_count: number;
    permission_ids: string[];
}

interface PermissionOption {
    id: string;
    module: string;
    scope: 'tenant' | 'platform';
    action: string;
    name: string;
    slug: string;
}

const EMPTY_FORM = {
    name: '',
    slug: '',
    scope: 'tenant' as 'tenant' | 'platform',
    description: '',
    permission_ids: [] as string[],
};

export default function RoleTemplatesIndex({
    roleTemplates: templates,
    permissions,
}: {
    roleTemplates: RoleTemplateRow[];
    permissions: PermissionOption[];
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const [editing, setEditing] = useState<RoleTemplateRow | null>(null);
    const [creating, setCreating] = useState(false);

    const { data, setData, post, patch, processing, errors, reset } =
        useForm(EMPTY_FORM);

    const openCreate = () => {
        reset();
        setData(EMPTY_FORM);
        setCreating(true);
    };

    const openEdit = (template: RoleTemplateRow) => {
        setData({
            name: template.name,
            slug: template.slug,
            scope: template.scope,
            description: template.description ?? '',
            permission_ids: template.permission_ids,
        });
        setEditing(template);
    };

    const close = () => {
        setEditing(null);
        setCreating(false);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (editing) {
            patch(route('platform.rbac.role-templates.update', editing.id), {
                onSuccess: () => {
                    close();
                    notify('Role template updated.', 'success');
                },
            });
        } else {
            post(route('platform.rbac.role-templates.store'), {
                onSuccess: () => {
                    close();
                    notify('Role template created.', 'success');
                },
            });
        }
    };

    const destroy = (template: RoleTemplateRow) => {
        askConfirm({
            title: `Delete "${template.name}"?`,
            message: 'This cannot be undone.',
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route('platform.rbac.role-templates.destroy', template.id),
                );
            },
        });
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
    const availablePermissions = permissions.filter(
        (p) => p.scope === data.scope,
    );
    const permissionsByModule = availablePermissions.reduce<
        Record<string, PermissionOption[]>
    >((acc, perm) => {
        (acc[perm.module] ??= []).push(perm);
        return acc;
    }, {});

    return (
        <PlatformRbacLayout title="Role Templates">
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {templates.length} reusable templates for quickly
                        provisioning roles.
                    </p>
                    <BiButton onClick={openCreate}>New template</BiButton>
                </div>

                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {templates.map((template) => (
                        <BiCard
                            key={template.id}
                            title={template.name}
                            actions={
                                <div className="flex gap-1.5">
                                    <BiBadge
                                        variant={
                                            template.scope === 'platform'
                                                ? 'warning'
                                                : 'info'
                                        }
                                    >
                                        {template.scope}
                                    </BiBadge>
                                    {template.is_system && (
                                        <BiBadge variant="neutral">
                                            System
                                        </BiBadge>
                                    )}
                                </div>
                            }
                        >
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {template.description ?? 'No description'}
                            </p>
                            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                {template.permissions_count} permissions
                            </p>

                            <div className="mt-4 flex flex-wrap gap-3 text-sm">
                                <button
                                    onClick={() => openEdit(template)}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Edit
                                </button>
                                {!template.is_system && (
                                    <button
                                        onClick={() => destroy(template)}
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
                title={editing ? `Edit ${editing.name}` : 'New role template'}
                maxWidth="2xl"
                footer={
                    <>
                        <SecondaryButton type="button" onClick={close}>
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="role-template-form"
                            disabled={processing}
                        >
                            Save
                        </BiButton>
                    </>
                }
            >
                <form
                    id="role-template-form"
                    onSubmit={submit}
                    className="space-y-4"
                >
                    <div className="grid gap-4 sm:grid-cols-3">
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
                                Scope
                            </label>
                            <SelectInput
                                className="mt-1 block w-full"
                                value={data.scope}
                                onChange={(e) => {
                                    setData(
                                        'scope',
                                        e.target.value as 'tenant' | 'platform',
                                    );
                                    setData('permission_ids', []);
                                }}
                            >
                                <option value="tenant">Tenant</option>
                                <option value="platform">Platform</option>
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
        </PlatformRbacLayout>
    );
}
