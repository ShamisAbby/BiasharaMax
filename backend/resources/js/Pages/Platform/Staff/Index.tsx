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
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface AdminRow {
    id: string;
    name: string;
    email: string;
    status: 'invited' | 'active' | 'suspended';
    platform_role: { id: string; name: string } | null;
    last_login_at: string | null;
    created_at: string;
}

const STATUS_VARIANT = {
    invited: 'info',
    active: 'success',
    suspended: 'danger',
} as const;

export default function PlatformStaffIndex({
    admins,
    platformRoles,
}: {
    admins: AdminRow[];
    platformRoles: { id: string; name: string }[];
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const { platformAuth } = usePage().props as {
        platformAuth: { user?: { id: string } };
    };
    const [inviting, setInviting] = useState(false);
    const [editing, setEditing] = useState<AdminRow | null>(null);

    const inviteForm = useForm({ name: '', email: '', platform_role_id: '' });
    const editForm = useForm({ name: '', platform_role_id: '' });

    const submitInvite = (e: FormEvent) => {
        e.preventDefault();

        inviteForm.post(route('platform.staff.store'), {
            onSuccess: () => {
                setInviting(false);
                inviteForm.reset();
                notify('Invitation sent.', 'success');
            },
        });
    };

    const openEdit = (admin: AdminRow) => {
        editForm.setData({
            name: admin.name,
            platform_role_id: admin.platform_role?.id ?? '',
        });
        setEditing(admin);
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editing) return;

        editForm.patch(route('platform.staff.update', editing.id), {
            onSuccess: () => {
                setEditing(null);
                notify('Admin updated.', 'success');
            },
        });
    };

    const toggleStatus = (admin: AdminRow) => {
        router.post(
            route(
                admin.status === 'suspended'
                    ? 'platform.staff.activate'
                    : 'platform.staff.deactivate',
                admin.id,
            ),
            {},
            {
                onError: (errors) => {
                    if (errors.platform_user)
                        notify(errors.platform_user, 'error');
                },
            },
        );
    };

    const destroy = (admin: AdminRow) => {
        askConfirm({
            title: `Remove ${admin.name}'s platform admin access?`,
            message: 'This cannot be undone.',
            tone: 'danger',
            confirmLabel: 'Remove',
            onConfirm: () => {
                router.delete(route('platform.staff.destroy', admin.id), {
                    onError: (errors) => {
                        if (errors.platform_user)
                            notify(errors.platform_user, 'error');
                    },
                });
            },
        });
    };

    const columns: BiTableColumn<AdminRow>[] = [
        {
            key: 'admin',
            label: 'Admin',
            render: (admin) => (
                <>
                    <p className="font-medium text-gray-900 dark:text-gray-100">
                        {admin.name}
                    </p>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        {admin.email}
                    </p>
                </>
            ),
        },
        {
            key: 'role',
            label: 'Platform role',
            render: (admin) => admin.platform_role?.name ?? 'Unrestricted',
        },
        {
            key: 'status',
            label: 'Status',
            render: (admin) => (
                <BiBadge variant={STATUS_VARIANT[admin.status]}>
                    {admin.status}
                </BiBadge>
            ),
        },
        {
            key: 'last_login',
            label: 'Last login',
            render: (admin) =>
                admin.last_login_at
                    ? new Date(admin.last_login_at).toLocaleString()
                    : 'Never',
        },
        {
            key: 'actions',
            label: 'Actions',
            align: 'right',
            render: (admin) => {
                const isSelf = admin.id === platformAuth.user?.id;

                return (
                    <div className="flex justify-end gap-3">
                        <button
                            onClick={() => openEdit(admin)}
                            className="text-indigo-600 hover:underline"
                        >
                            Edit
                        </button>
                        {!isSelf && admin.status !== 'invited' && (
                            <button
                                onClick={() => toggleStatus(admin)}
                                className={
                                    admin.status === 'suspended'
                                        ? 'text-emerald-600 hover:underline'
                                        : 'text-amber-600 hover:underline'
                                }
                            >
                                {admin.status === 'suspended'
                                    ? 'Activate'
                                    : 'Suspend'}
                            </button>
                        )}
                        {!isSelf && (
                            <button
                                onClick={() => destroy(admin)}
                                className="text-red-600 hover:underline"
                            >
                                Remove
                            </button>
                        )}
                    </div>
                );
            },
        },
    ];

    return (
        <PlatformLayout>
            <Head title="Platform Admins" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Platform Admins
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {admins.length} accounts with access to this
                            SuperAdmin console.
                        </p>
                    </div>
                    <BiButton onClick={() => setInviting(true)}>
                        Invite admin
                    </BiButton>
                </div>

                <BiDataGrid
                    columns={columns}
                    paginated={{
                        data: admins,
                        meta: {
                            current_page: 1,
                            last_page: 1,
                            total: admins.length,
                            links: [],
                        },
                    }}
                    rowKey={(admin) => admin.id}
                    emptyMessage="No platform admins yet."
                />
            </div>

            <BiModal
                show={inviting}
                onClose={() => setInviting(false)}
                title="Invite a platform admin"
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setInviting(false)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="invite-admin-form"
                            disabled={inviteForm.processing}
                        >
                            Send invitation
                        </BiButton>
                    </>
                }
            >
                <form
                    id="invite-admin-form"
                    onSubmit={submitInvite}
                    className="space-y-4"
                >
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Name
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={inviteForm.data.name}
                            onChange={(e) =>
                                inviteForm.setData('name', e.target.value)
                            }
                        />
                        {inviteForm.errors.name && (
                            <p className="mt-1 text-sm text-red-600">
                                {inviteForm.errors.name}
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
                            value={inviteForm.data.email}
                            onChange={(e) =>
                                inviteForm.setData('email', e.target.value)
                            }
                        />
                        {inviteForm.errors.email && (
                            <p className="mt-1 text-sm text-red-600">
                                {inviteForm.errors.email}
                            </p>
                        )}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Platform role
                        </label>
                        <SelectInput
                            className="mt-1 block w-full"
                            value={inviteForm.data.platform_role_id}
                            onChange={(e) =>
                                inviteForm.setData(
                                    'platform_role_id',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">Unrestricted (no role)</option>
                            {platformRoles.map((role) => (
                                <option key={role.id} value={role.id}>
                                    {role.name}
                                </option>
                            ))}
                        </SelectInput>
                    </div>
                </form>
            </BiModal>

            <BiModal
                show={editing !== null}
                onClose={() => setEditing(null)}
                title={`Edit ${editing?.name ?? ''}`}
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
                            form="edit-admin-form"
                            disabled={editForm.processing}
                        >
                            Save
                        </BiButton>
                    </>
                }
            >
                <form
                    id="edit-admin-form"
                    onSubmit={submitEdit}
                    className="space-y-4"
                >
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Name
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={editForm.data.name}
                            onChange={(e) =>
                                editForm.setData('name', e.target.value)
                            }
                        />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Platform role
                        </label>
                        <SelectInput
                            className="mt-1 block w-full"
                            value={editForm.data.platform_role_id}
                            onChange={(e) =>
                                editForm.setData(
                                    'platform_role_id',
                                    e.target.value,
                                )
                            }
                        >
                            <option value="">Unrestricted (no role)</option>
                            {platformRoles.map((role) => (
                                <option key={role.id} value={role.id}>
                                    {role.name}
                                </option>
                            ))}
                        </SelectInput>
                    </div>
                </form>
            </BiModal>
        </PlatformLayout>
    );
}
