import Badge from '@/Components/Badge';
import Card from '@/Components/Card';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Permission, Role } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type GroupedPermissions = Record<string, Permission[]>;

function RoleForm({
    role,
    groupedPermissions,
    onSaved,
}: {
    role?: Role;
    groupedPermissions: GroupedPermissions;
    onSaved: () => void;
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        name: role?.name ?? '',
        description: role?.description ?? '',
        permissions: role?.permissions?.map((p) => p.id) ?? [],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const options = { onSuccess: onSaved };

        if (role) {
            patch(route('settings.roles.update', role.id), options);
        } else {
            post(route('settings.roles.store'), options);
        }
    };

    const togglePermission = (permissionId: string) => {
        setData(
            'permissions',
            data.permissions.includes(permissionId)
                ? data.permissions.filter((id) => id !== permissionId)
                : [...data.permissions, permissionId],
        );
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div>
                <InputLabel htmlFor="role_name" value="Role name" />
                <TextInput
                    id="role_name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                    disabled={role?.is_system}
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="role_description" value="Description (optional)" />
                <TextInput
                    id="role_description"
                    className="mt-1 block w-full"
                    value={data.description}
                    onChange={(e) => setData('description', e.target.value)}
                />
            </div>

            <div>
                <InputLabel value="Permissions" />
                <div className="mt-2 max-h-72 space-y-4 overflow-y-auto rounded-md border border-gray-200 p-4 dark:border-gray-700">
                    {Object.entries(groupedPermissions).map(([module, permissions]) => (
                        <div key={module}>
                            <p className="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                {module}
                            </p>
                            <div className="mt-1 space-y-1">
                                {permissions.map((permission) => (
                                    <label
                                        key={permission.id}
                                        className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                    >
                                        <input
                                            type="checkbox"
                                            className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            checked={data.permissions.includes(permission.id)}
                                            onChange={() => togglePermission(permission.id)}
                                        />
                                        {permission.name}
                                    </label>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="flex justify-end gap-3">
                <PrimaryButton disabled={processing}>
                    {role ? 'Save changes' : 'Create role'}
                </PrimaryButton>
            </div>
        </form>
    );
}

export default function Roles({
    roles,
    permissions,
}: {
    roles: Role[];
    permissions: GroupedPermissions;
}) {
    const [editingRole, setEditingRole] = useState<Role | null>(null);
    const [creating, setCreating] = useState(false);

    const deleteRole = (role: Role) => {
        if (confirm(`Delete the "${role.name}" role?`)) {
            router.delete(route('settings.roles.destroy', role.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Roles & Permissions
                </h2>
            }
        >
            <Head title="Roles & Permissions" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    <Card
                        title="Roles"
                        description="Control what each role can see and do in your business."
                        actions={
                            <PrimaryButton onClick={() => setCreating(true)}>
                                New role
                            </PrimaryButton>
                        }
                    >
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {roles.map((role) => (
                                <div
                                    key={role.id}
                                    className="flex items-center justify-between py-3"
                                >
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <p className="font-medium text-gray-900 dark:text-gray-100">
                                                {role.name}
                                            </p>
                                            {role.is_system && (
                                                <Badge variant="info">System</Badge>
                                            )}
                                        </div>
                                        <p className="text-sm text-gray-500 dark:text-gray-400">
                                            {role.users_count ?? 0} member
                                            {role.users_count === 1 ? '' : 's'} &middot;{' '}
                                            {role.permissions?.length ?? 0} permissions
                                        </p>
                                    </div>
                                    <div className="flex gap-2">
                                        <SecondaryButton onClick={() => setEditingRole(role)}>
                                            Edit
                                        </SecondaryButton>
                                        {!role.is_system && (
                                            <DangerButton onClick={() => deleteRole(role)}>
                                                Delete
                                            </DangerButton>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>
                </div>
            </div>

            <Modal show={creating} onClose={() => setCreating(false)} maxWidth="lg">
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        New role
                    </h2>
                    <div className="mt-4">
                        <RoleForm
                            groupedPermissions={permissions}
                            onSaved={() => setCreating(false)}
                        />
                    </div>
                </div>
            </Modal>

            <Modal show={editingRole !== null} onClose={() => setEditingRole(null)} maxWidth="lg">
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        Edit role
                    </h2>
                    <div className="mt-4">
                        {editingRole && (
                            <RoleForm
                                role={editingRole}
                                groupedPermissions={permissions}
                                onSaved={() => setEditingRole(null)}
                            />
                        )}
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
